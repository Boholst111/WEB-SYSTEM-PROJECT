<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;

class ProductController extends Controller
{
    /**
     * Display a listing of products with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with(['brand', 'category'])
            ->active();

        // Apply filters
        $filters = $request->only([
            'scale', 'material', 'brand_id', 'category_id', 
            'features', 'min_price', 'max_price', 
            'is_chase_variant', 'is_preorder', 'in_stock'
        ]);

        if (!empty($filters)) {
            $query->filter($filters);
        }

        // Apply search if provided
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['name', 'current_price', 'created_at', 'stock_quantity'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
            'filters_applied' => $filters,
        ]);
    }

    /**
     * Advanced product search with full-text capabilities.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:255',
            'filters' => 'sometimes|array',
            'sort_by' => 'sometimes|string|in:relevance,name,price,created_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $searchQuery = $request->get('query');
        $filters = $request->get('filters', []);
        $sortBy = $request->get('sort_by', 'relevance');
        $sortOrder = $request->get('sort_order', 'desc');
        $perPage = $request->get('per_page', 20);

        $query = Product::query()
            ->with(['brand', 'category'])
            ->active();

        // Full-text search
        $query->search($searchQuery);

        // Apply additional filters
        if (!empty($filters)) {
            $query->filter($filters);
        }

        // Apply sorting
        switch ($sortBy) {
            case 'relevance':
                // MySQL full-text search already provides relevance scoring
                break;
            case 'price':
                $query->orderBy('current_price', $sortOrder);
                break;
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            case 'created_at':
                $query->orderBy('created_at', $sortOrder);
                break;
        }

        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'query' => $searchQuery,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
            'filters_applied' => $filters,
        ]);
    }

    /**
     * Display the specified product with detailed information.
     */
    public function show(Product $product): JsonResponse
    {
        // Load relationships and additional data
        $product->load([
            'brand',
            'category.parent',
            'reviews' => function ($query) {
                $query->with('user:id,first_name,last_name')
                      ->latest()
                      ->limit(10);
            }
        ]);

        // Add computed attributes
        $productData = $product->toArray();
        $productData['is_available'] = $product->isAvailable();
        $productData['is_low_stock'] = $product->isLowStock();
        $productData['is_on_sale'] = $product->isOnSale();
        $productData['average_rating'] = $product->average_rating;
        $productData['review_count'] = $product->review_count;
        $productData['formatted_price'] = $product->formatted_price;
        $productData['discount_percentage'] = $product->discount_percentage;

        // Add category breadcrumb
        if ($product->category) {
            $productData['category_breadcrumb'] = $product->category->breadcrumb;
        }

        return response()->json([
            'success' => true,
            'data' => $productData,
        ]);
    }
}