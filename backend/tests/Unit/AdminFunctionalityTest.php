<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\InventoryController;
use App\Services\AnalyticsService;
use App\Services\OrderManagementService;
use App\Services\NotificationService;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\LoyaltyTransaction;
use App\Models\InventoryMovement;
use App\Models\PreOrder;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;

class AdminFunctionalityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected AnalyticsController $analyticsController;
    protected OrderController $orderController;
    protected InventoryController $inventoryController;
    protected User $user;
    protected AdminUser $admin;
    protected Product $product;
    protected Brand $brand;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::factory()->create();
        $this->admin = AdminUser::factory()->create();
        $this->brand = Brand::factory()->create();
        $this->category = Category::factory()->create();
        
        $this->product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'current_price' => 1000.00,
            'stock_quantity' => 100,
        ]);

        // Create controllers with real services
        $analyticsService = new AnalyticsService();
        $this->analyticsController = new AnalyticsController($analyticsService);
        
        $paymentService = Mockery::mock(\App\Services\Payment\PaymentService::class);
        $notificationService = Mockery::mock(NotificationService::class);
        $shippingService = Mockery::mock(\App\Services\ShippingService::class);
        
        $orderManagementService = new OrderManagementService(
            $paymentService,
            $notificationService,
            $shippingService
        );
        
        $this->orderController = new OrderController($orderManagementService, $shippingService);
        $this->inventoryController = new InventoryController($notificationService);
    }
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===== ANALYTICS FUNCTIONALITY TESTS =====

    /** @test */
    public function analytics_dashboard_returns_comprehensive_data()
    {
        // Create test orders with different statuses and dates
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
            'total_amount' => 1600.00,
            'created_at' => now()->subDays(3),
        ]);

        // Create order items
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

        // Create loyalty transactions
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => 'earned',
            'amount' => 50.00,
            'created_at' => now()->subDays(3),
        ]);

        $request = new Request([
            'date_from' => now()->subWeek()->toDateString(),
            'date_to' => now()->toDateString(),
            'period' => 'daily'
        ]);

        $response = $this->analyticsController->index($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        
        $analyticsData = $data['data'];
        $this->assertArrayHasKey('sales_analytics', $analyticsData);
        $this->assertArrayHasKey('product_analytics', $analyticsData);
        $this->assertArrayHasKey('customer_analytics', $analyticsData);
        $this->assertArrayHasKey('traffic_analysis', $analyticsData);
        $this->assertArrayHasKey('loyalty_metrics', $analyticsData);
        $this->assertArrayHasKey('inventory_insights', $analyticsData);

        // Verify sales analytics structure
        $salesAnalytics = $analyticsData['sales_analytics'];
        $this->assertArrayHasKey('revenue_metrics', $salesAnalytics);
        $this->assertArrayHasKey('order_metrics', $salesAnalytics);
        $this->assertArrayHasKey('payment_analytics', $salesAnalytics);
    }

    /** @test */
    public function analytics_caches_dashboard_data()
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'sales_analytics' => [],
                'product_analytics' => [],
                'customer_analytics' => [],
                'traffic_analysis' => [],
                'loyalty_metrics' => [],
                'inventory_insights' => [],
            ]);

        $request = new Request([
            'date_from' => now()->subWeek()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $response = $this->analyticsController->index($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function sales_metrics_calculates_revenue_correctly()
    {
        // Create paid orders
        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'subtotal' => 1000.00,
            'discount_amount' => 100.00,
            'credits_used' => 50.00,
            'shipping_fee' => 100.00,
            'total_amount' => 950.00,
            'created_at' => now()->subDays(2),
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'subtotal' => 2000.00,
            'total_amount' => 2100.00,
            'created_at' => now()->subDays(1),
        ]);

        $request = new Request([
            'date_from' => now()->subWeek()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $response = $this->analyticsController->salesMetrics($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $revenueMetrics = $data['data']['revenue_metrics'];
        
        $this->assertEquals(3050.00, $revenueMetrics['total_revenue']); // 950 + 2100
        $this->assertEquals(3000.00, $revenueMetrics['gross_revenue']); // 1000 + 2000
        $this->assertEquals(100.00, $revenueMetrics['discount_amount']);
        $this->assertEquals(50.00, $revenueMetrics['credits_used']);
    }
    /** @test */
    public function product_performance_identifies_best_sellers()
    {
        $product2 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'current_price' => 500.00,
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

        $request = new Request([
            'date_from' => now()->subWeek()->toDateString(),
            'date_to' => now()->toDateString(),
            'limit' => 10,
        ]);

        $response = $this->analyticsController->productPerformance($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $bestSellers = $data['data']['best_sellers'];
        
        $this->assertCount(2, $bestSellers);
        $this->assertEquals(5, $bestSellers[0]['total_sold']); // Product 1 should be first
        $this->assertEquals(2, $bestSellers[1]['total_sold']); // Product 2 should be second
    }

    /** @test */
    public function customer_analytics_tracks_acquisition_and_retention()
    {
        // Create new customer in date range
        $newUser = User::factory()->create(['created_at' => now()->subDays(5)]);
        
        // Create returning customer (old user with new order)
        $oldUser = User::factory()->create(['created_at' => now()->subMonths(2)]);
        
        // Old order for returning customer
        Order::factory()->create([
            'user_id' => $oldUser->id,
            'created_at' => now()->subMonths(3),
        ]);
        
        // New order for returning customer
        Order::factory()->create([
            'user_id' => $oldUser->id,
            'created_at' => now()->subDays(2),
        ]);

        $request = new Request([
            'date_from' => now()->subWeek()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $response = $this->analyticsController->customerAnalytics($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $customerData = $data['data'];
        
        $this->assertArrayHasKey('acquisition_metrics', $customerData);
        $this->assertArrayHasKey('retention_metrics', $customerData);
        $this->assertArrayHasKey('lifetime_value', $customerData);
        $this->assertArrayHasKey('loyalty_analysis', $customerData);
        
        $acquisitionMetrics = $customerData['acquisition_metrics'];
        $this->assertEquals(1, $acquisitionMetrics['new_customers']); // newUser
        
        $retentionMetrics = $customerData['retention_metrics'];
        $this->assertEquals(1, $retentionMetrics['returning_customers']); // oldUser
    }

    /** @test */
    public function real_time_summary_provides_current_metrics()
    {
        // Create today's orders
        Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'paid',
            'total_amount' => 1000.00,
            'created_at' => now(),
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        // Create low stock product
        Product::factory()->create([
            'stock_quantity' => 3,
            'is_preorder' => false,
        ]);

        $response = $this->analyticsController->realTimeSummary();
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $summary = $data['data'];
        
        $this->assertArrayHasKey('today_orders', $summary);
        $this->assertArrayHasKey('today_revenue', $summary);
        $this->assertArrayHasKey('pending_orders', $summary);
        $this->assertArrayHasKey('low_stock_products', $summary);
        
        $this->assertEquals(2, $summary['today_orders']);
        $this->assertEquals(1000.00, $summary['today_revenue']);
        $this->assertEquals(1, $summary['pending_orders']);
        $this->assertEquals(1, $summary['low_stock_products']);
    }

    // ===== ORDER MANAGEMENT FUNCTIONALITY TESTS =====

    /** @test */
    public function order_index_filters_and_paginates_correctly()
    {
        // Create orders with different statuses
        Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(2),
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(1),
        ]);

        $request = new Request([
            'status' => 'pending',
            'per_page' => 10,
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
        ]);

        $response = $this->orderController->index($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('summary', $data);
        
        // Should only return pending orders
        $orders = $data['data']['data'];
        $this->assertCount(1, $orders);
        $this->assertEquals('pending', $orders[0]['status']);
    }
    /** @test */
    public function order_show_returns_complete_order_details()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'shipped',
            'tracking_number' => 'TEST123456',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 1000.00,
        ]);

        $order->payment()->create([
            'amount' => $order->total_amount,
            'payment_method' => 'gcash',
            'gateway' => 'gcash',
            'status' => 'completed'
        ]);

        $response = $this->orderController->show($order);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $orderData = $data['data'];
        
        $this->assertArrayHasKey('order', $orderData);
        $this->assertArrayHasKey('timeline', $orderData);
        $this->assertArrayHasKey('can_cancel', $orderData);
        $this->assertArrayHasKey('can_refund', $orderData);
        
        // Verify order has relationships loaded
        $order = $orderData['order'];
        $this->assertArrayHasKey('user', $order);
        $this->assertArrayHasKey('items', $order);
        $this->assertArrayHasKey('payment', $order);
    }

    /** @test */
    public function order_status_update_validates_transitions()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        // Valid transition
        $request = new Request([
            'status' => 'confirmed',
            'admin_notes' => 'Order confirmed by admin',
            'notify_customer' => true,
        ]);

        $response = $this->orderController->updateStatus($request, $order);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals('confirmed', $order->fresh()->status);
        $this->assertEquals('Order confirmed by admin', $order->fresh()->admin_notes);
    }

    /** @test */
    public function order_status_update_rejects_invalid_transitions()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        // Invalid transition (pending -> delivered)
        $request = new Request([
            'status' => 'delivered',
        ]);

        $response = $this->orderController->updateStatus($request, $order);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals('pending', $order->fresh()->status); // Should remain unchanged
    }

    /** @test */
    public function bulk_order_update_processes_multiple_orders()
    {
        $orders = Order::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $orderIds = $orders->pluck('id')->toArray();

        $request = new Request([
            'order_ids' => $orderIds,
            'action' => 'update_status',
            'status' => 'confirmed',
            'admin_notes' => 'Bulk confirmation',
        ]);

        $response = $this->orderController->bulkUpdate($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(3, $data['data']['processed']);
        $this->assertEquals(0, $data['data']['failed']);

        // Verify all orders were updated
        foreach ($orders as $order) {
            $this->assertEquals('confirmed', $order->fresh()->status);
        }
    }

    /** @test */
    public function order_analytics_provides_comprehensive_metrics()
    {
        // Create orders with different characteristics
        Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'delivered',
            'payment_method' => 'gcash',
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(5),
        ]);

        Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'payment_method' => 'maya',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(3),
        ]);

        $request = new Request([
            'date_from' => now()->subWeek()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
            'group_by' => 'day',
        ]);

        $response = $this->orderController->analytics($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $analytics = $data['data'];
        
        $this->assertArrayHasKey('order_trends', $analytics);
        $this->assertArrayHasKey('status_distribution', $analytics);
        $this->assertArrayHasKey('payment_methods', $analytics);
        $this->assertArrayHasKey('summary', $analytics);
        
        $summary = $analytics['summary'];
        $this->assertEquals(2, $summary['total_orders']);
        $this->assertEquals(2500.00, $summary['total_revenue']);
        $this->assertEquals(50.00, $summary['completion_rate']); // 1 delivered out of 2
    }

    // ===== INVENTORY MANAGEMENT FUNCTIONALITY TESTS =====

    /** @test */
    public function inventory_index_filters_by_stock_status()
    {
        // Create products with different stock levels
        Product::factory()->create([
            'name' => 'In Stock Product',
            'stock_quantity' => 50,
            'is_preorder' => false,
        ]);

        Product::factory()->create([
            'name' => 'Low Stock Product',
            'stock_quantity' => 3,
            'is_preorder' => false,
        ]);

        Product::factory()->create([
            'name' => 'Out of Stock Product',
            'stock_quantity' => 0,
            'is_preorder' => false,
        ]);

        Product::factory()->create([
            'name' => 'Preorder Product',
            'stock_quantity' => 0,
            'is_preorder' => true,
        ]);

        // Test low stock filter
        $request = new Request([
            'stock_status' => 'low_stock',
            'low_stock_threshold' => 5,
        ]);

        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $products = $data['data']['data'];
        
        $this->assertCount(1, $products);
        $this->assertEquals('Low Stock Product', $products[0]['name']);
        
        // Verify summary counts
        $summary = $data['summary'];
        $this->assertEquals(1, $summary['in_stock']); // In Stock Product
        $this->assertEquals(1, $summary['low_stock']); // Low Stock Product
        $this->assertEquals(1, $summary['out_of_stock']); // Out of Stock Product
        $this->assertEquals(1, $summary['preorders']); // Preorder Product
    }
    /** @test */
    public function low_stock_alert_identifies_products_correctly()
    {
        // Create products with different stock levels
        Product::factory()->create([
            'name' => 'Critical Stock',
            'stock_quantity' => 2,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Normal Stock',
            'stock_quantity' => 20,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Inactive Product',
            'stock_quantity' => 1,
            'is_preorder' => false,
            'status' => 'inactive', // Should be excluded
        ]);

        $request = new Request(['threshold' => 5]);

        $response = $this->inventoryController->lowStock($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals(5, $data['threshold']);
        
        $products = $data['data'];
        $this->assertCount(1, $products);
        $this->assertEquals('Critical Stock', $products[0]['name']);
        $this->assertEquals(2, $products[0]['stock_quantity']);
    }

    /** @test */
    public function stock_update_creates_inventory_movement()
    {
        $initialStock = $this->product->stock_quantity;

        $request = new Request([
            'quantity' => 50,
            'type' => 'restock',
            'reason' => 'New shipment received',
        ]);

        $response = $this->inventoryController->updateStock($request, $this->product);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        
        // Verify stock was updated
        $this->product->refresh();
        $this->assertEquals($initialStock + 50, $this->product->stock_quantity);
        
        // Verify inventory movement was created
        $movement = InventoryMovement::where('product_id', $this->product->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('restock', $movement->movement_type);
        $this->assertEquals(50, $movement->quantity_change);
        $this->assertEquals($initialStock, $movement->quantity_before);
        $this->assertEquals($initialStock + 50, $movement->quantity_after);
        $this->assertEquals('New shipment received', $movement->reason);
    }

    /** @test */
    public function stock_update_handles_different_movement_types()
    {
        $this->product->update(['stock_quantity' => 100]);

        // Test damage reduction
        $request = new Request([
            'quantity' => 5,
            'type' => 'damage',
            'reason' => 'Damaged during handling',
        ]);

        $response = $this->inventoryController->updateStock($request, $this->product);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(95, $this->product->fresh()->stock_quantity); // 100 - 5

        // Test direct adjustment
        $request = new Request([
            'quantity' => 80,
            'type' => 'adjustment',
            'reason' => 'Physical count adjustment',
        ]);

        $response = $this->inventoryController->updateStock($request, $this->product);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(80, $this->product->fresh()->stock_quantity); // Direct set to 80
    }

    /** @test */
    public function inventory_reports_provide_movement_analytics()
    {
        // Create inventory movements
        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'movement_type' => 'sale',
            'quantity_change' => -5,
            'created_at' => now()->subDays(2),
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'movement_type' => 'restock',
            'quantity_change' => 20,
            'created_at' => now()->subDays(1),
        ]);

        $request = new Request([
            'date_from' => now()->subWeek()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        $response = $this->inventoryController->reports($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $reportData = $data['data'];
        
        $this->assertArrayHasKey('movements', $reportData);
        $this->assertArrayHasKey('summary', $reportData);
        
        $movements = $reportData['movements'];
        $this->assertCount(2, $movements);
        
        // Find sale movement
        $saleMovement = $movements->firstWhere('movement_type', 'sale');
        $this->assertNotNull($saleMovement);
        $this->assertEquals(1, $saleMovement['count']);
        $this->assertEquals(5, $saleMovement['total_quantity']);
        
        // Find restock movement
        $restockMovement = $movements->firstWhere('movement_type', 'restock');
        $this->assertNotNull($restockMovement);
        $this->assertEquals(1, $restockMovement['count']);
        $this->assertEquals(20, $restockMovement['total_quantity']);
    }

    /** @test */
    public function preorder_arrivals_tracks_status_correctly()
    {
        // Create preorders with different statuses
        $pendingPreorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->addDays(5),
            'actual_arrival_date' => null,
        ]);

        $arrivedPreorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->subDays(2),
            'actual_arrival_date' => now()->subDays(1),
        ]);

        $overduePreorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->subDays(3),
            'actual_arrival_date' => null,
        ]);

        // Test pending arrivals
        $request = new Request(['arrival_status' => 'pending']);
        $response = $this->inventoryController->preOrderArrivals($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $preorders = $data['data']['data'];
        $this->assertCount(1, $preorders);
        $this->assertEquals($pendingPreorder->id, $preorders[0]['id']);

        // Test overdue arrivals
        $request = new Request(['arrival_status' => 'overdue']);
        $response = $this->inventoryController->preOrderArrivals($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $preorders = $data['data']['data'];
        $this->assertCount(1, $preorders);
        $this->assertEquals($overduePreorder->id, $preorders[0]['id']);
    }
    /** @test */
    public function preorder_arrival_update_changes_status()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->addDays(5),
            'actual_arrival_date' => null,
        ]);

        $request = new Request([
            'actual_arrival_date' => now()->format('Y-m-d'),
            'notes' => 'Arrived in good condition',
        ]);

        $response = $this->inventoryController->updatePreOrderArrival($request, $preorder);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        
        $preorder->refresh();
        $this->assertNotNull($preorder->actual_arrival_date);
        $this->assertEquals('Arrived in good condition', $preorder->admin_notes);
        $this->assertEquals('ready_for_payment', $preorder->status);
    }

    /** @test */
    public function chase_variants_management_filters_correctly()
    {
        // Create chase variants with different availability
        $availableChase = Product::factory()->create([
            'name' => 'Available Chase',
            'is_chase_variant' => true,
            'stock_quantity' => 5,
            'current_price' => 2000.00,
        ]);

        $soldOutChase = Product::factory()->create([
            'name' => 'Sold Out Chase',
            'is_chase_variant' => true,
            'stock_quantity' => 0,
            'current_price' => 3000.00,
        ]);

        Product::factory()->create([
            'name' => 'Regular Product',
            'is_chase_variant' => false,
            'stock_quantity' => 10,
        ]);

        // Test available chase variants
        $request = new Request(['availability' => 'available']);
        $response = $this->inventoryController->chaseVariants($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $chaseVariants = $data['data']['data'];
        $this->assertCount(1, $chaseVariants);
        $this->assertEquals('Available Chase', $chaseVariants[0]['name']);
        
        // Verify summary
        $summary = $data['summary'];
        $this->assertEquals(2, $summary['total_chase_variants']);
        $this->assertEquals(1, $summary['available']);
        $this->assertEquals(1, $summary['sold_out']);
        $this->assertEquals(2500.00, $summary['average_price']); // (2000 + 3000) / 2
    }

    /** @test */
    public function purchase_order_creation_calculates_totals()
    {
        $product2 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
        ]);

        $request = new Request([
            'supplier_name' => 'Test Supplier',
            'supplier_email' => 'supplier@test.com',
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_cost' => 500.00,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 5,
                    'unit_cost' => 300.00,
                ],
            ],
            'expected_delivery_date' => now()->addWeeks(2)->format('Y-m-d'),
            'notes' => 'Urgent restock needed',
        ]);

        $response = $this->inventoryController->createPurchaseOrder($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $poData = $data['data'];
        
        $this->assertArrayHasKey('purchase_order_number', $poData);
        $this->assertEquals('Test Supplier', $poData['supplier_name']);
        $this->assertEquals(6500.00, $poData['total_amount']); // (10 * 500) + (5 * 300)
        $this->assertEquals(2, $poData['items_count']);
        
        // Verify inventory movement was created
        $movement = InventoryMovement::where('movement_type', 'purchase_order')->first();
        $this->assertNotNull($movement);
        $this->assertEquals($poData['purchase_order_number'], $movement->reference_id);
    }

    // ===== VALIDATION AND ERROR HANDLING TESTS =====

    /** @test */
    public function analytics_validates_date_parameters()
    {
        $request = new Request([
            'date_from' => 'invalid-date',
            'date_to' => now()->toDateString(),
        ]);

        // Should handle invalid dates gracefully
        $response = $this->analyticsController->salesMetrics($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function order_controller_validates_status_updates()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $request = new Request([
            'status' => 'invalid_status',
        ]);

        $response = $this->orderController->updateStatus($request, $order);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $data);
    }

    /** @test */
    public function inventory_controller_validates_stock_updates()
    {
        $request = new Request([
            'quantity' => -10, // Invalid negative quantity
            'type' => 'restock',
            'reason' => 'Test',
        ]);

        $response = $this->inventoryController->updateStock($request, $this->product);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function bulk_operations_handle_partial_failures()
    {
        // Create orders with different statuses
        $validOrder = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
        
        $invalidOrder = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'delivered', // Cannot transition to confirmed
        ]);

        $request = new Request([
            'order_ids' => [$validOrder->id, $invalidOrder->id],
            'action' => 'update_status',
            'status' => 'confirmed',
        ]);

        $response = $this->orderController->bulkUpdate($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']); // Overall operation succeeds
        $this->assertEquals(1, $data['data']['processed']);
        $this->assertEquals(1, $data['data']['failed']);
        
        // Verify results array contains details for both orders
        $results = $data['data']['results'];
        $this->assertCount(2, $results);
        
        $validResult = collect($results)->firstWhere('order_id', $validOrder->id);
        $invalidResult = collect($results)->firstWhere('order_id', $invalidOrder->id);
        
        $this->assertTrue($validResult['success']);
        $this->assertFalse($invalidResult['success']);
    }
}