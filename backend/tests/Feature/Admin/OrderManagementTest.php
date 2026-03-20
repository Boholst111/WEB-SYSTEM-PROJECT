<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderManagementService;
use App\Services\ShippingService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $admin;
    protected User $customer;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user (using regular User model with admin role)
        $this->admin = User::factory()->create([
            'email' => 'admin@diecastempire.com',
            'preferences' => ['role' => 'admin']
        ]);

        // Create customer
        $this->customer = User::factory()->create();

        // Create products
        $products = Product::factory()->count(3)->create([
            'stock_quantity' => 10,
            'current_price' => 500.00
        ]);

        // Create order with items
        $this->order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PAID,
            'total_amount' => 1500.00
        ]);

        foreach ($products as $product) {
            OrderItem::factory()->create([
                'order_id' => $this->order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->current_price
            ]);
        }

        // Authenticate as admin
        Sanctum::actingAs($this->admin, ['*']);
    }

    /** @test */
    public function admin_can_view_orders_list()
    {
        $response = $this->getJson('/api/admin/orders');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'order_number',
                                'status',
                                'payment_status',
                                'total_amount',
                                'user' => [
                                    'id',
                                    'first_name',
                                    'last_name',
                                    'email'
                                ]
                            ]
                        ],
                        'current_page',
                        'total'
                    ],
                    'summary' => [
                        'total_orders',
                        'total_revenue',
                        'status_breakdown'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_filter_orders_by_status()
    {
        // Create orders with different statuses
        Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);
        Order::factory()->create(['status' => Order::STATUS_SHIPPED]);

        $response = $this->getJson('/api/admin/orders?status=confirmed');

        $response->assertStatus(200);
        
        $orders = $response->json('data.data');
        foreach ($orders as $order) {
            $this->assertEquals('confirmed', $order['status']);
        }
    }

    /** @test */
    public function admin_can_search_orders_by_order_number()
    {
        $response = $this->getJson('/api/admin/orders?search=' . $this->order->order_number);

        $response->assertStatus(200);
        
        $orders = $response->json('data.data');
        $this->assertCount(1, $orders);
        $this->assertEquals($this->order->order_number, $orders[0]['order_number']);
    }

    /** @test */
    public function admin_can_view_single_order_details()
    {
        $response = $this->getJson("/api/admin/orders/{$this->order->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'order' => [
                            'id',
                            'order_number',
                            'status',
                            'items' => [
                                '*' => [
                                    'id',
                                    'product' => [
                                        'id',
                                        'name',
                                        'sku'
                                    ],
                                    'quantity',
                                    'unit_price'
                                ]
                            ]
                        ],
                        'timeline',
                        'can_cancel',
                        'can_refund'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_update_order_status()
    {
        $response = $this->putJson("/api/admin/orders/{$this->order->id}/status", [
            'status' => Order::STATUS_CONFIRMED,
            'admin_notes' => 'Order confirmed by admin',
            'notify_customer' => true
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Order status updated successfully'
                ]);

        $this->order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $this->order->status);
        $this->assertEquals('Order confirmed by admin', $this->order->admin_notes);
    }

    /** @test */
    public function admin_cannot_make_invalid_status_transitions()
    {
        // Try to transition from pending directly to delivered
        $response = $this->putJson("/api/admin/orders/{$this->order->id}/status", [
            'status' => Order::STATUS_DELIVERED
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false
                ]);

        $this->order->refresh();
        $this->assertEquals(Order::STATUS_PENDING, $this->order->status);
    }

    /** @test */
    public function admin_can_add_tracking_information()
    {
        // First confirm the order
        $this->order->update(['status' => Order::STATUS_PROCESSING]);

        $response = $this->putJson("/api/admin/orders/{$this->order->id}/status", [
            'status' => Order::STATUS_SHIPPED,
            'tracking_number' => 'LBC123456789',
            'courier_service' => 'lbc'
        ]);

        $response->assertStatus(200);

        $this->order->refresh();
        $this->assertEquals(Order::STATUS_SHIPPED, $this->order->status);
        $this->assertEquals('LBC123456789', $this->order->tracking_number);
        $this->assertEquals('lbc', $this->order->courier_service);
        $this->assertNotNull($this->order->shipped_at);
    }

    /** @test */
    public function admin_can_bulk_update_orders()
    {
        // Create additional orders
        $orders = Order::factory()->count(3)->create([
            'status' => Order::STATUS_CONFIRMED
        ]);

        $orderIds = $orders->pluck('id')->toArray();

        $response = $this->postJson('/api/admin/orders/bulk-update', [
            'order_ids' => $orderIds,
            'action' => 'update_status',
            'status' => Order::STATUS_PROCESSING,
            'admin_notes' => 'Bulk status update'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'processed',
                        'failed',
                        'results'
                    ]
                ]);

        // Verify all orders were updated
        foreach ($orders as $order) {
            $order->refresh();
            $this->assertEquals(Order::STATUS_PROCESSING, $order->status);
        }
    }

    /** @test */
    public function admin_can_handle_payment_exceptions()
    {
        // Create order with failed payment
        $failedOrder = Order::factory()->create([
            'payment_status' => Order::PAYMENT_FAILED
        ]);

        $response = $this->postJson("/api/admin/orders/{$failedOrder->id}/payment-exception", [
            'action' => 'mark_paid',
            'reason' => 'Manual verification completed',
            'admin_notes' => 'Payment verified through bank statement'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function admin_can_handle_inventory_exceptions()
    {
        $orderItem = $this->order->items->first();

        $response = $this->postJson("/api/admin/orders/{$this->order->id}/inventory-exception", [
            'action' => 'partial_fulfillment',
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'new_quantity' => 1
                ]
            ],
            'admin_notes' => 'Partial stock available'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function admin_can_generate_shipping_labels()
    {
        // Create orders ready for shipping
        $orders = Order::factory()->count(2)->create([
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Order::PAYMENT_PAID
        ]);

        $orderIds = $orders->pluck('id')->toArray();

        $response = $this->postJson('/api/admin/orders/shipping-labels', [
            'order_ids' => $orderIds,
            'courier_service' => 'lbc',
            'service_type' => 'standard'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'summary' => [
                            'total',
                            'successful',
                            'failed'
                        ],
                        'results' => [
                            '*' => [
                                'order_id',
                                'order_number',
                                'success',
                                'tracking_number'
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function admin_can_view_order_analytics()
    {
        $response = $this->getJson('/api/admin/orders/analytics?date_from=2024-01-01&date_to=2024-12-31');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'order_trends',
                        'status_distribution',
                        'payment_methods',
                        'top_products',
                        'customer_metrics',
                        'summary' => [
                            'total_orders',
                            'total_revenue',
                            'avg_order_value',
                            'completion_rate'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function admin_can_export_orders()
    {
        $response = $this->postJson('/api/admin/orders/export', [
            'format' => 'csv',
            'filters' => [
                'status' => 'pending'
            ],
            'columns' => ['order_number', 'customer_name', 'total_amount', 'status']
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'download_url',
                        'filename',
                        'expires_at'
                    ]
                ]);
    }

    /** @test */
    public function validation_errors_are_returned_for_invalid_requests()
    {
        // Test invalid status update
        $response = $this->putJson("/api/admin/orders/{$this->order->id}/status", [
            'status' => 'invalid_status'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);

        // Test invalid bulk update
        $response = $this->postJson('/api/admin/orders/bulk-update', [
            'order_ids' => [],
            'action' => 'invalid_action'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['order_ids', 'action']);
    }

    /** @test */
    public function unauthorized_users_cannot_access_admin_endpoints()
    {
        // Create regular user and authenticate
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/admin/orders');

        $response->assertStatus(403);
    }

    /** @test */
    public function order_timeline_includes_all_status_changes()
    {
        // Update order through multiple statuses
        $this->order->updateStatus(Order::STATUS_CONFIRMED);
        $this->order->updateStatus(Order::STATUS_PROCESSING);
        $this->order->update([
            'status' => Order::STATUS_SHIPPED,
            'tracking_number' => 'TEST123',
            'courier_service' => 'lbc',
            'shipped_at' => now()
        ]);

        $response = $this->getJson("/api/admin/orders/{$this->order->id}");

        $timeline = $response->json('data.timeline');
        
        $this->assertGreaterThanOrEqual(3, count($timeline));
        
        // Check for key events
        $events = collect($timeline)->pluck('event')->toArray();
        $this->assertContains('order_created', $events);
        $this->assertContains('shipped', $events);
    }
}