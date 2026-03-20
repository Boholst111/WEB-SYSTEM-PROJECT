<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Admin\OrderController;
use App\Services\OrderManagementService;
use App\Services\Payment\PaymentService;
use App\Services\NotificationService;
use App\Services\ShippingService;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;

/**
 * Test order management workflows and bulk operations.
 * Validates Requirements 1.5, 1.10
 */
class AdminOrderManagementWorkflowsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected OrderController $orderController;
    protected OrderManagementService $orderManagementService;
    protected $paymentServiceMock;
    protected $notificationServiceMock;
    protected $shippingServiceMock;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->paymentServiceMock = Mockery::mock(PaymentService::class);
        $this->notificationServiceMock = Mockery::mock(NotificationService::class);
        $this->shippingServiceMock = Mockery::mock(ShippingService::class);

        // Create service and controller
        $this->orderManagementService = new OrderManagementService(
            $this->paymentServiceMock,
            $this->notificationServiceMock,
            $this->shippingServiceMock
        );

        $this->orderController = new OrderController(
            $this->orderManagementService,
            $this->shippingServiceMock
        );

        // Create test data
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create(['stock_quantity' => 100]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function multi_stage_order_workflow_processes_correctly()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PAID,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendOrderStatusUpdate')
            ->times(4); // For each status transition

        // Stage 1: Pending -> Confirmed
        $request = new Request([
            'status' => Order::STATUS_CONFIRMED,
            'admin_notes' => 'Order confirmed',
        ]);

        $response = $this->orderController->updateStatus($request, $order);
        $this->assertTrue($response->getData(true)['success']);
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->fresh()->status);
        
        // Verify inventory was reserved
        $this->assertEquals(95, $this->product->fresh()->stock_quantity); // 100 - 5

        // Stage 2: Confirmed -> Processing
        $request = new Request(['status' => Order::STATUS_PROCESSING]);
        $response = $this->orderController->updateStatus($request, $order);
        $this->assertTrue($response->getData(true)['success']);
        $this->assertEquals(Order::STATUS_PROCESSING, $order->fresh()->status);

        // Stage 3: Processing -> Shipped
        $this->shippingServiceMock
            ->shouldReceive('createShipment')
            ->once()
            ->with($order, Mockery::type('array'));

        $request = new Request([
            'status' => Order::STATUS_SHIPPED,
            'tracking_number' => 'TRACK123456',
            'courier_service' => 'lbc',
        ]);

        $response = $this->orderController->updateStatus($request, $order);
        $this->assertTrue($response->getData(true)['success']);
        
        $order->refresh();
        $this->assertEquals(Order::STATUS_SHIPPED, $order->status);
        $this->assertEquals('TRACK123456', $order->tracking_number);
        $this->assertEquals('lbc', $order->courier_service);
        $this->assertNotNull($order->shipped_at);

        // Stage 4: Shipped -> Delivered
        $request = new Request(['status' => Order::STATUS_DELIVERED]);
        $response = $this->orderController->updateStatus($request, $order);
        $this->assertTrue($response->getData(true)['success']);
        
        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);
    }

    /** @test */
    public function bulk_status_update_handles_mixed_results()
    {
        // Create orders with different statuses
        $pendingOrders = Order::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING,
        ]);

        $confirmedOrder = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_CONFIRMED,
        ]);

        $deliveredOrder = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DELIVERED, // Cannot transition to confirmed
        ]);

        $allOrderIds = $pendingOrders->pluck('id')
            ->concat([$confirmedOrder->id, $deliveredOrder->id])
            ->toArray();

        $this->notificationServiceMock
            ->shouldReceive('sendOrderStatusUpdate')
            ->times(3); // Only for successful transitions

        $request = new Request([
            'order_ids' => $allOrderIds,
            'action' => 'update_status',
            'status' => Order::STATUS_CONFIRMED,
            'admin_notes' => 'Bulk confirmation',
        ]);

        $response = $this->orderController->bulkUpdate($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(3, $data['data']['processed']); // 3 pending orders
        $this->assertEquals(2, $data['data']['failed']); // confirmed and delivered orders

        // Verify results array
        $results = $data['data']['results'];
        $this->assertCount(5, $results);

        $successfulResults = collect($results)->where('success', true);
        $failedResults = collect($results)->where('success', false);

        $this->assertCount(3, $successfulResults);
        $this->assertCount(2, $failedResults);

        // Verify pending orders were updated
        foreach ($pendingOrders as $order) {
            $this->assertEquals(Order::STATUS_CONFIRMED, $order->fresh()->status);
        }

        // Verify other orders remained unchanged
        $this->assertEquals(Order::STATUS_CONFIRMED, $confirmedOrder->fresh()->status);
        $this->assertEquals(Order::STATUS_DELIVERED, $deliveredOrder->fresh()->status);
    }

    /** @test */
    public function bulk_tracking_update_adds_shipping_info()
    {
        $orders = Order::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PROCESSING,
        ]);

        $orderIds = $orders->pluck('id')->toArray();
        $trackingNumbers = [
            $orders[0]->id => 'TRACK001',
            $orders[1]->id => 'TRACK002',
            $orders[2]->id => 'TRACK003',
        ];

        $request = new Request([
            'order_ids' => $orderIds,
            'action' => 'add_tracking',
            'tracking_numbers' => $trackingNumbers,
            'courier_service' => 'lbc',
        ]);

        $response = $this->orderController->bulkUpdate($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(3, $data['data']['processed']);
        $this->assertEquals(0, $data['data']['failed']);

        // Verify tracking numbers were assigned
        foreach ($orders as $order) {
            $order->refresh();
            $this->assertEquals($trackingNumbers[$order->id], $order->tracking_number);
            $this->assertEquals('lbc', $order->courier_service);
        }
    }

    /** @test */
    public function payment_exception_handling_processes_correctly()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => Order::PAYMENT_FAILED,
            'total_amount' => 1500.00,
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendPaymentExceptionNotification')
            ->once()
            ->with($order, 'mark_paid', Mockery::type('array'));

        $request = new Request([
            'action' => 'mark_paid',
            'reason' => 'Manual verification completed',
            'admin_notes' => 'Payment confirmed via bank statement',
        ]);

        $response = $this->orderController->handlePaymentException($request, $order);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(Order::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    /** @test */
    public function inventory_exception_handling_manages_stock_issues()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_CONFIRMED,
        ]);

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendInventoryExceptionNotification')
            ->once()
            ->with($order, 'partial_fulfillment', Mockery::type('array'));

        $request = new Request([
            'action' => 'partial_fulfillment',
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'new_quantity' => 7, // Reduce from 10 to 7
                ]
            ],
            'admin_notes' => 'Only 7 units available in stock',
            'notify_customer' => true,
        ]);

        $response = $this->orderController->handleInventoryException($request, $order);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
    }

    /** @test */
    public function order_cancellation_releases_inventory()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PAID,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 15,
        ]);

        // Simulate inventory reservation (reduce stock)
        $this->product->update(['stock_quantity' => 85]); // 100 - 15

        $this->notificationServiceMock
            ->shouldReceive('sendOrderStatusUpdate')
            ->once();

        $request = new Request([
            'status' => Order::STATUS_CANCELLED,
            'admin_notes' => 'Customer requested cancellation',
        ]);

        $response = $this->orderController->updateStatus($request, $order);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(Order::STATUS_CANCELLED, $order->fresh()->status);
        
        // Verify inventory was released
        $this->assertEquals(100, $this->product->fresh()->stock_quantity); // 85 + 15

        // Verify inventory movement was logged
        $releaseMovement = InventoryMovement::where('product_id', $this->product->id)
            ->where('movement_type', 'release')
            ->first();
        
        $this->assertNotNull($releaseMovement);
        $this->assertEquals(15, $releaseMovement->quantity_change);
        $this->assertEquals('order', $releaseMovement->reference_type);
        $this->assertEquals($order->id, $releaseMovement->reference_id);
    }

    /** @test */
    public function shipping_label_generation_processes_bulk_orders()
    {
        $orders = Order::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PROCESSING,
        ]);

        $orderIds = $orders->pluck('id')->toArray();

        $this->shippingServiceMock
            ->shouldReceive('generateBulkLabels')
            ->once()
            ->with($orderIds, 'lbc', 'standard')
            ->andReturn([
                'labels_generated' => 5,
                'download_url' => '/admin/shipping/labels/batch_123.pdf',
                'tracking_numbers' => [
                    $orders[0]->id => 'LBC001',
                    $orders[1]->id => 'LBC002',
                    $orders[2]->id => 'LBC003',
                    $orders[3]->id => 'LBC004',
                    $orders[4]->id => 'LBC005',
                ]
            ]);

        $request = new Request([
            'order_ids' => $orderIds,
            'courier_service' => 'lbc',
            'service_type' => 'standard',
        ]);

        $response = $this->orderController->generateShippingLabels($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(5, $data['data']['labels_generated']);
        $this->assertStringContains('batch_123.pdf', $data['data']['download_url']);
    }

    /** @test */
    public function order_timeline_tracks_all_events()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DELIVERED,
            'shipped_at' => now()->subDays(2),
            'delivered_at' => now()->subDays(1),
            'tracking_number' => 'TRACK123',
            'courier_service' => 'lbc',
        ]);

        // Create payment
        $order->payment()->create([
            'amount' => $order->total_amount,
            'payment_method' => 'gcash',
            'gateway' => 'gcash',
            'status' => 'completed',
            'created_at' => now()->subDays(5),
        ]);

        $timeline = $this->orderManagementService->getOrderTimeline($order);

        $this->assertIsArray($timeline);
        $this->assertGreaterThan(0, count($timeline));

        // Extract event types
        $events = collect($timeline)->pluck('event')->toArray();

        // Verify required events are present
        $this->assertContains('order_created', $events);
        $this->assertContains('payment_processed', $events);
        $this->assertContains('shipped', $events);
        $this->assertContains('delivered', $events);

        // Verify timeline is chronologically ordered
        $timestamps = collect($timeline)->pluck('timestamp')->toArray();
        $sortedTimestamps = collect($timestamps)->sort()->values()->toArray();
        $this->assertEquals($sortedTimestamps, $timestamps);

        // Verify shipping event contains tracking info
        $shippedEvent = collect($timeline)->firstWhere('event', 'shipped');
        $this->assertNotNull($shippedEvent);
        $this->assertStringContains('TRACK123', $shippedEvent['description']);
        $this->assertStringContains('lbc', $shippedEvent['description']);
    }

    /** @test */
    public function order_analytics_provides_detailed_metrics()
    {
        // Create orders with various characteristics
        $user2 = User::factory()->create();

        // Completed orders
        Order::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_DELIVERED,
            'payment_method' => 'gcash',
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(5),
        ]);

        // Pending orders
        Order::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING,
            'payment_method' => 'maya',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(3),
        ]);

        // Orders from different user (returning customer)
        Order::factory()->create([
            'user_id' => $user2->id,
            'status' => Order::STATUS_DELIVERED,
            'total_amount' => 800.00,
            'created_at' => now()->subDays(2),
        ]);

        // Historical order for user2 (to make them returning)
        Order::factory()->create([
            'user_id' => $user2->id,
            'status' => Order::STATUS_DELIVERED,
            'total_amount' => 600.00,
            'created_at' => now()->subDays(20), // Outside date range
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

        // Verify structure
        $this->assertArrayHasKey('order_trends', $analytics);
        $this->assertArrayHasKey('status_distribution', $analytics);
        $this->assertArrayHasKey('payment_methods', $analytics);
        $this->assertArrayHasKey('customer_metrics', $analytics);
        $this->assertArrayHasKey('summary', $analytics);

        // Verify summary calculations
        $summary = $analytics['summary'];
        $this->assertEquals(6, $summary['total_orders']); // 3 + 2 + 1
        $this->assertEquals(6800.00, $summary['total_revenue']); // (3*1000) + (2*1500) + 800
        $this->assertEquals(1133.33, round($summary['avg_order_value'], 2)); // 6800/6
        $this->assertEquals(66.67, round($summary['completion_rate'], 2)); // 4 delivered out of 6 total

        // Verify status distribution
        $statusDistribution = $analytics['status_distribution'];
        $this->assertEquals(4, $statusDistribution['delivered']); // 3 + 1
        $this->assertEquals(2, $statusDistribution['pending']);

        // Verify payment method breakdown
        $paymentMethods = $analytics['payment_methods'];
        $this->assertCount(2, $paymentMethods);

        $gcashMethod = $paymentMethods->firstWhere('payment_method', 'gcash');
        $mayaMethod = $paymentMethods->firstWhere('payment_method', 'maya');

        $this->assertEquals(3, $gcashMethod['order_count']);
        $this->assertEquals(3000.00, $gcashMethod['revenue']);

        $this->assertEquals(2, $mayaMethod['order_count']);
        $this->assertEquals(3000.00, $mayaMethod['revenue']);

        // Verify customer metrics
        $customerMetrics = $analytics['customer_metrics'];
        $this->assertEquals(0, $customerMetrics['new_customers']); // Both users created outside date range
        $this->assertEquals(1, $customerMetrics['returning_customers']); // user2 has historical order
    }

    /** @test */
    public function order_export_generates_download_link()
    {
        Order::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(3),
        ]);

        $request = new Request([
            'format' => 'csv',
            'filters' => [
                'date_from' => now()->subWeek()->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ],
            'columns' => ['order_number', 'status', 'total_amount', 'created_at'],
        ]);

        $response = $this->orderController->export($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('download_url', $data['data']);
        $this->assertArrayHasKey('filename', $data['data']);
        $this->assertArrayHasKey('expires_at', $data['data']);

        $this->assertStringContains('orders_export_', $data['data']['filename']);
        $this->assertStringContains('.csv', $data['data']['filename']);
        $this->assertStringContains('/admin/exports/', $data['data']['download_url']);
    }

    /** @test */
    public function order_search_filters_by_multiple_criteria()
    {
        $user2 = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);

        // Create orders with different characteristics
        $order1 = Order::factory()->create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-001',
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(5),
        ]);

        $order2 = Order::factory()->create([
            'user_id' => $user2->id,
            'order_number' => 'ORD-002',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'total_amount' => 1500.00,
            'created_at' => now()->subDays(3),
        ]);

        // Test search by order number
        $request = new Request(['search' => 'ORD-001']);
        $response = $this->orderController->index($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $orders = $data['data']['data'];
        $this->assertCount(1, $orders);
        $this->assertEquals('ORD-001', $orders[0]['order_number']);

        // Test search by customer name
        $request = new Request(['search' => 'John']);
        $response = $this->orderController->index($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $orders = $data['data']['data'];
        $this->assertCount(1, $orders);
        $this->assertEquals($user2->id, $orders[0]['user_id']);

        // Test combined filters
        $request = new Request([
            'status' => 'pending',
            'payment_status' => 'pending',
            'date_from' => now()->subWeek()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        $response = $this->orderController->index($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $orders = $data['data']['data'];
        $this->assertCount(1, $orders);
        $this->assertEquals('pending', $orders[0]['status']);
        $this->assertEquals('pending', $orders[0]['payment_status']);
    }
}