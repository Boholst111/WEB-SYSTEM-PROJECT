<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories with hierarchical structure.
     */
    public function index(Request $request): JsonResponse
    {
        $includeProducts = $request->boolean('include_products', false);
        $onlyWithProducts = $request->boolean('only_with_products', false);

        $query = Category::query()->active();

        // Filter to only categories with products if requested
        if ($onlyWithProducts) {
            $query->withProducts();
        }

        // Load products count
        $query->selectRaw('*, (SELECT COUNT(*) FROM products WHERE products.category_id = categories.id AND products.status = "active") as active_products_count');

        // Get root categories first
        $rootCategories = $query->root()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Build hierarchical structure
        $categories = $rootCategories->map(function ($category) use ($includeProducts) {
            return $this->buildCategoryTree($category, $includeProducts);
        });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Display the specified category with its products.
     */
    public function show(Category $category, Request $request): JsonResponse
    {
        $includeProducts = $request->boolean('include_products', true);
        $perPage = min($request->get('per_page', 20), 100);

        // Load category with relationships
        $category->load(['parent', 'children' => function ($query) {
            $query->active()->orderBy('sort_order')->orderBy('name');
        }]);

        $categoryData = $category->toArray();
        $categoryData['breadcrumb'] = $category->breadcrumb;
        $categoryData['products_count'] = $category->active_products_count;

        // Include products if requested
        if ($includeProducts) {
            $products = $category->products()
                ->with(['brand'])
                ->active()
                ->orderBy('name')
                ->paginate($perPage);

            $categoryData['products'] = [
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
            'data' => $categoryData,
        ]);
    }

    /**
     * Build hierarchical category tree.
     */
    private function buildCategoryTree(Category $category, bool $includeProducts = false): array
    {
        $categoryData = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $category->image_url,
            'products_count' => $category->active_products_count ?? 0,
            'children' => [],
        ];

        // Add products if requested
        if ($includeProducts) {
            $categoryData['products'] = $category->products()
                ->with(['brand'])
                ->active()
                ->limit(10)
                ->get();
        }

        // Recursively build children
        $children = $category->children()
            ->active()
            ->selectRaw('*, (SELECT COUNT(*) FROM products WHERE products.category_id = categories.id AND products.status = "active") as active_products_count')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {
            $categoryData['children'][] = $this->buildCategoryTree($child, $includeProducts);
        }

        return $categoryData;
    }
}