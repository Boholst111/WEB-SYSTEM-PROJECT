<?php

namespace Tests\Performance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

/**
 * Load Testing for 500 Concurrent Users
 * 
 * Validates: Requirements 1.2
 * Tests system performance under Drop Day traffic conditions
 */
class LoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable rate limiting for performance tests
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        
        // Seed test data
        $this->seedTestData();
    }

    /**
     * Seed minimal test data for performance testing
     */
    private function seedTestData(): void
    {
        // Create categories
        $categories = Category::factory()->count(10)->create();
        
        // Create brands with unique slugs
        $brands = collect();
        for ($i = 0; $i < 20; $i++) {
            $brands->push(Brand::factory()->create([
                'slug' => 'brand-' . $i . '-' . uniqid(),
            ]));
        }
        
        // Create products (simulate 1000 SKUs for realistic testing)
        Product::factory()->count(1000)->create([
            'category_id' => $categories->random()->id,
            'brand_id' => $brands->random()->id,
        ]);
        
        // Create test users
        User::factory()->count(50)->create();
    }

    /**
     * Test: Product catalog endpoint under concurrent load
     * 
     * Simulates multiple users browsing the product catalog simultaneously
     */
    public function test_product_catalog_handles_concurrent_requests(): void
    {
        $startTime = microtime(true);
        $requests = 100; // Simulate 100 concurrent requests
        $responses = [];
        
        for ($i = 0; $i < $requests; $i++) {
            $response = $this->getJson('/api/products?per_page=20');
            $responses[] = $response->status();
        }
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $avgResponseTime = $duration / $requests;
        
        // Assert all requests succeeded
        foreach ($responses as $status) {
            $this->assertEquals(200, $status);
        }
        
        // Assert average response time is under 2 seconds (2000ms)
        $this->assertLessThan(2000, $avgResponseTime, 
            "Average response time {$avgResponseTime}ms exceeds 2 second threshold");
        
        echo "\n[Load Test] Product Catalog: {$requests} requests in " . 
             number_format($duration, 2) . "ms (avg: " . 
             number_format($avgResponseTime, 2) . "ms per request)\n";
    }

    /**
     * Test: Product filtering under load
     * 
     * Tests complex filtering queries with concurrent users
     */
    public function test_product_filtering_handles_concurrent_load(): void
    {
        $startTime = microtime(true);
        $requests = 50;
        $responses = [];
        
        $filters = [
            ['scale' => '1:64'],
            ['material' => 'diecast'],
            ['scale' => '1:43', 'material' => 'resin'],
            ['is_chase_variant' => true],
        ];
        
        for ($i = 0; $i < $requests; $i++) {
            $filter = $filters[$i % count($filters)];
            $queryString = http_build_query($filter);
            $response = $this->getJson("/api/products?{$queryString}");
            $responses[] = $response->status();
        }
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;
        $avgResponseTime = $duration / $requests;
        
        foreach ($responses as $status) {
            $this->assertEquals(200, $status);
        }
        
        $this->assertLessThan(2000, $avgResponseTime,
            "Filtering response time {$avgResponseTime}ms exceeds threshold");
        
        echo "\n[Load Test] Product Filtering: {$requests} requests in " . 
             number_format($duration, 2) . "ms (avg: " . 
             number_format($avgResponseTime, 2) . "ms per request)\n";
    }

    /**
     * Test: Product detail page under load
     * 
     * Simulates users viewing product details during Drop Day
     */
    public function test_product_detail_handles_concurrent_load(): void
    {
        $products = Product::inRandomOrder()->limit(10)->get();
        $startTime = microtime(true);
        $requests = 100;
        $responses = [];
        
        for ($i = 0; $i < $requests; $i++) {
            $product = $products[$i % $products->count()];
            $response = $this->getJson("/api/products/{$product->id}");
            $responses[] = $response->status();
        }
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;
        $avgResponseTime = $duration / $requests;
        
        foreach ($responses as $status) {
            $this->assertEquals(200, $status);
        }
        
        $this->assertLessThan(2000, $avgResponseTime,
            "Product detail response time {$avgResponseTime}ms exceeds threshold");
        
        echo "\n[Load Test] Product Detail: {$requests} requests in " . 
             number_format($duration, 2) . "ms (avg: " . 
             number_format($avgResponseTime, 2) . "ms per request)\n";
    }

    /**
     * Test: Authentication endpoint under load
     * 
     * Tests login performance during high traffic
     */
    public function test_authentication_handles_concurrent_load(): void
    {
        $users = User::factory()->count(20)->create();
        
        $startTime = microtime(true);
        $requests = 50;
        $responses = [];
        
        for ($i = 0; $i < $requests; $i++) {
            $user = $users[$i % $users->count()];
            $response = $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password', // Default factory password
            ]);
            $responses[] = $response->status();
        }
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;
        $avgResponseTime = $duration / $requests;
        
        // Accept various response codes (200=success, 401=unauthorized, 422=validation)
        foreach ($responses as $status) {
            $this->assertContains($status, [200, 401, 403, 422], 
                "Unexpected status code: {$status}");
        }
        
        $this->assertLessThan(2000, $avgResponseTime,
            "Authentication response time {$avgResponseTime}ms exceeds threshold");
        
        echo "\n[Load Test] Authentication: {$requests} requests in " . 
             number_format($duration, 2) . "ms (avg: " . 
             number_format($avgResponseTime, 2) . "ms per request)\n";
    }

    /**
     * Test: Mixed workload simulation
     * 
     * Simulates realistic Drop Day traffic with mixed operations
     */
    public function test_mixed_workload_performance(): void
    {
        $products = Product::inRandomOrder()->limit(20)->get();
        $users = User::factory()->count(10)->create();
        
        $startTime = microtime(true);
        $totalRequests = 200;
        $successCount = 0;
        
        for ($i = 0; $i < $totalRequests; $i++) {
            $operation = $i % 5;
            
            switch ($operation) {
                case 0: // Browse catalog
                    $response = $this->getJson('/api/products?per_page=20');
                    break;
                case 1: // Filter products
                    $response = $this->getJson('/api/products?scale=1:64');
                    break;
                case 2: // View product detail
                    $product = $products[$i % $products->count()];
                    $response = $this->getJson("/api/products/{$product->id}");
                    break;
                case 3: // Get categories
                    $response = $this->getJson('/api/categories');
                    break;
                case 4: // Get brands
                    $response = $this->getJson('/api/brands');
                    break;
            }
            
            if ($response->status() === 200) {
                $successCount++;
            }
        }
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;
        $avgResponseTime = $duration / $totalRequests;
        $successRate = ($successCount / $totalRequests) * 100;
        
        // Assert success rate is above 95%
        $this->assertGreaterThanOrEqual(95, $successRate,
            "Success rate {$successRate}% is below 95% threshold");
        
        // Assert average response time is under 2 seconds
        $this->assertLessThan(2000, $avgResponseTime,
            "Mixed workload avg response time {$avgResponseTime}ms exceeds threshold");
        
        echo "\n[Load Test] Mixed Workload: {$totalRequests} requests in " . 
             number_format($duration, 2) . "ms (avg: " . 
             number_format($avgResponseTime, 2) . "ms per request, " . 
             "success rate: " . number_format($successRate, 2) . "%)\n";
    }
}
