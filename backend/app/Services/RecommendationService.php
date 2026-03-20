<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class RecommendationService
{
    /**
     * Get personalized product recommendations for a user.
     */
    public function getPersonalizedRecommendations(?int $userId, int $limit = 10): Collection
    {
        if (!$userId) {
            return $this->getPopularProducts($limit);
        }

        $cacheKey = "recommendations:user:{$userId}:{$limit}";
        
        return Cache::remember($cacheKey, 1800, function () use ($userId, $limit) {
            $user = User::find($userId);
            
            if (!$user) {
                return $this->getPopularProducts($limit);
            }

            // Get user's browsing history (from wishlist and cart)
            $browsingHistory = $this->getUserBrowsingHistory($userId);
            
            if ($browsingHistory->isEmpty()) {
                return $this->getPopularProducts($limit);
            }

            // Get recommendations based on browsing history
            $recommendations = $this->getRecommendationsBasedOnHistory($browsingHistory, $limit);
            
            // If not enough recommendations, fill with popular products
            if ($recommendations->count() < $limit) {
                $popularProducts = $this->getPopularProducts($limit - $recommendations->count());
                $recommendations = $recommendations->merge($popularProducts)->unique('id');
            }

            return $recommendations->take($limit);
        });
    }

    /**
     * Get similar products based on a product.
     */
    public function getSimilarProducts(int $productId, int $limit = 10): Collection
    {
        $cacheKey = "recommendations:similar:{$productId}:{$limit}";
        
        return Cache::remember($cacheKey, 3600, function () use ($productId, $limit) {
            $product = Product::find($productId);
            
            if (!$product) {
                return collect();
            }

            // Find similar products based on category, brand, scale, and material
            return Product::query()
                ->active()
                ->where('id', '!=', $productId)
                ->where(function ($query) use ($product) {
                    $query->where('category_id', $product->category_id)
                        ->orWhere('brand_id', $product->brand_id)
                        ->orWhere('scale', $product->scale)
                        ->orWhere('material', $product->material);
                })
                ->with(['brand', 'category'])
                ->inStock()
                ->limit($limit * 2) // Get more to filter
                ->get()
                ->sortByDesc(function ($item) use ($product) {
                    // Calculate similarity score
                    $score = 0;
                    if ($item->category_id === $product->category_id) $score += 4;
                    if ($item->brand_id === $product->brand_id) $score += 3;
                    if ($item->scale === $product->scale) $score += 2;
                    if ($item->material === $product->material) $score += 1;
                    return $score;
                })
                ->take($limit)
                ->values();
        });
    }

    /**
     * Get cross-sell recommendations (frequently bought together).
     */
    public function getCrossSellRecommendations(int $productId, int $limit = 6): Collection
    {
        $cacheKey = "recommendations:cross_sell:{$productId}:{$limit}";
        
        return Cache::remember($cacheKey, 3600, function () use ($productId, $limit) {
            // Find products that were purchased together with this product
            $frequentlyBoughtTogether = DB::table('order_items as oi1')
                ->join('order_items as oi2', 'oi1.order_id', '=', 'oi2.order_id')
                ->where('oi1.product_id', $productId)
                ->where('oi2.product_id', '!=', $productId)
                ->select('oi2.product_id', DB::raw('COUNT(*) as frequency'))
                ->groupBy('oi2.product_id')
                ->orderByDesc('frequency')
                ->limit($limit)
                ->pluck('product_id');

            if ($frequentlyBoughtTogether->isEmpty()) {
                return $this->getSimilarProducts($productId, $limit);
            }

            return Product::query()
                ->active()
                ->whereIn('id', $frequentlyBoughtTogether)
                ->with(['brand', 'category'])
                ->get();
        });
    }

    /**
     * Get upsell recommendations (higher-priced alternatives).
     */
    public function getUpsellRecommendations(int $productId, int $limit = 6): Collection
    {
        $cacheKey = "recommendations:upsell:{$productId}:{$limit}";
        
        return Cache::remember($cacheKey, 3600, function () use ($productId, $limit) {
            $product = Product::find($productId);
            
            if (!$product) {
                return collect();
            }

            // Find similar but higher-priced products
            return Product::query()
                ->active()
                ->where('id', '!=', $productId)
                ->where('current_price', '>', $product->current_price)
                ->where('current_price', '<=', $product->current_price * 1.5) // Max 50% more expensive
                ->where(function ($query) use ($product) {
                    $query->where('category_id', $product->category_id)
                        ->orWhere('brand_id', $product->brand_id)
                        ->orWhere('scale', $product->scale);
                })
                ->with(['brand', 'category'])
                ->inStock()
                ->orderBy('current_price', 'asc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get trending products.
     */
    public function getTrendingProducts(int $limit = 10): Collection
    {
        $cacheKey = "recommendations:trending:{$limit}";
        
        return Cache::remember($cacheKey, 1800, function () use ($limit) {
            // Get products with most orders in the last 7 days
            $trendingProductIds = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.created_at', '>=', now()->subDays(7))
                ->whereIn('orders.status', ['confirmed', 'processing', 'shipped', 'delivered'])
                ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_sold'))
                ->groupBy('order_items.product_id')
                ->orderByDesc('total_sold')
                ->limit($limit)
                ->pluck('product_id');

            if ($trendingProductIds->isEmpty()) {
                return $this->getPopularProducts($limit);
            }

            return Product::query()
                ->active()
                ->whereIn('id', $trendingProductIds)
                ->with(['brand', 'category'])
                ->get()
                ->sortBy(function ($product) use ($trendingProductIds) {
                    return $trendingProductIds->search($product->id);
                })
                ->values();
        });
    }

    /**
     * Get new arrivals.
     */
    public function getNewArrivals(int $limit = 10): Collection
    {
        $cacheKey = "recommendations:new_arrivals:{$limit}";
        
        return Cache::remember($cacheKey, 3600, function () use ($limit) {
            return Product::query()
                ->active()
                ->inStock()
                ->with(['brand', 'category'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get popular products.
     */
    protected function getPopularProducts(int $limit = 10): Collection
    {
        $cacheKey = "recommendations:popular:{$limit}";
        
        return Cache::remember($cacheKey, 3600, function () use ($limit) {
            // Get products with most orders all-time
            $popularProductIds = OrderItem::query()
                ->select('product_id', DB::raw('SUM(quantity) as total_sold'))
                ->groupBy('product_id')
                ->orderByDesc('total_sold')
                ->limit($limit)
                ->pluck('product_id');

            if ($popularProductIds->isEmpty()) {
                return Product::query()
                    ->active()
                    ->inStock()
                    ->with(['brand', 'category'])
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
            }

            return Product::query()
                ->active()
                ->whereIn('id', $popularProductIds)
                ->with(['brand', 'category'])
                ->get()
                ->sortBy(function ($product) use ($popularProductIds) {
                    return $popularProductIds->search($product->id);
                })
                ->values();
        });
    }

    /**
     * Get user's browsing history.
     */
    protected function getUserBrowsingHistory(int $userId): Collection
    {
        // Get products from wishlist and cart
        $wishlistProducts = DB::table('wishlists')
            ->where('user_id', $userId)
            ->pluck('product_id');

        $cartProducts = DB::table('shopping_cart')
            ->where('user_id', $userId)
            ->pluck('product_id');

        // Get products from recent orders
        $orderProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', $userId)
            ->where('orders.created_at', '>=', now()->subDays(90))
            ->pluck('order_items.product_id');

        $productIds = $wishlistProducts
            ->merge($cartProducts)
            ->merge($orderProducts)
            ->unique();

        return Product::query()
            ->whereIn('id', $productIds)
            ->with(['brand', 'category'])
            ->get();
    }

    /**
     * Get recommendations based on browsing history.
     */
    protected function getRecommendationsBasedOnHistory(Collection $browsingHistory, int $limit): Collection
    {
        // Extract common attributes from browsing history
        $categories = $browsingHistory->pluck('category_id')->filter()->unique();
        $brands = $browsingHistory->pluck('brand_id')->filter()->unique();
        $scales = $browsingHistory->pluck('scale')->filter()->unique();
        $materials = $browsingHistory->pluck('material')->filter()->unique();

        // Find products matching these attributes
        return Product::query()
            ->active()
            ->whereNotIn('id', $browsingHistory->pluck('id'))
            ->where(function ($query) use ($categories, $brands, $scales, $materials) {
                if ($categories->isNotEmpty()) {
                    $query->orWhereIn('category_id', $categories);
                }
                if ($brands->isNotEmpty()) {
                    $query->orWhereIn('brand_id', $brands);
                }
                if ($scales->isNotEmpty()) {
                    $query->orWhereIn('scale', $scales);
                }
                if ($materials->isNotEmpty()) {
                    $query->orWhereIn('material', $materials);
                }
            })
            ->with(['brand', 'category'])
            ->inStock()
            ->limit($limit * 2)
            ->get()
            ->sortByDesc(function ($product) use ($categories, $brands, $scales, $materials) {
                // Calculate relevance score
                $score = 0;
                if ($categories->contains($product->category_id)) $score += 4;
                if ($brands->contains($product->brand_id)) $score += 3;
                if ($scales->contains($product->scale)) $score += 2;
                if ($materials->contains($product->material)) $score += 1;
                return $score;
            })
            ->take($limit)
            ->values();
    }
}
