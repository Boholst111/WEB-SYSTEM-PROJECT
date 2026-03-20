<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Performance Integration Test
 * 
 * Tests system performance under load, validates caching effectiveness,
 * database query optimization, and response time requirements.
 * 
 * **Feature: diecast-empire, Performance Testing**
 */
class PerformanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        Category::factory()->count(10)->create();
        Brand::factory()->count(20)->create();
        Product::factory()->count(100)->create();
        User::factory()->count(50)->create();
    }

    /**
     * Test: Product listing performance with caching
     * 
     * @test
     */
    public function test_product_listing_performance_with_caching()
    {
        // First request (cache miss)
        $startTime = microtime(true);
        $response1 = $this->getJson('/api/products?per_page=20');
        $firstRequestTime = (microtime(true) - $startTime) * 1000; // Convert to ms

        $response1->assertStatus(200);
        
        // Second request (cache hit)
        $startTime = microtime(true);
        $response2 = $this->getJson('/api/products?per_page=20');
        $secondRequestTime = (microtime(true) - $startTime) * 1000;

        $response2->assertStatus(200);

        // Cached request should be faster
        $this->assertLessThan($firstRequestTime, $secondRequestTime);
        
        // Response time should be under 2 seconds (2000ms)
        $this->assertLessThan(2000, $firstRequestTime, 
            "First request took {$firstRequestTime}ms, should be under 2000ms");
    }

    /**
     * Test: Complex filtering query performance
     * 
     * @test
     */
    public function test_complex_filtering_query_performance()
    {
        $category = Category::first();
        $brand = Brand::first();

        $startTime = microtime(true);
        
        $response = $this->getJson("/api/products?scale=1:64&material=diecast&category_id={$category->id}&brand_id={$brand->id}");
        
        $queryTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        
        // Complex query should complete within 2 seconds
        $this->assertLessThan(2000, $queryTime,
            "Complex filtering query took {$queryTime}ms, should be under 2000ms");
    }

    /**
     * Test: Search performance with full-text search
     * 
     * @test
     */
    public function test_search_performance()
    {
        $product = Product::first();
        $searchTerm = substr($product->name, 0, 5);

        $startTime = microtime(true);
        
        $response = $this->postJson('/api/search', [
            'query' => $searchTerm,
        ]);
        
        $searchTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        
        // Search should complete within 1 second
        $this->assertLessThan(1000, $searchTime,
            "Search query took {$searchTime}ms, should be under 1000ms");
    }

    /**
     * Test: Database query count optimization
     * 
     * @test
     */
    public function test_database_query_count_optimization()
    {
        DB::enableQueryLog();

        // Get products with relationships
        $response = $this->getJson('/api/products?per_page=10');
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertStatus(200);

        // Should use eager loading to minimize queries
        // Expect: 1 for products, 1 for categories, 1 for brands = ~3 queries
        $this->assertLessThan(10, $queryCount,
            "Query count is {$queryCount}, should be under 10 with proper eager loading");

        DB::disableQueryLog();
    }

    /**
     * Test: Concurrent user requests simulation
     * 
     * @test
     */
    public function test_concurrent_user_requests_simulation()
    {
        $users = User::factory()->count(10)->create();
        $products = Product::take(5)->get();

        $startTime = microtime(true);
        $responses = [];

        // Simulate concurrent requests
        foreach ($users as $user) {
            $token = $this->actingAs($user)->json('POST', '/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ])->json('token');

            // Each user browses products
            $responses[] = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson('/api/products');

            // Each user views a product
            $product = $products->random();
            $responses[] = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson("/api/products/{$product->id}");
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        // All requests should complete
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Average time per request should be reasonable
        $avgTimePerRequest = $totalTime / count($responses);
        $this->assertLessThan(500, $avgTimePerRequest,
            "Average time per request is {$avgTimePerRequest}ms, should be under 500ms");
    }

    /**
     * Test: Cache effectiveness for frequently accessed data
     * 
     * @test
     */
    public function test_cache_effectiveness()
    {
        $product = Product::first();
        $cacheKey = "product:{$product->id}";

        // Clear cache
        Cache::forget($cacheKey);

        // First access (cache miss)
        $startTime = microtime(true);
        $response1 = $this->getJson("/api/products/{$product->id}");
        $uncachedTime = (microtime(true) - $startTime) * 1000;

        $response1->assertStatus(200);

        // Second access (cache hit)
        $startTime = microtime(true);
        $response2 = $this->getJson("/api/products/{$product->id}");
        $cachedTime = (microtime(true) - $startTime) * 1000;

        $response2->assertStatus(200);

        // Cached response should be significantly faster
        $this->assertLessThan($uncachedTime * 0.5, $cachedTime,
            "Cached request ({$cachedTime}ms) should be at least 50% faster than uncached ({$uncachedTime}ms)");
    }

    /**
     * Test: API response time under load
     * 
     * @test
     */
    public function test_api_response_time_under_load()
    {
        $endpoints = [
            '/api/products',
            '/api/categories',
            '/api/brands',
            '/api/filters',
        ];

        $responseTimes = [];

        // Test each endpoint multiple times
        foreach ($endpoints as $endpoint) {
            for ($i = 0; $i < 5; $i++) {
                $startTime = microtime(true);
                $response = $this->getJson($endpoint);
                $responseTime = (microtime(true) - $startTime) * 1000;

                $response->assertStatus(200);
                $responseTimes[] = $responseTime;

                // Each request should be under 2 seconds
                $this->assertLessThan(2000, $responseTime,
                    "Request to {$endpoint} took {$responseTime}ms, should be under 2000ms");
            }
        }

        // Calculate average response time
        $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
        
        // Average should be well under the threshold
        $this->assertLessThan(1000, $avgResponseTime,
            "Average response time is {$avgResponseTime}ms, should be under 1000ms");
    }

    /**
     * Test: Memory usage during large dataset operations
     * 
     * @test
     */
    public function test_memory_usage_during_large_operations()
    {
        $memoryBefore = memory_get_usage(true);

        // Perform large dataset operation
        $response = $this->getJson('/api/products?per_page=100');
        
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

        $response->assertStatus(200);

        // Memory usage should be reasonable (under 50MB for this operation)
        $this->assertLessThan(50, $memoryUsed,
            "Memory usage is {$memoryUsed}MB, should be under 50MB");
    }

    /**
     * Test: Database connection pool efficiency
     * 
     * @test
     */
    public function test_database_connection_pool_efficiency()
    {
        $startTime = microtime(true);

        // Make multiple database queries
        for ($i = 0; $i < 20; $i++) {
            Product::inRandomOrder()->first();
        }

        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgTimePerQuery = $totalTime / 20;

        // Connection pooling should keep queries fast
        $this->assertLessThan(50, $avgTimePerQuery,
            "Average query time is {$avgTimePerQuery}ms, should be under 50ms with connection pooling");
    }

    /**
     * Test: Redis cache performance
     * 
     * @test
     */
    public function test_redis_cache_performance()
    {
        $testData = [
            'products' => Product::take(10)->get()->toArray(),
            'categories' => Category::all()->toArray(),
        ];

        // Test cache write performance
        $startTime = microtime(true);
        Cache::put('test_data', $testData, 3600);
        $writeTime = (microtime(true) - $startTime) * 1000;

        // Test cache read performance
        $startTime = microtime(true);
        $cachedData = Cache::get('test_data');
        $readTime = (microtime(true) - $startTime) * 1000;

        $this->assertNotNull($cachedData);
        
        // Cache operations should be very fast
        $this->assertLessThan(100, $writeTime,
            "Cache write took {$writeTime}ms, should be under 100ms");
        $this->assertLessThan(50, $readTime,
            "Cache read took {$readTime}ms, should be under 50ms");

        // Cleanup
        Cache::forget('test_data');
    }

    /**
     * Test: Pagination performance with large datasets
     * 
     * @test
     */
    public function test_pagination_performance()
    {
        // Test first page
        $startTime = microtime(true);
        $response1 = $this->getJson('/api/products?page=1&per_page=20');
        $firstPageTime = (microtime(true) - $startTime) * 1000;

        $response1->assertStatus(200);

        // Test middle page
        $startTime = microtime(true);
        $response2 = $this->getJson('/api/products?page=3&per_page=20');
        $middlePageTime = (microtime(true) - $startTime) * 1000;

        $response2->assertStatus(200);

        // Both should be fast
        $this->assertLessThan(1000, $firstPageTime,
            "First page load took {$firstPageTime}ms, should be under 1000ms");
        $this->assertLessThan(1000, $middlePageTime,
            "Middle page load took {$middlePageTime}ms, should be under 1000ms");
    }
}
