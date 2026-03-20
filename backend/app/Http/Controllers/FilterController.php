<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FilterController extends Controller
{
    /**
     * Get available filter options for product catalog.
     */
    public function index(Request $request): JsonResponse
    {
        $categoryId = $request->get('category_id');
        $brandId = $request->get('brand_id');

        // Base query for active products
        $baseQuery = Product::query()->where('products.status', 'active');

        // Apply context filters if provided
        if ($categoryId) {
            $baseQuery->where('category_id', $categoryId);
        }
        if ($brandId) {
            $baseQuery->where('brand_id', $brandId);
        }

        // Get available scales
        $scales = $this->getAvailableScales($baseQuery);

        // Get available materials
        $materials = $this->getAvailableMaterials($baseQuery);

        // Get available features
        $features = $this->getAvailableFeatures($baseQuery);

        // Get price range
        $priceRange = $this->getPriceRange($baseQuery);

        // Get available brands (if not filtered by brand)
        $brands = $brandId ? [] : $this->getAvailableBrands($baseQuery);

        // Get available categories (if not filtered by category)
        $categories = $categoryId ? [] : $this->getAvailableCategories($baseQuery);

        // Get availability options
        $availability = $this->getAvailabilityOptions($baseQuery);

        return response()->json([
            'success' => true,
            'data' => [
                'scales' => $scales,
                'materials' => $materials,
                'features' => $features,
                'price_range' => $priceRange,
                'brands' => $brands,
                'categories' => $categories,
                'availability' => $availability,
            ],
            'context' => [
                'category_id' => $categoryId,
                'brand_id' => $brandId,
            ],
        ]);
    }

    /**
     * Get available scales with product counts.
     */
    private function getAvailableScales($baseQuery): array
    {
        $scales = (clone $baseQuery)
            ->select('scale', DB::raw('COUNT(*) as count'))
            ->whereNotNull('scale')
            ->groupBy('scale')
            ->orderBy('scale')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->scale,
                    'label' => $item->scale,
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return $scales;
    }

    /**
     * Get available materials with product counts.
     */
    private function getAvailableMaterials($baseQuery): array
    {
        $materials = (clone $baseQuery)
            ->select('material', DB::raw('COUNT(*) as count'))
            ->whereNotNull('material')
            ->groupBy('material')
            ->orderBy('material')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->material,
                    'label' => ucfirst($item->material),
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return $materials;
    }

    /**
     * Get available features with product counts.
     */
    private function getAvailableFeatures($baseQuery): array
    {
        // Get all products with features
        $products = (clone $baseQuery)
            ->whereNotNull('features')
            ->select('features')
            ->get();

        $featureCounts = [];
        
        foreach ($products as $product) {
            $features = $product->features ?? [];
            foreach ($features as $feature) {
                if (!isset($featureCounts[$feature])) {
                    $featureCounts[$feature] = 0;
                }
                $featureCounts[$feature]++;
            }
        }

        // Sort by feature name
        ksort($featureCounts);

        $features = [];
        foreach ($featureCounts as $feature => $count) {
            $features[] = [
                'value' => $feature,
                'label' => ucwords(str_replace('_', ' ', $feature)),
                'count' => $count,
            ];
        }

        return $features;
    }

    /**
     * Get price range (min and max).
     */
    private function getPriceRange($baseQuery): array
    {
        $priceStats = (clone $baseQuery)
            ->selectRaw('MIN(current_price) as min_price, MAX(current_price) as max_price')
            ->first();

        return [
            'min' => (float) ($priceStats->min_price ?? 0),
            'max' => (float) ($priceStats->max_price ?? 0),
        ];
    }

    /**
     * Get available brands with product counts.
     */
    private function getAvailableBrands($baseQuery): array
    {
        $brands = (clone $baseQuery)
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->select('brands.id', 'brands.name', 'brands.slug', DB::raw('COUNT(*) as count'))
            ->where('brands.status', 'active')
            ->groupBy('brands.id', 'brands.name', 'brands.slug')
            ->orderBy('brands.name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return $brands;
    }

    /**
     * Get available categories with product counts.
     */
    private function getAvailableCategories($baseQuery): array
    {
        $categories = (clone $baseQuery)
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.id', 'categories.name', 'categories.slug', 'categories.parent_id', DB::raw('COUNT(*) as count'))
            ->where('categories.status', 'active')
            ->groupBy('categories.id', 'categories.name', 'categories.slug', 'categories.parent_id')
            ->orderBy('categories.name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'parent_id' => $item->parent_id,
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return $categories;
    }

    /**
     * Get availability options with counts.
     */
    private function getAvailabilityOptions($baseQuery): array
    {
        $inStockCount = (clone $baseQuery)->where('stock_quantity', '>', 0)->count();
        $preOrderCount = (clone $baseQuery)->where('is_preorder', true)->count();
        $chaseVariantCount = (clone $baseQuery)->where('is_chase_variant', true)->count();
        $onSaleCount = (clone $baseQuery)->whereColumn('current_price', '<', 'base_price')->count();

        return [
            [
                'value' => 'in_stock',
                'label' => 'In Stock',
                'count' => $inStockCount,
            ],
            [
                'value' => 'preorder',
                'label' => 'Pre-order',
                'count' => $preOrderCount,
            ],
            [
                'value' => 'chase_variant',
                'label' => 'Chase Variant',
                'count' => $chaseVariantCount,
            ],
            [
                'value' => 'on_sale',
                'label' => 'On Sale',
                'count' => $onSaleCount,
            ],
        ];
    }
}