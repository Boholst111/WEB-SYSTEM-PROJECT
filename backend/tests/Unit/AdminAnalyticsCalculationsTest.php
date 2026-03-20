<?php

namespace Tests\Unit;

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

/**
 * Test analytics calculations and reporting accuracy.
 * Validates Requirements 1.5, 1.10
 */
class AdminAnalyticsCalculationsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected AnalyticsService $analyticsService;
    protected User $user;
    protected Product $product;
    protected Brand $brand;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyticsService = new AnalyticsService();
        
        // Create test data
        $this->user = User::factory()->create();
        $this->brand = Brand::factory()->create();
        $this->category = Category::factory()->create();
        
        $this->product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'current_price' => 1000.00,
            'stock_quantity' => 100,
        ]);
    }

    /** @test */
    public function revenue_metrics_calculate_correctly_with_discounts_and_credits()
    {
        // Create orders with various discount and credit scenarios
        $order1 = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'subtotal' => 2000.00,
            'discount_amount' => 300.00,
            'credits_used' => 150.00,
            'shipping_fee' => 200.00,
            'total_amount' => 1750.00, // 2000 - 300 - 150 + 200
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

        // Unpaid order should not be included
        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'pending',
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(2),
        ]);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getSalesAnalytics($dateFrom, $dateTo);
        $revenueMetrics = $result['revenue_metrics'];

        // Verify calculations
        $this->assertEquals(3350.00, $revenueMetrics['total_revenue']); // 1750 + 1600
        $this->assertEquals(3500.00, $revenueMetrics['gross_revenue']); // 2000 + 1500
        $this->assertEquals(300.00, $revenueMetrics['discount_amount']); // 300 + 0
        $this->assertEquals(150.00, $revenueMetrics['credits_used']); // 150 + 0
        $this->assertEquals(300.00, $revenueMetrics['shipping_revenue']); // 200 + 100
        $this->assertEquals(2900.00, $revenueMetrics['net_revenue']); // 3350 - 300 - 150
    }

    /** @test */
    public function conversion_rate_calculates_correctly()
    {
        // Create 10 total orders
        Order::factory()->count(6)->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(3),
        ]);

        Order::factory()->count(4)->create([
            'user_id' => $this->user->id,
            'payment_status' => 'pending',
            'created_at' => now()->subDays(3),
        ]);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getSalesAnalytics($dateFrom, $dateTo);
        $orderMetrics = $result['order_metrics'];

        $this->assertEquals(10, $orderMetrics['total_orders']);
        $this->assertEquals(6, $orderMetrics['paid_orders']);
        $this->assertEquals(60.00, $orderMetrics['conversion_rate']); // 6/10 * 100
    }

    /** @test */
    public function product_performance_ranks_by_quantity_sold()
    {
        $product2 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => 'Product 2',
        ]);

        $product3 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => 'Product 3',
        ]);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(2),
        ]);

        // Product 1: 10 units sold
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 1000.00,
        ]);

        // Product 2: 15 units sold (should be #1)
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 15,
            'unit_price' => 800.00,
        ]);

        // Product 3: 5 units sold
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product3->id,
            'quantity' => 5,
            'unit_price' => 1200.00,
        ]);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getProductAnalytics($dateFrom, $dateTo, 10);
        $bestSellers = $result['best_sellers'];

        $this->assertCount(3, $bestSellers);
        
        // Verify ranking by quantity sold
        $this->assertEquals(15, $bestSellers[0]['total_sold']); // Product 2
        $this->assertEquals(10, $bestSellers[1]['total_sold']); // Product 1
        $this->assertEquals(5, $bestSellers[2]['total_sold']); // Product 3
        
        // Verify revenue calculations
        $this->assertEquals(12000.00, $bestSellers[0]['total_revenue']); // 15 * 800
        $this->assertEquals(10000.00, $bestSellers[1]['total_revenue']); // 10 * 1000
        $this->assertEquals(6000.00, $bestSellers[2]['total_revenue']); // 5 * 1200
    }

    /** @test */
    public function inventory_turnover_calculates_correctly()
    {
        // Set specific stock quantities
        $this->product->update([
            'stock_quantity' => 20,
            'is_preorder' => false,
            'status' => 'active',
        ]);
        
        $product2 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'stock_quantity' => 10,
            'current_price' => 500.00,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(2),
        ]);

        // Product 1: 10 units sold, 20 in stock = 0.5 turnover
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 1000.00,
        ]);

        // Product 2: 8 units sold, 10 in stock = 0.8 turnover (higher)
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 8,
            'unit_price' => 500.00,
        ]);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getProductAnalytics($dateFrom, $dateTo, 10);
        $inventoryTurnover = $result['inventory_turnover'];

        $this->assertCount(2, $inventoryTurnover);
        
        // Should be ordered by turnover rate (highest first)
        $this->assertEquals(0.8, $inventoryTurnover[0]['turnover_rate']); // Product 2
        $this->assertEquals(0.5, $inventoryTurnover[1]['turnover_rate']); // Product 1
        
        // Verify inventory values
        $this->assertEquals(5000.00, $inventoryTurnover[0]['inventory_value']); // 10 * 500
        $this->assertEquals(20000.00, $inventoryTurnover[1]['inventory_value']); // 20 * 1000
    }

    /** @test */
    public function category_performance_aggregates_correctly()
    {
        $category2 = Category::factory()->create(['name' => 'Category 2']);
        
        $product2 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $category2->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(2),
        ]);

        // Category 1: 2 items, 15 total quantity, 12000 revenue
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 800.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 800.00,
        ]);

        // Category 2: 1 item, 3 total quantity, 1500 revenue
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 3,
            'unit_price' => 500.00,
        ]);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getProductAnalytics($dateFrom, $dateTo);
        $categoryPerformance = $result['category_performance'];

        $this->assertCount(2, $categoryPerformance);
        
        // Should be ordered by revenue (highest first)
        $category1Data = $categoryPerformance[0];
        $category2Data = $categoryPerformance[1];
        
        $this->assertEquals(12000.00, $category1Data['total_revenue']); // 15 * 800
        $this->assertEquals(15, $category1Data['total_sold']);
        $this->assertEquals(800.00, $category1Data['avg_price']);
        
        $this->assertEquals(1500.00, $category2Data['total_revenue']); // 3 * 500
        $this->assertEquals(3, $category2Data['total_sold']);
        $this->assertEquals(500.00, $category2Data['avg_price']);
    }

    /** @test */
    public function customer_lifetime_value_calculates_accurately()
    {
        // Create customers with different spending patterns
        $highValueUser = User::factory()->create();
        $mediumValueUser = User::factory()->create();
        $lowValueUser = User::factory()->create();

        // High value customer: 3 orders totaling 5000
        Order::factory()->create([
            'user_id' => $highValueUser->id,
            'payment_status' => 'paid',
            'total_amount' => 2000.00,
            'created_at' => now()->subDays(10),
        ]);
        Order::factory()->create([
            'user_id' => $highValueUser->id,
            'payment_status' => 'paid',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(5),
        ]);
        Order::factory()->create([
            'user_id' => $highValueUser->id,
            'payment_status' => 'paid',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(2),
        ]);

        // Medium value customer: 2 orders totaling 2000
        Order::factory()->create([
            'user_id' => $mediumValueUser->id,
            'payment_status' => 'paid',
            'total_amount' => 1200.00,
            'created_at' => now()->subDays(8),
        ]);
        Order::factory()->create([
            'user_id' => $mediumValueUser->id,
            'payment_status' => 'paid',
            'total_amount' => 800.00,
            'created_at' => now()->subDays(3),
        ]);

        // Low value customer: 1 order totaling 500
        Order::factory()->create([
            'user_id' => $lowValueUser->id,
            'payment_status' => 'paid',
            'total_amount' => 500.00,
            'created_at' => now()->subDays(4),
        ]);

        $dateFrom = now()->subWeeks(2)->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getCustomerAnalytics($dateFrom, $dateTo);
        $lifetimeValue = $result['lifetime_value'];

        // Average LTV should be (5000 + 2000 + 500) / 3 = 2500
        $this->assertEquals(2500.00, $lifetimeValue['avg_lifetime_value']);
        
        // Top customers should be ordered by total spent
        $topCustomers = $lifetimeValue['top_customers'];
        $this->assertCount(3, $topCustomers);
        
        $this->assertEquals(5000.00, $topCustomers[0]['orders_sum_total_amount']);
        $this->assertEquals(2000.00, $topCustomers[1]['orders_sum_total_amount']);
        $this->assertEquals(500.00, $topCustomers[2]['orders_sum_total_amount']);
    }

    /** @test */
    public function loyalty_utilization_rate_calculates_correctly()
    {
        // Create loyalty transactions
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => 'earned',
            'amount' => 100.00,
            'created_at' => now()->subDays(10),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => 'earned',
            'amount' => 200.00,
            'created_at' => now()->subDays(8),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => 'redeemed',
            'amount' => -75.00, // Negative for redemption
            'created_at' => now()->subDays(5),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => 'redeemed',
            'amount' => -25.00,
            'created_at' => now()->subDays(2),
        ]);

        $dateFrom = now()->subWeeks(2)->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getCustomerAnalytics($dateFrom, $dateTo);
        $loyaltyAnalysis = $result['loyalty_analysis'];

        $this->assertEquals(300.00, $loyaltyAnalysis['credits_earned']); // 100 + 200
        $this->assertEquals(100.00, $loyaltyAnalysis['credits_redeemed']); // abs(-75 + -25)
        $this->assertEquals(33.33, $loyaltyAnalysis['utilization_rate']); // 100/300 * 100
    }

    /** @test */
    public function growth_comparison_calculates_period_over_period()
    {
        // Current period orders (last 7 days)
        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(3),
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(1),
        ]);

        // Previous period orders (8-14 days ago)
        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 800.00,
            'created_at' => now()->subDays(10),
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 700.00,
            'created_at' => now()->subDays(12),
        ]);

        $dateFrom = now()->subDays(7)->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getSalesAnalytics($dateFrom, $dateTo);
        $growthComparison = $result['growth_comparison'];

        // Current period: 2 orders, 2500 revenue
        $this->assertEquals(2500.00, $growthComparison['current_period']['revenue']);
        $this->assertEquals(2, $growthComparison['current_period']['orders']);

        // Previous period: 2 orders, 1500 revenue
        $this->assertEquals(1500.00, $growthComparison['previous_period']['revenue']);
        $this->assertEquals(2, $growthComparison['previous_period']['orders']);

        // Growth calculations
        $expectedRevenueGrowth = ((2500 - 1500) / 1500) * 100; // 66.67%
        $expectedOrderGrowth = ((2 - 2) / 2) * 100; // 0%

        $this->assertEquals(66.67, $growthComparison['growth']['revenue_growth']);
        $this->assertEquals(0.00, $growthComparison['growth']['order_growth']);
    }

    /** @test */
    public function price_analysis_segments_correctly()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(2),
        ]);

        // Create order items in different price ranges
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 300.00, // Under ₱500
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 750.00, // ₱500-₱999
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_price' => 1500.00, // ₱1000-₱1999
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 3000.00, // ₱2000-₱4999
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 6000.00, // ₱5000+
        ]);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $result = $this->analyticsService->getProductAnalytics($dateFrom, $dateTo);
        $priceAnalysis = $result['price_analysis'];

        $priceRanges = collect($priceAnalysis['price_ranges'])->keyBy('price_range');

        $this->assertEquals(1, $priceRanges['Under ₱500']['count']);
        $this->assertEquals(600.00, $priceRanges['Under ₱500']['revenue']); // 2 * 300

        $this->assertEquals(1, $priceRanges['₱500-₱999']['count']);
        $this->assertEquals(750.00, $priceRanges['₱500-₱999']['revenue']); // 1 * 750

        $this->assertEquals(1, $priceRanges['₱1000-₱1999']['count']);
        $this->assertEquals(4500.00, $priceRanges['₱1000-₱1999']['revenue']); // 3 * 1500

        $this->assertEquals(1, $priceRanges['₱2000-₱4999']['count']);
        $this->assertEquals(3000.00, $priceRanges['₱2000-₱4999']['revenue']); // 1 * 3000

        $this->assertEquals(1, $priceRanges['₱5000+']['count']);
        $this->assertEquals(6000.00, $priceRanges['₱5000+']['revenue']); // 1 * 6000

        // Average selling price should be weighted average
        $expectedAvg = (300 + 750 + 1500 + 3000 + 6000) / 5; // 2310
        $this->assertEquals($expectedAvg, $priceAnalysis['avg_selling_price']);
    }
}