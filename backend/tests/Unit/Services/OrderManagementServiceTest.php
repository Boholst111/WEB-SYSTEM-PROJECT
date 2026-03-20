<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderManagementService;
use App\Services\Payment\PaymentService;
use App\Services\NotificationService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Mockery;

class OrderManagementServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected OrderManagementService $orderManagementService;
    protected $paymentServiceMock;
    protected $notificationServiceMock;
    protected $shippingServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->paymentServiceMock = Mockery::mock(PaymentService::class);
        $this->notificationServiceMock = Mockery::mock(NotificationService::class);
        $this->shippingServiceMock = Mockery::mock(ShippingService::class);

        // Create service instance with mocks
        $this->orderManagementService = new OrderManagementService(
            $this->paymentServiceMock,
            $this->notificationServiceMock,
            $this->shippingServiceMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_update_order_status_with_valid_transition()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendOrderStatusUpdate')
            ->once()
            ->with($order, Order::STATUS_PENDING, Order::STATUS_CONFIRMED);

        $result = $this->orderManagementService->updateOrderStatus(
            $order,
            Order::STATUS_CONFIRMED,
            ['notify_customer' => true]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    /** @test */
    public function it_rejects_invalid_status_transitions()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING
        ]);

        $result = $this->orderManagementService->updateOrderStatus(
            $order,
            Order::STATUS_DELIVERED // Invalid transition from pending
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid status transition', $result['message']);
        $this->assertEquals(Order::STATUS_PENDING, $order->fresh()->status);
    }

    /** @test */
    public function it_updates_additional_fields_during_status_change()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PROCESSING
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendOrderStatusUpdate')
            ->once();

        $this->shippingServiceMock
            ->shouldReceive('createShipment')
            ->once()
            ->with($order, Mockery::type('array'));

        $result = $this->orderManagementService->updateOrderStatus(
            $order,
            Order::STATUS_SHIPPED,
            [
                'tracking_number' => 'TEST123456',
                'courier_service' => 'lbc',
                'admin_notes' => 'Shipped via LBC'
            ]
        );

        $this->assertTrue($result['success']);
        
        $order->refresh();
        $this->assertEquals('TEST123456', $order->tracking_number);
        $this->assertEquals('lbc', $order->courier_service);
        $this->assertEquals('Shipped via LBC', $order->admin_notes);
        $this->assertNotNull($order->shipped_at);
    }

    /** @test */
    public function it_can_perform_bulk_order_updates()
    {
        $user = User::factory()->create();
        $orders = Order::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendOrderStatusUpdate')
            ->times(3);

        $orderIds = $orders->pluck('id')->toArray();

        $result = $this->orderManagementService->bulkUpdateOrders(
            $orderIds,
            'update_status',
            ['status' => Order::STATUS_CONFIRMED]
        );

        $this->assertEquals(3, $result['processed']);
        $this->assertEquals(0, $result['failed']);

        foreach ($orders as $order) {
            $this->assertEquals(Order::STATUS_CONFIRMED, $order->fresh()->status);
        }
    }

    /** @test */
    public function it_handles_bulk_update_failures_gracefully()
    {
        $user = User::factory()->create();
        $validOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING
        ]);
        
        $invalidOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_DELIVERED // Cannot transition to confirmed
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendOrderStatusUpdate')
            ->once(); // Only for valid order

        $orderIds = [$validOrder->id, $invalidOrder->id];

        $result = $this->orderManagementService->bulkUpdateOrders(
            $orderIds,
            'update_status',
            ['status' => Order::STATUS_CONFIRMED]
        );

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(1, $result['failed']);
        
        $this->assertEquals(Order::STATUS_CONFIRMED, $validOrder->fresh()->status);
        $this->assertEquals(Order::STATUS_DELIVERED, $invalidOrder->fresh()->status);
    }

    /** @test */
    public function it_reserves_inventory_when_order_is_confirmed()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);
        
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendOrderStatusUpdate')
            ->once();

        $result = $this->orderManagementService->updateOrderStatus(
            $order,
            Order::STATUS_CONFIRMED
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(7, $product->fresh()->stock_quantity); // 10 - 3 = 7
    }

    /** @test */
    public function it_releases_inventory_when_order_is_cancelled()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_CONFIRMED
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendOrderStatusUpdate')
            ->once();

        $result = $this->orderManagementService->updateOrderStatus(
            $order,
            Order::STATUS_CANCELLED
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(7, $product->fresh()->stock_quantity); // 5 + 2 = 7
    }

    /** @test */
    public function it_generates_order_timeline_correctly()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_SHIPPED,
            'shipped_at' => now()->subDays(1),
            'tracking_number' => 'TEST123',
            'courier_service' => 'lbc'
        ]);

        // Create payment
        $order->payment()->create([
            'amount' => $order->total_amount,
            'payment_method' => 'gcash',
            'gateway' => 'gcash',
            'status' => 'completed'
        ]);

        $timeline = $this->orderManagementService->getOrderTimeline($order);

        $this->assertIsArray($timeline);
        $this->assertGreaterThan(0, count($timeline));

        // Check for required events
        $events = collect($timeline)->pluck('event')->toArray();
        $this->assertContains('order_created', $events);
        $this->assertContains('payment_processed', $events);
        $this->assertContains('shipped', $events);

        // Verify timeline is sorted by timestamp
        $timestamps = collect($timeline)->pluck('timestamp')->toArray();
        $sortedTimestamps = collect($timestamps)->sort()->values()->toArray();
        $this->assertEquals($sortedTimestamps, $timestamps);
    }

    /** @test */
    public function it_calculates_order_analytics_correctly()
    {
        $user = User::factory()->create();
        
        // Create orders with different statuses and dates
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_DELIVERED,
            'total_amount' => 1000,
            'created_at' => now()->subDays(5)
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 500,
            'created_at' => now()->subDays(3)
        ]);

        $dateFrom = now()->subDays(7)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        $analytics = $this->orderManagementService->getOrderAnalytics($dateFrom, $dateTo, 'day');

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('order_trends', $analytics);
        $this->assertArrayHasKey('status_distribution', $analytics);
        $this->assertArrayHasKey('summary', $analytics);

        $summary = $analytics['summary'];
        $this->assertEquals(2, $summary['total_orders']);
        $this->assertEquals(1500, $summary['total_revenue']);
        $this->assertEquals(750, $summary['avg_order_value']);
        $this->assertEquals(50, $summary['completion_rate']); // 1 delivered out of 2 total
    }

    /** @test */
    public function it_handles_payment_exceptions_correctly()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_status' => Order::PAYMENT_FAILED
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendPaymentExceptionNotification')
            ->once()
            ->with($order, 'mark_paid', Mockery::type('array'));

        $result = $this->orderManagementService->handlePaymentException(
            $order,
            'mark_paid',
            ['reason' => 'Manual verification']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(Order::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    /** @test */
    public function it_handles_inventory_exceptions_correctly()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendInventoryExceptionNotification')
            ->once()
            ->with($order, 'partial_fulfillment', Mockery::type('array'));

        $items = [
            [
                'order_item_id' => $orderItem->id,
                'new_quantity' => 3
            ]
        ];

        $result = $this->orderManagementService->handleInventoryException(
            $order,
            'partial_fulfillment',
            $items,
            ['admin_notes' => 'Partial stock available']
        );

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_skips_notifications_when_requested()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING
        ]);

        $this->notificationServiceMock
            ->shouldNotReceive('sendOrderStatusUpdate');

        $result = $this->orderManagementService->updateOrderStatus(
            $order,
            Order::STATUS_CONFIRMED,
            ['notify_customer' => false]
        );

        $this->assertTrue($result['success']);
    }
}