<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Advanced search with relevance scoring.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:255',
            'filters' => 'sometimes|array',
            'sort_by' => 'sometimes|string|in:relevance,name,price,created_at,popularity',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $results = $this->searchService->search(
            $request->get('query'),
            $request->get('filters', []),
            $request->get('sort_by', 'relevance'),
            $request->get('sort_order', 'desc'),
            $request->get('per_page', 20)
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Get autocomplete suggestions.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1|max:100',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $suggestions = $this->searchService->getAutocompleteSuggestions(
            $request->get('query'),
            $request->get('limit', 10)
        );

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    /**
     * Get search suggestions with products.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1|max:100',
        ]);

        $suggestions = $this->searchService->getSuggestions($request->get('query'));

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    /**
     * Log search query for analytics.
     */
    public function logSearch(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|max:255',
            'results_count' => 'required|integer|min:0',
            'clicked_product_id' => 'sometimes|integer|exists:products,id',
        ]);

        $this->searchService->logSearch(
            $request->get('query'),
            $request->get('results_count'),
            $request->get('clicked_product_id'),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Search logged successfully',
        ]);
    }

    /**
     * Get popular searches.
     */
    public function popularSearches(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        
        $popularSearches = $this->searchService->getPopularSearches($limit);

        return response()->json([
            'success' => true,
            'data' => $popularSearches,
        ]);
    }
}
