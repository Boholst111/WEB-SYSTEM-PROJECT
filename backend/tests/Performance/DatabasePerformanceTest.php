<?php

namespace Tests\Performance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Order;
use App\Models\User;
use App\Models\LoyaltyTransaction;

/**
 * Database Performance Benchmarking
 * 
 * Validates: Requirements 1.2
 * Tests database query performance and optimization
 */
class DatabasePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed test data for realistic performance testing
        $this->seedLargeDataset();
    }

    /**
     * Seed a large dataset for performance testing
     */
    private function seedLargeDataset(): void
    {
        // Create categories
        $categories = Category::factory()->count(20)->create();
        
        // Create brands with unique names and slugs
        $brands = collect();
        for ($i = 0; $i < 50; $i++) {
            $uniqueId = uniqid();
            $brands->push(Brand::factory()->create([
                'name' => 'Brand Perf ' . $i . ' ' . $uniqueId,
                'slug' => 'brand-perf-' . $i . '-' . $uniqueId,
            ]));
        }
        
        // Create 2000 products for realistic testing
        Product::factory()->count(2000)->create([
            'category_id' => $categories->random()->id,
            'brand_id' => $brands->random()->id,
        ]);
        
        // Create users with orders and loyalty transactions
        $users = User::factory()->count(100)->create();
        
        foreach ($users->random(50) as $user) {
            Order::factory()->count(rand(1, 5))->create([
                'user_id' => $user->id,
            ]);
            
            LoyaltyTransaction::factory()->count(rand(2, 10))->create([
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * Test: Product catalog query performance
     * 
     * Measures query time for paginated product listing
     */
    public function test_product_catalog_query_performance(): void
    {
        $iterations = 10;
        $totalTime = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            
            $products = Product::with(['category', 'brand'])
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            
            $endTime = microtime(true);
            $totalTime += ($endTime - $startTime) * 1000;
        }
        
        $avgQueryTime = $totalTime / $iterations;
        
        // Assert query time is under 100ms
        $this->assertLessThan(100, $avgQueryTime,
            "Product catalog query time {$avgQueryTime}ms exceeds 100ms threshold");
        
        echo "\n[DB Performance] Product Catalog Query: avg " . 
             number_format($avgQueryTime, 2) . "ms over {$iterations} iterations\n";
    }

    /**
     * Test: Complex filtering query performance
     * 
     * Measures performance of multi-dimensional product filtering
     */
    public function test_complex_filtering_query_performance(): void
    {
        $iterations = 10;
        $totalTime = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            
            $products = Product::with(['category', 'brand'])
                ->where('status', 'active')
                ->where('scale', '1:64')
                ->where('material', 'diecast')
                ->where('stock_quantity', '>', 0)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            
            $endTime = microtime(true);
            $totalTime += ($endTime - $startTime) * 1000;
        }
        
        $avgQueryTime = $totalTime / $iterations;
        
        // Assert query time is under 150ms for complex filters
        $this->assertLessThan(150, $avgQueryTime,
            "Complex filtering query time {$avgQueryTime}ms exceeds 150ms threshold");
        
        echo "\n[DB Performance] Complex Filtering Query: avg " . 
             number_format($avgQueryTime, 2) . "ms over {$iterations} iterations\n";
    }

    /**
     * Test: Product search query performance
     * 
     * Measures full-text search performance
     */
    public function test_product_search_query_performance(): void
    {
        $iterations = 10;
        $totalTime = 0;
        $searchTerms = ['hot wheels', 'ferrari', 'chase', 'limited edition', 'diecast'];
        
        for ($i = 0; $i < $iterations; $i++) {
            $searchTerm = $searchTerms[$i % count($searchTerms)];
            $startTime = microtime(true);
            
            $products = Product::where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                ->with(['category', 'brand'])
                ->limit(20)
                ->get();
            
            $endTime = microtime(true);
            $totalTime += ($endTime - $startTime) * 1000;
        }
        
        $avgQueryTime = $totalTime / $iterations;
        
        // Assert search query time is under 200ms
        $this->assertLessThan(200, $avgQueryTime,
            "Product search query time {$avgQueryTime}ms exceeds 200ms threshold");
        
        echo "\n[DB Performance] Product Search Query: avg " . 
             number_format($avgQueryTime, 2) . "ms over {$iterations} iterations\n";
    }

    /**
     * Test: Order history query performance
     * 
     * Measures user order history retrieval performance
     */
    public function test_order_history_query_performance(): void
    {
        $users = User::has('orders')->limit(10)->get();
        $iterations = count($users);
        $totalTime = 0;
        
        foreach ($users as $user) {
            $startTime = microtime(true);
            
            $orders = Order::where('user_id', $user->id)
                ->with(['items.product'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
            
            $endTime = microtime(true);
            $totalTime += ($endTime - $startTime) * 1000;
        }
        
        $avgQueryTime = $totalTime / $iterations;
        
        // Assert order history query time is under 100ms
        $this->assertLessThan(100, $avgQueryTime,
            "Order history query time {$avgQueryTime}ms exceeds 100ms threshold");
        
        echo "\n[DB Performance] Order History Query: avg " . 
             number_format($avgQueryTime, 2) . "ms over {$iterations} iterations\n";
    }

    /**
     * Test: Loyalty transaction ledger query performance
     * 
     * Measures loyalty credits calculation performance
     */
    public function test_loyalty_ledger_query_performance(): void
    {
        $users = User::has('loyaltyTransactions')->limit(10)->get();
        $iterations = count($users);
        $totalTime = 0;
        
        foreach ($users as $user) {
            $startTime = microtime(true);
            
            $transactions = LoyaltyTransaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            
            $balance = LoyaltyTransaction::where('user_id', $user->id)
                ->selectRaw('SUM(CASE WHEN transaction_type = "earned" THEN amount ELSE -amount END) as balance')
                ->value('balance');
            
            $endTime = microtime(true);
            $totalTime += ($endTime - $startTime) * 1000;
        }
        
        $avgQueryTime = $totalTime / $iterations;
        
        // Assert loyalty ledger query time is under 100ms
        $this->assertLessThan(100, $avgQueryTime,
            "Loyalty ledger query time {$avgQueryTime}ms exceeds 100ms threshold");
        
        echo "\n[DB Performance] Loyalty Ledger Query: avg " . 
             number_format($avgQueryTime, 2) . "ms over {$iterations} iterations\n";
    }

    /**
     * Test: Database connection pool performance
     * 
     * Measures connection handling under load
     */
    public function test_database_connection_pool_performance(): void
    {
        $iterations = 50;
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate multiple concurrent queries
            DB::table('products')->where('status', 'active')->count();
            DB::table('users')->where('status', 'active')->count();
            DB::table('orders')->where('status', 'pending')->count();
        }
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $avgTime = $totalTime / $iterations;
        
        // Assert connection pool handles requests efficiently (under 50ms per batch)
        $this->assertLessThan(50, $avgTime,
            "Connection pool performance {$avgTime}ms exceeds 50ms threshold");
        
        echo "\n[DB Performance] Connection Pool: {$iterations} query batches in " . 
             number_format($totalTime, 2) . "ms (avg: " . 
             number_format($avgTime, 2) . "ms per batch)\n";
    }

    /**
     * Test: Index effectiveness
     * 
     * Verifies that database indexes are being used effectively
     */
    public function test_index_effectiveness(): void
    {
        // Skip this test for SQLite as EXPLAIN output differs
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('Index effectiveness test requires MySQL/PostgreSQL');
        }
        
        // Test that indexes are used for common queries
        $explain = DB::select('EXPLAIN SELECT * FROM products WHERE status = ? AND scale = ?', ['active', '1:64']);
        
        // Check that the query uses an index (type should not be ALL)
        $this->assertNotEquals('ALL', $explain[0]->type,
            "Query is not using indexes efficiently (full table scan detected)");
        
        echo "\n[DB Performance] Index Usage: Query type = {$explain[0]->type}, " .
             "Key = " . ($explain[0]->key ?? 'none') . "\n";
    }

    /**
     * Test: Aggregate query performance
     * 
     * Measures performance of analytics and reporting queries
     */
    public function test_aggregate_query_performance(): void
    {
        $startTime = microtime(true);
        
        // Simulate analytics queries
        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $totalOrders = Order::count();
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total_amount');
        $avgOrderValue = Order::where('payment_status', 'paid')->avg('total_amount');
        
        $endTime = microtime(true);
        $queryTime = ($endTime - $startTime) * 1000;
        
        // Assert aggregate queries complete under 200ms
        $this->assertLessThan(200, $queryTime,
            "Aggregate query time {$queryTime}ms exceeds 200ms threshold");
        
        echo "\n[DB Performance] Aggregate Queries: " . 
             number_format($queryTime, 2) . "ms for 5 aggregate operations\n";
    }
}
