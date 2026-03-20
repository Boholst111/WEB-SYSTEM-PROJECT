<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    protected RecommendationService $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    /**
     * Get personalized recommendations for the authenticated user.
     */
    public function personalized(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $userId = $request->user()?->id;

        $recommendations = $this->recommendationService->getPersonalizedRecommendations($userId, $limit);

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    /**
     * Get similar products.
     */
    public function similar(Request $request, int $productId): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $recommendations = $this->recommendationService->getSimilarProducts($productId, $limit);

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    /**
     * Get cross-sell recommendations (frequently bought together).
     */
    public function crossSell(Request $request, int $productId): JsonResponse
    {
        $limit = $request->get('limit', 6);

        $recommendations = $this->recommendationService->getCrossSellRecommendations($productId, $limit);

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    /**
     * Get upsell recommendations.
     */
    public function upsell(Request $request, int $productId): JsonResponse
    {
        $limit = $request->get('limit', 6);

        $recommendations = $this->recommendationService->getUpsellRecommendations($productId, $limit);

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    /**
     * Get trending products.
     */
    public function trending(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $recommendations = $this->recommendationService->getTrendingProducts($limit);

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    /**
     * Get new arrivals.
     */
    public function newArrivals(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $recommendations = $this->recommendationService->getNewArrivals($limit);

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }
}
