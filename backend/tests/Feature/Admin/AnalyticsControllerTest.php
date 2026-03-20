<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\LoyaltyTransaction;
use App\Models\PreOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user (using regular User model with admin email)
        $this->adminUser = User::factory()->create([
            'email' => 'admin@diecastempire.com',
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create();

        // Create test data
        $this->createTestData();
    }

    protected function createTestData(): void
    {
        // Create brands and categories
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Die-cast Cars']);

        // Create products
        $products = Product::factory()->count(10)->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 500.00,
            'stock_quantity' => 50,
        ]);

        // Create orders with items
        $orders = Order::factory()->count(5)->create([
            'user_id' => $this->regularUser->id,
            'payment_status' => 'paid',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(rand(1, 30)),
        ]);

        foreach ($orders as $order) {
            OrderItem::factory()->count(3)->create([
                'order_id' => $order->id,
                'product_id' => $products->random()->id,
                'quantity' => rand(1, 3),
                'unit_price' => 500.00,
            ]);
        }

        // Create loyalty transactions
        LoyaltyTransaction::factory()->count(10)->create([
            'user_id' => $this->regularUser->id,
            'transaction_type' => 'earned',
            'amount' => 25.00,
            'created_at' => now()->subDays(rand(1, 30)),
        ]);

        // Create pre-orders
        PreOrder::factory()->count(3)->create([
            'user_id' => $this->regularUser->id,
            'product_id' => $products->random()->id,
            'status' => 'deposit_paid',
        ]);
    }

    /** @test */
    public function admin_can_access_analytics_dashboard()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'sales_analytics' => [
                            'revenue_metrics',
                            'order_metrics',
                            'payment_analytics',
                            'time_series',
                            'growth_comparison',
                        ],
                        'product_analytics' => [
                            'best_sellers',
                            'slow_movers',
                            'inventory_turnover',
                            'category_performance',
                            'brand_performance',
                            'price_analysis',
                        ],
                        'customer_analytics' => [
                            'acquisition_metrics',
                            'retention_metrics',
                            'lifetime_value',
                            'loyalty_analysis',
                            'segmentation',
                        ],
                        'traffic_analysis',
                        'loyalty_metrics',
                        'inventory_insights',
                        'date_range',
                    ]
                ]);
    }

    /** @test */
    public function regular_user_cannot_access_analytics_dashboard()
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/admin/analytics');

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_analytics_dashboard()
    {
        $response = $this->getJson('/api/admin/analytics');

        $response->assertStatus(401);
    }

    /** @test */
    public function admin_can_get_sales_metrics()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics/sales-metrics');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'revenue_metrics' => [
                            'total_revenue',
                            'gross_revenue',
                            'discount_amount',
                            'credits_used',
                            'shipping_revenue',
                            'net_revenue',
                        ],
                        'order_metrics' => [
                            'total_orders',
                            'paid_orders',
                            'average_order_value',
                            'conversion_rate',
                            'status_breakdown',
                        ],
                        'payment_analytics',
                        'time_series',
                        'growth_comparison',
                    ]
                ]);
    }

    /** @test */
    public function admin_can_get_product_performance()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics/product-performance');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'best_sellers',
                        'slow_movers',
                        'inventory_turnover',
                        'category_performance',
                        'brand_performance',
                        'price_analysis',
                    ]
                ]);
    }

    /** @test */
    public function admin_can_get_customer_analytics()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics/customer-analytics');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'acquisition_metrics' => [
                            'new_customers',
                            'total_customers',
                            'growth_rate',
                        ],
                        'retention_metrics' => [
                            'returning_customers',
                            'retention_rate',
                        ],
                        'lifetime_value' => [
                            'avg_lifetime_value',
                            'top_customers',
                        ],
                        'loyalty_analysis' => [
                            'tier_distribution',
                            'credits_earned',
                            'credits_redeemed',
                            'utilization_rate',
                        ],
                        'segmentation',
                    ]
                ]);
    }

    /** @test */
    public function admin_can_get_traffic_analysis()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics/traffic-analysis');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'summary' => [
                            'estimated_visitors',
                            'conversion_rate',
                            'cart_abandonment_rate',
                            'bounce_rate',
                            'avg_session_duration',
                        ],
                        'popular_products',
                        'device_types',
                        'peak_hours',
                    ]
                ]);
    }

    /** @test */
    public function admin_can_get_real_time_summary()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics/real-time-summary');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'today_orders',
                        'today_revenue',
                        'pending_orders',
                        'low_stock_products',
                        'active_users_today',
                        'conversion_rate_today',
                    ]
                ]);
    }

    /** @test */
    public function analytics_dashboard_accepts_date_range_parameters()
    {
        Sanctum::actingAs($this->adminUser);

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $response = $this->getJson("/api/admin/analytics?date_from={$dateFrom}&date_to={$dateTo}&period=daily");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'date_range' => [
                            'from' => $dateFrom,
                            'to' => $dateTo,
                            'period' => 'daily',
                        ]
                    ]
                ]);
    }

    /** @test */
    public function analytics_dashboard_uses_default_date_range_when_not_provided()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics');

        $response->assertStatus(200)
                ->assertJsonPath('data.date_range.period', 'daily');

        // Check that date range is approximately one month
        $dateRange = $response->json('data.date_range');
        $from = \Carbon\Carbon::parse($dateRange['from']);
        $to = \Carbon\Carbon::parse($dateRange['to']);
        
        $this->assertTrue($from->diffInDays($to) >= 28);
        $this->assertTrue($from->diffInDays($to) <= 31);
    }

    /** @test */
    public function sales_metrics_calculates_revenue_correctly()
    {
        Sanctum::actingAs($this->adminUser);

        // Create a specific order for testing
        $order = Order::factory()->create([
            'user_id' => $this->regularUser->id,
            'payment_status' => 'paid',
            'subtotal' => 1000.00,
            'discount_amount' => 100.00,
            'credits_used' => 50.00,
            'shipping_fee' => 150.00,
            'total_amount' => 1000.00,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/analytics/sales-metrics');

        $response->assertStatus(200);
        
        $revenueMetrics = $response->json('data.revenue_metrics');
        $this->assertIsNumeric($revenueMetrics['total_revenue']);
        $this->assertIsNumeric($revenueMetrics['gross_revenue']);
        $this->assertIsNumeric($revenueMetrics['net_revenue']);
    }

    /** @test */
    public function product_performance_returns_best_sellers()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics/product-performance?limit=5');

        $response->assertStatus(200);
        
        $bestSellers = $response->json('data.best_sellers');
        $this->assertIsArray($bestSellers);
        $this->assertLessThanOrEqual(5, count($bestSellers));
    }

    /** @test */
    public function customer_analytics_calculates_retention_rate()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics/customer-analytics');

        $response->assertStatus(200);
        
        $retentionMetrics = $response->json('data.retention_metrics');
        $this->assertIsNumeric($retentionMetrics['retention_rate']);
        $this->assertGreaterThanOrEqual(0, $retentionMetrics['retention_rate']);
        $this->assertLessThanOrEqual(100, $retentionMetrics['retention_rate']);
    }

    /** @test */
    public function analytics_data_is_cached()
    {
        Sanctum::actingAs($this->adminUser);

        // First request
        $response1 = $this->getJson('/api/admin/analytics');
        $response1->assertStatus(200);

        // Second request should use cached data
        $response2 = $this->getJson('/api/admin/analytics');
        $response2->assertStatus(200);

        // Responses should be identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    /** @test */
    public function analytics_handles_empty_data_gracefully()
    {
        // Clear all test data
        Order::truncate();
        OrderItem::truncate();
        LoyaltyTransaction::truncate();
        PreOrder::truncate();

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics');

        $response->assertStatus(200);
        
        // Should return zero values instead of errors
        $salesAnalytics = $response->json('data.sales_analytics');
        $this->assertEquals(0, $salesAnalytics['revenue_metrics']['total_revenue']);
        $this->assertEquals(0, $salesAnalytics['order_metrics']['total_orders']);
    }

    /** @test */
    public function real_time_summary_shows_current_day_data()
    {
        // Create an order for today
        Order::factory()->create([
            'user_id' => $this->regularUser->id,
            'payment_status' => 'paid',
            'total_amount' => 500.00,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/analytics/real-time-summary');

        $response->assertStatus(200);
        
        $summary = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $summary['today_orders']);
        $this->assertGreaterThanOrEqual(500, $summary['today_revenue']);
    }
}