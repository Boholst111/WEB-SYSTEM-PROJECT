<?php

namespace Tests\Unit\Services;

use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new CacheService();
        Cache::flush();
    }

    public function test_can_cache_and_retrieve_product(): void
    {
        $productId = 1;
        $productData = [
            'id' => $productId,
            'name' => 'Test Product',
            'price' => 99.99,
        ];

        $this->cacheService->cacheProduct($productId, $productData);
        $cached = $this->cacheService->getProduct($productId);

        $this->assertEquals($productData, $cached);
    }

    public function test_can_invalidate_product_cache(): void
    {
        $productId = 1;
        $productData = ['id' => $productId, 'name' => 'Test Product'];

        $this->cacheService->cacheProduct($productId, $productData);
        $this->assertNotNull($this->cacheService->getProduct($productId));

        $this->cacheService->invalidateProduct($productId);
        $this->assertNull($this->cacheService->getProduct($productId));
    }

    public function test_can_cache_and_retrieve_category(): void
    {
        $categoryId = 1;
        $categoryData = [
            'id' => $categoryId,
            'name' => 'Test Category',
        ];

        $this->cacheService->cacheCategory($categoryId, $categoryData);
        $cached = $this->cacheService->getCategory($categoryId);

        $this->assertEquals($categoryData, $cached);
    }

    public function test_can_cache_all_categories(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'Category 1'],
            ['id' => 2, 'name' => 'Category 2'],
        ];

        $this->cacheService->cacheAllCategories($categories);
        $cached = $this->cacheService->getAllCategories();

        $this->assertEquals($categories, $cached);
    }

    public function test_can_invalidate_category_cache(): void
    {
        $categoryId = 1;
        $categoryData = ['id' => $categoryId, 'name' => 'Test Category'];

        $this->cacheService->cacheCategory($categoryId, $categoryData);
        $this->cacheService->cacheAllCategories([$categoryData]);

        $this->cacheService->invalidateCategory($categoryId);

        $this->assertNull($this->cacheService->getCategory($categoryId));
        $this->assertNull($this->cacheService->getAllCategories());
    }

    public function test_can_cache_user_preferences(): void
    {
        $userId = 1;
        $preferences = [
            'theme' => 'dark',
            'notifications' => true,
        ];

        $this->cacheService->cacheUserPreferences($userId, $preferences);
        $cached = $this->cacheService->getUserPreferences($userId);

        $this->assertEquals($preferences, $cached);
    }

    public function test_can_cache_query_result(): void
    {
        $queryHash = md5('SELECT * FROM products');
        $result = ['product1', 'product2'];

        $this->cacheService->cacheQueryResult($queryHash, $result);
        $cached = $this->cacheService->getQueryResult($queryHash);

        $this->assertEquals($result, $cached);
    }

    public function test_can_cache_and_retrieve_brand(): void
    {
        $brandId = 1;
        $brandData = [
            'id' => $brandId,
            'name' => 'Test Brand',
        ];

        $this->cacheService->cacheBrand($brandId, $brandData);
        $cached = $this->cacheService->getBrand($brandId);

        $this->assertEquals($brandData, $cached);
    }

    public function test_can_cache_all_brands(): void
    {
        $brands = [
            ['id' => 1, 'name' => 'Brand 1'],
            ['id' => 2, 'name' => 'Brand 2'],
        ];

        $this->cacheService->cacheAllBrands($brands);
        $cached = $this->cacheService->getAllBrands();

        $this->assertEquals($brands, $cached);
    }

    public function test_can_cache_filter_options(): void
    {
        $filterOptions = [
            'scales' => ['1:64', '1:43'],
            'materials' => ['diecast', 'resin'],
        ];

        $this->cacheService->cacheFilterOptions($filterOptions);
        $cached = $this->cacheService->getFilterOptions();

        $this->assertEquals($filterOptions, $cached);
    }

    public function test_can_clear_all_caches(): void
    {
        $this->cacheService->cacheProduct(1, ['name' => 'Product']);
        $this->cacheService->cacheCategory(1, ['name' => 'Category']);

        $this->cacheService->clearAllCaches();

        $this->assertNull($this->cacheService->getProduct(1));
        $this->assertNull($this->cacheService->getCategory(1));
    }

    public function test_get_cache_stats_returns_array(): void
    {
        $stats = $this->cacheService->getCacheStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('status', $stats);
        $this->assertArrayHasKey('driver', $stats);
    }
}
