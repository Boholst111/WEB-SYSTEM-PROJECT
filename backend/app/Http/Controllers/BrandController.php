<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    /**
     * Display a listing of brands.
     */
    public function index(Request $request): JsonResponse
    {
        $includeProducts = $request->boolean('include_products', false);
        $onlyWithProducts = $request->boolean('only_with_products', false);
        $perPage = min($request->get('per_page', 50), 100);

        $query = Brand::query()->active();

        // Filter to only brands with products if requested
        if ($onlyWithProducts) {
            $query->withProducts();
        }

        // Apply search if provided
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        // Apply sorting (except for products_count which is handled after transformation)
        $allowedSorts = ['name', 'products_count', 'created_at'];
        if (in_array($sortBy, $allowedSorts) && $sortBy !== 'products_count') {
            $query->orderBy($sortBy, $sortOrder);
        }

        $brands = $query->paginate($perPage);

        // Transform brands to use correct products_count and apply sorting if needed
        $brands->getCollection()->transform(function ($brand) use ($includeProducts) {
            if ($includeProducts) {
                $brand->load(['products' => function ($query) {
                    $query->with('category')->active()->limit(10);
                }]);
            }
            
            // Set the active_products_count attribute so the accessor uses it
            $brand->setAttribute('active_products_count', $brand->active_products_count);
            
            return $brand;
        });

        // Apply sorting after transformation if needed
        if ($sortBy === 'products_count') {
            $collection = $brands->getCollection();
            if ($sortOrder === 'desc') {
                $collection = $collection->sortByDesc('products_count');
            } else {
                $collection = $collection->sortBy('products_count');
            }
            $brands->setCollection($collection->values());
        }

        return response()->json([
            'success' => true,
            'data' => $brands->items(),
            'pagination' => [
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
                'per_page' => $brands->perPage(),
                'total' => $brands->total(),
                'from' => $brands->firstItem(),
                'to' => $brands->lastItem(),
            ],
        ]);
    }

    /**
     * Display the specified brand with its products.
     */
    public function show(Brand $brand, Request $request): JsonResponse
    {
        $includeProducts = $request->boolean('include_products', true);
        $perPage = min($request->get('per_page', 20), 100);

        $brandData = $brand->toArray();
        $brandData['products_count'] = $brand->active_products_count;

        // Include products if requested
        if ($includeProducts) {
            $products = $brand->products()
                ->with(['category'])
                ->active()
                ->orderBy('name')
                ->paginate($perPage);

            $brandData['products'] = [
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $brandData,
        ]);
    }
}