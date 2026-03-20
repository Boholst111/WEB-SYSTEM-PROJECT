<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SearchLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchService
{
    /**
     * Perform advanced search with relevance scoring.
     */
    public function search(
        string $query,
        array $filters = [],
        string $sortBy = 'relevance',
        string $sortOrder = 'desc',
        int $perPage = 20
    ): array {
        $cacheKey = $this->getCacheKey('search', $query, $filters, $sortBy, $sortOrder, $perPage);
        
        return Cache::remember($cacheKey, 300, function () use ($query, $filters, $sortBy, $sortOrder, $perPage) {
            $queryBuilder = Product::query()
                ->with(['brand', 'category'])
                ->active();

            // Apply full-text search with relevance scoring
            $queryBuilder->search($query);

            // Apply filters
            if (!empty($filters)) {
                $queryBuilder->filter($filters);
            }

            // Apply sorting
            $this->applySorting($queryBuilder, $sortBy, $sortOrder, $query);

            $products = $queryBuilder->paginate($perPage);

            return [
                'query' => $query,
                'products' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
                'filters_applied' => $filters,
            ];
        });
    }

    /**
     * Get autocomplete suggestions.
     */
    public function getAutocompleteSuggestions(string $query, int $limit = 10): array
    {
        $cacheKey = $this->getCacheKey('autocomplete', $query, $limit);
        
        return Cache::remember($cacheKey, 600, function () use ($query, $limit) {
            $suggestions = [];

            // Get product name suggestions
            $productSuggestions = Product::query()
                ->active()
                ->where('name', 'like', '%' . $query . '%')
                ->select('name')
                ->distinct()
                ->limit($limit)
                ->pluck('name')
                ->toArray();

            $suggestions = array_merge($suggestions, $productSuggestions);

            // Get brand name suggestions
            $brandSuggestions = DB::table('brands')
                ->where('name', 'like', '%' . $query . '%')
                ->select('name')
                ->distinct()
                ->limit($limit)
                ->pluck('name')
                ->toArray();

            $suggestions = array_merge($suggestions, $brandSuggestions);

            // Get category name suggestions
            $categorySuggestions = DB::table('categories')
                ->where('name', 'like', '%' . $query . '%')
                ->select('name')
                ->distinct()
                ->limit($limit)
                ->pluck('name')
                ->toArray();

            $suggestions = array_merge($suggestions, $categorySuggestions);

            // Remove duplicates and limit
            $suggestions = array_unique($suggestions);
            $suggestions = array_slice($suggestions, 0, $limit);

            return array_values($suggestions);
        });
    }

    /**
     * Get search suggestions with product previews.
     */
    public function getSuggestions(string $query): array
    {
        $cacheKey = $this->getCacheKey('suggestions', $query);
        
        return Cache::remember($cacheKey, 600, function () use ($query) {
            // Get text suggestions
            $textSuggestions = $this->getAutocompleteSuggestions($query, 5);

            // Get product previews
            $products = Product::query()
                ->with(['brand', 'category'])
                ->active()
                ->search($query)
                ->limit(5)
                ->get();

            return [
                'suggestions' => $textSuggestions,
                'products' => $products,
            ];
        });
    }

    /**
     * Log search query for analytics.
     */
    public function logSearch(
        string $query,
        int $resultsCount,
        ?int $clickedProductId = null,
        ?int $userId = null
    ): void {
        SearchLog::create([
            'user_id' => $userId,
            'query' => $query,
            'results_count' => $resultsCount,
            'clicked_product_id' => $clickedProductId,
            'searched_at' => now(),
        ]);
    }

    /**
     * Get popular searches.
     */
    public function getPopularSearches(int $limit = 10): array
    {
        $cacheKey = "popular_searches:{$limit}";
        
        return Cache::remember($cacheKey, 3600, function () use ($limit) {
            return SearchLog::query()
                ->select('query', DB::raw('COUNT(*) as search_count'))
                ->where('searched_at', '>=', now()->subDays(30))
                ->groupBy('query')
                ->orderByDesc('search_count')
                ->limit($limit)
                ->pluck('search_count', 'query')
                ->toArray();
        });
    }

    /**
     * Apply sorting to query builder.
     */
    protected function applySorting($queryBuilder, string $sortBy, string $sortOrder, string $query): void
    {
        switch ($sortBy) {
            case 'relevance':
                // Relevance is already applied by the search scope
                break;
            case 'price':
                $queryBuilder->orderBy('current_price', $sortOrder);
                break;
            case 'name':
                $queryBuilder->orderBy('name', $sortOrder);
                break;
            case 'created_at':
                $queryBuilder->orderBy('created_at', $sortOrder);
                break;
            case 'popularity':
                // Order by number of sales (order items count)
                $queryBuilder->withCount('orderItems')
                    ->orderBy('order_items_count', $sortOrder);
                break;
        }
    }

    /**
     * Generate cache key for search results.
     */
    protected function getCacheKey(string $type, ...$params): string
    {
        return 'search:' . $type . ':' . md5(serialize($params));
    }
}
