<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AnalyticsService;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\LoyaltyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected AnalyticsService $analyticsService;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyticsService = new AnalyticsService();
        
        // Create test data
        $this->user = User::factory()->create();
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        
        $this->product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 1000.00,
            'stock_quantity' => 100,
        ]);
    }

    /** @test */
    public function it_calculates_sales_analytics_correctly()
    {
        // Create test orders
        $order1 = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'subtotal' => 2000.00,
            'discount_amount' => 200.00,
            'credits_used' => 100.00,
            'shipping_fee' => 150.00,
            'total_amount' => 1850.00,
            'created_at' => now()->subDays(5),
        ]);

        $order2 = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'subtotal' => 1500.00,
            'discount_amount' => 0.00,
            'credits_used' => 0.00,
            'shipping_fee' => 100.00,
            'total_amount' => 1600.00,
            'created_at' => now()->subDays(3),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 1000.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 1500.00,
        ]);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getSalesAnalytics($dateFrom, $dateTo);

        $this->assertArrayHasKey('revenue_metrics', $result);
        $this->assertArrayHasKey('order_metrics', $result);
        $this->assertArrayHasKey('payment_analytics', $result);
        $this->assertArrayHasKey('time_series', $result);
        $this->assertArrayHasKey('growth_comparison', $result);

        // Check revenue metrics
        $revenueMetrics = $result['revenue_metrics'];
        $this->assertEquals(3450.00, $revenueMetrics['total_revenue']); // 1850 + 1600
        $this->assertEquals(3500.00, $revenueMetrics['gross_revenue']); // 2000 + 1500
        $this->assertEquals(200.00, $revenueMetrics['discount_amount']);
        $this->assertEquals(100.00, $revenueMetrics['credits_used']);
        $this->assertEquals(250.00, $revenueMetrics['shipping_revenue']); // 150 + 100

        // Check order metrics
        $orderMetrics = $result['order_metrics'];
        $this->assertEquals(2, $orderMetrics['total_orders']);
        $this->assertEquals(2, $orderMetrics['paid_orders']);
        $this->assertEquals(1725.00, $orderMetrics['average_order_value']); // 3450 / 2
        $this->assertEquals(100.00, $orderMetrics['conversion_rate']); // 2/2 * 100
    }

    /** @test */
    public function it_calculates_product_analytics_correctly()
    {
        // Create orders with different products
        $product2 = Product::factory()->create([
            'brand_id' => $this->product->brand_id,
            'category_id' => $this->product->category_id,
            'current_price' => 500.00,
            'stock_quantity' => 50,
        ]);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(2),
        ]);

        // Product 1: 5 units sold
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 1000.00,
        ]);

        // Product 2: 2 units sold
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 2,
            'unit_price' => 500.00,
        ]);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getProductAnalytics($dateFrom, $dateTo, 10);

        $this->assertArrayHasKey('best_sellers', $result);
        $this->assertArrayHasKey('slow_movers', $result);
        $this->assertArrayHasKey('inventory_turnover', $result);
        $this->assertArrayHasKey('category_performance', $result);
        $this->assertArrayHasKey('brand_performance', $result);
        $this->assertArrayHasKey('price_analysis', $result);

        // Check best sellers (should be ordered by quantity sold)
        $bestSellers = $result['best_sellers'];
        $this->assertCount(2, $bestSellers);
        $this->assertEquals(5, $bestSellers[0]['total_sold']); // Product 1
        $this->assertEquals(2, $bestSellers[1]['total_sold']); // Product 2
    }

    /** @test */
    public function it_calculates_customer_analytics_correctly()
    {
        // Create additional users
        $newUser = User::factory()->create(['created_at' => now()->subDays(5)]);
        $oldUser = User::factory()->create(['created_at' => now()->subMonths(2)]);

        // Create orders for different users
        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(3),
        ]);

        Order::factory()->create([
            'user_id' => $newUser->id,
            'payment_status' => 'paid',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(2),
        ]);

        // Old user with previous order (returning customer)
        Order::factory()->create([
            'user_id' => $oldUser->id,
            'payment_status' => 'paid',
            'total_amount' => 800.00,
            'created_at' => now()->subMonths(3),
        ]);

        Order::factory()->create([
            'user_id' => $oldUser->id,
            'payment_status' => 'paid',
            'total_amount' => 1200.00,
            'created_at' => now()->subDays(1),
        ]);

        // Create loyalty transactions
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => 'earned',
            'amount' => 50.00,
            'created_at' => now()->subDays(3),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => 'redeemed',
            'amount' => -25.00,
            'created_at' => now()->subDays(2),
        ]);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getCustomerAnalytics($dateFrom, $dateTo);

        $this->assertArrayHasKey('acquisition_metrics', $result);
        $this->assertArrayHasKey('retention_metrics', $result);
        $this->assertArrayHasKey('lifetime_value', $result);
        $this->assertArrayHasKey('loyalty_analysis', $result);
        $this->assertArrayHasKey('segmentation', $result);

        // Check acquisition metrics
        $acquisitionMetrics = $result['acquisition_metrics'];
        $this->assertEquals(1, $acquisitionMetrics['new_customers']); // Only newUser created in date range
        $this->assertGreaterThan(0, $acquisitionMetrics['total_customers']);

        // Check retention metrics
        $retentionMetrics = $result['retention_metrics'];
        $this->assertEquals(1, $retentionMetrics['returning_customers']); // oldUser

        // Check loyalty analysis
        $loyaltyAnalysis = $result['loyalty_analysis'];
        $this->assertEquals(50.00, $loyaltyAnalysis['credits_earned']);
        $this->assertEquals(25.00, $loyaltyAnalysis['credits_redeemed']);
        $this->assertEquals(50.00, $loyaltyAnalysis['utilization_rate']); // 25/50 * 100
    }

    /** @test */
    public function it_handles_empty_data_gracefully()
    {
        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $salesResult = $this->analyticsService->getSalesAnalytics($dateFrom, $dateTo);
        $productResult = $this->analyticsService->getProductAnalytics($dateFrom, $dateTo);
        $customerResult = $this->analyticsService->getCustomerAnalytics($dateFrom, $dateTo);

        // Sales analytics should return zero values
        $this->assertEquals(0, $salesResult['revenue_metrics']['total_revenue']);
        $this->assertEquals(0, $salesResult['order_metrics']['total_orders']);

        // Product analytics should return empty arrays
        $this->assertIsArray($productResult['best_sellers']);
        $this->assertIsArray($productResult['category_performance']);

        // Customer analytics should return zero values
        $this->assertEquals(0, $customerResult['acquisition_metrics']['new_customers']);
        $this->assertEquals(0, $customerResult['loyalty_analysis']['credits_earned']);
    }

    /** @test */
    public function it_calculates_growth_comparison_correctly()
    {
        // Create orders in current period
        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(2),
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(1),
        ]);

        // Create orders in previous period
        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 800.00,
            'created_at' => now()->subDays(10),
        ]);

        $dateFrom = now()->subDays(7)->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getSalesAnalytics($dateFrom, $dateTo);
        $growthComparison = $result['growth_comparison'];

        $this->assertArrayHasKey('current_period', $growthComparison);
        $this->assertArrayHasKey('previous_period', $growthComparison);
        $this->assertArrayHasKey('growth', $growthComparison);

        // Current period should have 2 orders totaling 2500
        $this->assertEquals(2500.00, $growthComparison['current_period']['revenue']);
        $this->assertEquals(2, $growthComparison['current_period']['orders']);

        // Previous period should have 1 order totaling 800
        $this->assertEquals(800.00, $growthComparison['previous_period']['revenue']);
        $this->assertEquals(1, $growthComparison['previous_period']['orders']);

        // Growth should be positive
        $this->assertGreaterThan(0, $growthComparison['growth']['revenue_growth']);
        $this->assertGreaterThan(0, $growthComparison['growth']['order_growth']);
    }

    /** @test */
    public function it_generates_time_series_data_correctly()
    {
        // Create orders on different days
        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(3)->startOfDay(),
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(2)->startOfDay(),
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 800.00,
            'created_at' => now()->subDays(2)->startOfDay()->addHours(5),
        ]);

        $dateFrom = now()->subDays(7)->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getSalesAnalytics($dateFrom, $dateTo, 'daily');
        $timeSeries = $result['time_series'];

        $this->assertIsArray($timeSeries);
        $this->assertGreaterThan(0, count($timeSeries));

        // Check that time series contains expected data structure
        foreach ($timeSeries as $dataPoint) {
            $this->assertArrayHasKey('period', $dataPoint);
            $this->assertArrayHasKey('revenue', $dataPoint);
            $this->assertArrayHasKey('orders', $dataPoint);
            $this->assertArrayHasKey('avg_order_value', $dataPoint);
        }
    }

    /** @test */
    public function it_segments_customers_correctly()
    {
        // Create users with different order patterns
        $vipUser = User::factory()->create();
        $loyalUser = User::factory()->create();
        $regularUser = User::factory()->create();
        $newUser = User::factory()->create();

        $dateFrom = now()->subMonth()->toDateString();
        $dateTo = now()->toDateString();

        // VIP user: 5+ orders, 10000+ spent
        for ($i = 0; $i < 6; $i++) {
            Order::factory()->create([
                'user_id' => $vipUser->id,
                'payment_status' => 'paid',
                'total_amount' => 2000.00,
                'created_at' => now()->subDays($i + 1), // Ensure within date range
            ]);
        }

        // Loyal user: 3+ orders, 5000+ spent
        for ($i = 0; $i < 4; $i++) {
            Order::factory()->create([
                'user_id' => $loyalUser->id,
                'payment_status' => 'paid',
                'total_amount' => 1500.00,
                'created_at' => now()->subDays($i + 1), // Ensure within date range
            ]);
        }

        // Regular user: 2+ orders
        for ($i = 0; $i < 2; $i++) {
            Order::factory()->create([
                'user_id' => $regularUser->id,
                'payment_status' => 'paid',
                'total_amount' => 800.00,
                'created_at' => now()->subDays($i + 1), // Ensure within date range
            ]);
        }

        // New user: 1 order
        Order::factory()->create([
            'user_id' => $newUser->id,
            'payment_status' => 'paid',
            'total_amount' => 500.00,
            'created_at' => now()->subDays(5),
        ]);

        $result = $this->analyticsService->getCustomerAnalytics($dateFrom, $dateTo);
        $segmentation = $result['segmentation'];

        $this->assertArrayHasKey('segments', $segmentation);
        $segments = $segmentation['segments'];

        $this->assertArrayHasKey('VIP', $segments);
        $this->assertArrayHasKey('Loyal', $segments);
        $this->assertArrayHasKey('Regular', $segments);
        $this->assertArrayHasKey('New', $segments);

        $this->assertEquals(1, $segments['VIP']);
        $this->assertEquals(1, $segments['Loyal']);
        $this->assertEquals(1, $segments['Regular']);
        $this->assertEquals(1, $segments['New']);
    }
}