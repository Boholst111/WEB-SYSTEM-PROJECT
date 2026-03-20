<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    // Cache TTL constants (in seconds)
    const PRODUCT_CACHE_TTL = 7200; // 2 hours
    const CATEGORY_CACHE_TTL = 14400; // 4 hours
    const USER_SESSION_TTL = 86400; // 24 hours
    const QUERY_RESULT_TTL = 3600; // 1 hour
    const STATIC_DATA_TTL = 43200; // 12 hours

    /**
     * Cache product data with automatic invalidation
     */
    public function cacheProduct(int $productId, array $data): void
    {
        $key = $this->getProductCacheKey($productId);
        Cache::put($key, $data, self::PRODUCT_CACHE_TTL);
        
        // Add to product index for bulk invalidation
        $this->addToProductIndex($productId);
    }

    /**
     * Get cached product data
     */
    public function getProduct(int $productId): ?array
    {
        $key = $this->getProductCacheKey($productId);
        return Cache::get($key);
    }

    /**
     * Invalidate product cache
     */
    public function invalidateProduct(int $productId): void
    {
        $key = $this->getProductCacheKey($productId);
        Cache::forget($key);
        
        // Also invalidate related caches
        $this->invalidateProductList();
        $this->removeFromProductIndex($productId);
        
        Log::info('Product cache invalidated', ['product_id' => $productId]);
    }

    /**
     * Cache product list with filters
     */
    public function cacheProductList(string $filterHash, array $data): void
    {
        $key = "products:list:{$filterHash}";
        Cache::put($key, $data, self::QUERY_RESULT_TTL);
    }

    /**
     * Get cached product list
     */
    public function getProductList(string $filterHash): ?array
    {
        $key = "products:list:{$filterHash}";
        return Cache::get($key);
    }

    /**
     * Invalidate all product list caches
     */
    public function invalidateProductList(): void
    {
        // Use tag-based invalidation if available, otherwise clear by pattern
        Cache::tags(['products', 'product_lists'])->flush();
    }

    /**
     * Cache category data
     */
    public function cacheCategory(int $categoryId, array $data): void
    {
        $key = "category:{$categoryId}";
        Cache::put($key, $data, self::CATEGORY_CACHE_TTL);
    }

    /**
     * Get cached category data
     */
    public function getCategory(int $categoryId): ?array
    {
        $key = "category:{$categoryId}";
        return Cache::get($key);
    }

    /**
     * Cache all categories
     */
    public function cacheAllCategories(array $data): void
    {
        Cache::put('categories:all', $data, self::CATEGORY_CACHE_TTL);
    }

    /**
     * Get all cached categories
     */
    public function getAllCategories(): ?array
    {
        return Cache::get('categories:all');
    }

    /**
     * Invalidate category cache
     */
    public function invalidateCategory(int $categoryId): void
    {
        Cache::forget("category:{$categoryId}");
        Cache::forget('categories:all');
        
        Log::info('Category cache invalidated', ['category_id' => $categoryId]);
    }

    /**
     * Cache user preferences
     */
    public function cacheUserPreferences(int $userId, array $preferences): void
    {
        $key = "user:preferences:{$userId}";
        Cache::put($key, $preferences, self::USER_SESSION_TTL);
    }

    /**
     * Get cached user preferences
     */
    public function getUserPreferences(int $userId): ?array
    {
        $key = "user:preferences:{$userId}";
        return Cache::get($key);
    }

    /**
     * Cache database query result
     */
    public function cacheQueryResult(string $queryHash, $result, int $ttl = null): void
    {
        $ttl = $ttl ?? self::QUERY_RESULT_TTL;
        Cache::put("query:{$queryHash}", $result, $ttl);
    }

    /**
     * Get cached query result
     */
    public function getQueryResult(string $queryHash)
    {
        return Cache::get("query:{$queryHash}");
    }

    /**
     * Cache brand data
     */
    public function cacheBrand(int $brandId, array $data): void
    {
        $key = "brand:{$brandId}";
        Cache::put($key, $data, self::STATIC_DATA_TTL);
    }

    /**
     * Get cached brand data
     */
    public function getBrand(int $brandId): ?array
    {
        $key = "brand:{$brandId}";
        return Cache::get($key);
    }

    /**
     * Cache all brands
     */
    public function cacheAllBrands(array $data): void
    {
        Cache::put('brands:all', $data, self::STATIC_DATA_TTL);
    }

    /**
     * Get all cached brands
     */
    public function getAllBrands(): ?array
    {
        return Cache::get('brands:all');
    }

    /**
     * Invalidate brand cache
     */
    public function invalidateBrand(int $brandId): void
    {
        Cache::forget("brand:{$brandId}");
        Cache::forget('brands:all');
        
        Log::info('Brand cache invalidated', ['brand_id' => $brandId]);
    }

    /**
     * Cache filter options
     */
    public function cacheFilterOptions(array $data): void
    {
        Cache::put('filters:options', $data, self::STATIC_DATA_TTL);
    }

    /**
     * Get cached filter options
     */
    public function getFilterOptions(): ?array
    {
        return Cache::get('filters:options');
    }

    /**
     * Generate product cache key
     */
    private function getProductCacheKey(int $productId): string
    {
        return "product:{$productId}";
    }

    /**
     * Add product to index for bulk operations
     */
    private function addToProductIndex(int $productId): void
    {
        $index = Cache::get('products:index', []);
        if (!in_array($productId, $index)) {
            $index[] = $productId;
            Cache::put('products:index', $index, self::PRODUCT_CACHE_TTL);
        }
    }

    /**
     * Remove product from index
     */
    private function removeFromProductIndex(int $productId): void
    {
        $index = Cache::get('products:index', []);
        $index = array_filter($index, fn($id) => $id !== $productId);
        Cache::put('products:index', array_values($index), self::PRODUCT_CACHE_TTL);
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUpCache(): void
    {
        Log::info('Starting cache warm-up');
        
        // This method can be called during deployment or scheduled tasks
        // to pre-populate cache with frequently accessed data
        
        Log::info('Cache warm-up completed');
    }

    /**
     * Clear all application caches
     */
    public function clearAllCaches(): void
    {
        Cache::flush();
        Log::info('All caches cleared');
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        // This would return cache hit/miss rates and other metrics
        // Implementation depends on Redis monitoring tools
        return [
            'status' => 'operational',
            'driver' => config('cache.default'),
        ];
    }
}
