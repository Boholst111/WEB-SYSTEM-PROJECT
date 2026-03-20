<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\User;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\LoyaltyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class OrderModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up loyalty configuration for testing
        Config::set('loyalty.credits_rate', 0.05);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'order_number',
            'user_id',
            'status',
            'subtotal',
            'credits_used',
            'discount_amount',
            'shipping_fee',
            'tax_amount',
            'total_amount',
            'payment_method',
            'payment_status',
            'shipping_address',
            'billing_address',
            'tracking_number',
            'courier_service',
            'shipped_at',
            'delivered_at',
            'notes',
            'admin_notes',
        ];

        $order = new Order();
        $this->assertEquals($fillable, $order->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $order = Order::factory()->create([
            'subtotal' => '123.45',
            'credits_used' => '10.50',
            'discount_amount' => '5.25',
            'shipping_fee' => '15.00',
            'tax_amount' => '12.34',
            'total_amount' => '135.54',
            'shipping_address' => ['street' => '123 Main St', 'city' => 'Manila'],
            'billing_address' => ['street' => '456 Oak Ave', 'city' => 'Quezon City'],
            'shipped_at' => '2024-01-15 10:30:00',
            'delivered_at' => '2024-01-18 14:45:00',
        ]);

        $this->assertEquals(123.45, $order->subtotal);
        $this->assertEquals(10.50, $order->credits_used);
        $this->assertEquals(5.25, $order->discount_amount);
        $this->assertEquals(15.00, $order->shipping_fee);
        $this->assertEquals(12.34, $order->tax_amount);
        $this->assertEquals(135.54, $order->total_amount);
        $this->assertIsArray($order->shipping_address);
        $this->assertIsArray($order->billing_address);
        $this->assertInstanceOf(\Carbon\Carbon::class, $order->shipped_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $order->delivered_at);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $order->user);
        $this->assertEquals($user->id, $order->user->id);
    }

    /** @test */
    public function it_has_items_relationship()
    {
        $order = Order::factory()->create();
        $item = OrderItem::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $order->items);
        $this->assertTrue($order->items->contains($item));
    }

    /** @test */
    public function it_has_payment_relationship()
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(Payment::class, $order->payment);
        $this->assertEquals($payment->id, $order->payment->id);
    }

    /** @test */
    public function it_has_loyalty_transactions_relationship()
    {
        $order = Order::factory()->create();
        $transaction = LoyaltyTransaction::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $order->loyaltyTransactions);
        $this->assertTrue($order->loyaltyTransactions->contains($transaction));
    }

    /** @test */
    public function it_scopes_by_status()
    {
        $pendingOrder = Order::factory()->create(['status' => Order::STATUS_PENDING]);
        $shippedOrder = Order::factory()->create(['status' => Order::STATUS_SHIPPED]);

        $pendingOrders = Order::byStatus(Order::STATUS_PENDING)->get();

        $this->assertTrue($pendingOrders->contains($pendingOrder));
        $this->assertFalse($pendingOrders->contains($shippedOrder));
    }

    /** @test */
    public function it_scopes_by_payment_status()
    {
        $paidOrder = Order::factory()->create(['payment_status' => Order::PAYMENT_PAID]);
        $pendingOrder = Order::factory()->create(['payment_status' => Order::PAYMENT_PENDING]);

        $paidOrders = Order::byPaymentStatus(Order::PAYMENT_PAID)->get();

        $this->assertTrue($paidOrders->contains($paidOrder));
        $this->assertFalse($paidOrders->contains($pendingOrder));
    }

    /** @test */
    public function it_scopes_by_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $order1 = Order::factory()->create(['user_id' => $user1->id]);
        $order2 = Order::factory()->create(['user_id' => $user2->id]);

        $user1Orders = Order::byUser($user1->id)->get();

        $this->assertTrue($user1Orders->contains($order1));
        $this->assertFalse($user1Orders->contains($order2));
    }

    /** @test */
    public function it_scopes_by_date_range()
    {
        $oldOrder = Order::factory()->create(['created_at' => '2024-01-01']);
        $recentOrder = Order::factory()->create(['created_at' => '2024-01-15']);

        $recentOrders = Order::byDateRange('2024-01-10', '2024-01-20')->get();

        $this->assertFalse($recentOrders->contains($oldOrder));
        $this->assertTrue($recentOrders->contains($recentOrder));
    }

    /** @test */
    public function it_scopes_pending_orders()
    {
        $pendingOrder = Order::factory()->create(['status' => Order::STATUS_PENDING]);
        $confirmedOrder = Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);

        $pendingOrders = Order::pending()->get();

        $this->assertTrue($pendingOrders->contains($pendingOrder));
        $this->assertFalse($pendingOrders->contains($confirmedOrder));
    }

    /** @test */
    public function it_scopes_confirmed_orders()
    {
        $confirmedOrder = Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);
        $pendingOrder = Order::factory()->create(['status' => Order::STATUS_PENDING]);

        $confirmedOrders = Order::confirmed()->get();

        $this->assertTrue($confirmedOrders->contains($confirmedOrder));
        $this->assertFalse($confirmedOrders->contains($pendingOrder));
    }

    /** @test */
    public function it_scopes_shipped_orders()
    {
        $shippedOrder = Order::factory()->create(['status' => Order::STATUS_SHIPPED]);
        $processingOrder = Order::factory()->create(['status' => Order::STATUS_PROCESSING]);

        $shippedOrders = Order::shipped()->get();

        $this->assertTrue($shippedOrders->contains($shippedOrder));
        $this->assertFalse($shippedOrders->contains($processingOrder));
    }

    /** @test */
    public function it_scopes_delivered_orders()
    {
        $deliveredOrder = Order::factory()->create(['status' => Order::STATUS_DELIVERED]);
        $shippedOrder = Order::factory()->create(['status' => Order::STATUS_SHIPPED]);

        $deliveredOrders = Order::delivered()->get();

        $this->assertTrue($deliveredOrders->contains($deliveredOrder));
        $this->assertFalse($deliveredOrders->contains($shippedOrder));
    }

    /** @test */
    public function it_generates_unique_order_number()
    {
        $orderNumber1 = Order::generateOrderNumber();
        $orderNumber2 = Order::generateOrderNumber();

        $this->assertNotEquals($orderNumber1, $orderNumber2);
        $this->assertStringStartsWith('DE', $orderNumber1);
        $this->assertStringStartsWith('DE', $orderNumber2);
        $this->assertEquals(12, strlen($orderNumber1)); // DE + 6 digit date + 4 digit random
    }

    /** @test */
    public function it_calculates_order_totals()
    {
        $order = Order::factory()->create([
            'credits_used' => 10.00,
            'discount_amount' => 5.00,
            'shipping_fee' => 15.00,
            'tax_amount' => 8.00,
        ]);

        // Create order items
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => 50.00,
            'quantity' => 2,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => 30.00,
            'quantity' => 1,
        ]);

        $order->calculateTotals();

        $this->assertEquals(130.00, $order->subtotal); // (50*2) + (30*1)
        $this->assertEquals(138.00, $order->total_amount); // 130 - 10 - 5 + 15 + 8
    }

    /** @test */
    public function it_ensures_total_is_not_negative()
    {
        $order = Order::factory()->create([
            'credits_used' => 200.00,
            'discount_amount' => 50.00,
            'shipping_fee' => 0.00,
            'tax_amount' => 0.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => 100.00,
            'quantity' => 1,
        ]);

        $order->calculateTotals();

        $this->assertEquals(0.00, $order->total_amount);
    }

    /** @test */
    public function it_updates_status_with_valid_transitions()
    {
        $order = Order::factory()->create(['status' => Order::STATUS_PENDING]);

        // Valid transition: pending -> confirmed
        $result = $order->updateStatus(Order::STATUS_CONFIRMED);
        $this->assertTrue($result);
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->status);

        // Valid transition: confirmed -> processing
        $result = $order->updateStatus(Order::STATUS_PROCESSING);
        $this->assertTrue($result);
        $this->assertEquals(Order::STATUS_PROCESSING, $order->status);

        // Valid transition: processing -> shipped
        $result = $order->updateStatus(Order::STATUS_SHIPPED);
        $this->assertTrue($result);
        $this->assertEquals(Order::STATUS_SHIPPED, $order->status);
        $this->assertNotNull($order->shipped_at);

        // Valid transition: shipped -> delivered
        $result = $order->updateStatus(Order::STATUS_DELIVERED);
        $this->assertTrue($result);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);
    }

    /** @test */
    public function it_rejects_invalid_status_transitions()
    {
        $order = Order::factory()->create(['status' => Order::STATUS_PENDING]);

        // Invalid transition: pending -> shipped (skipping confirmed and processing)
        $result = $order->updateStatus(Order::STATUS_SHIPPED);
        $this->assertFalse($result);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
    }

    /** @test */
    public function it_allows_cancellation_from_valid_statuses()
    {
        $pendingOrder = Order::factory()->create(['status' => Order::STATUS_PENDING]);
        $confirmedOrder = Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);
        $processingOrder = Order::factory()->create(['status' => Order::STATUS_PROCESSING]);
        $shippedOrder = Order::factory()->create(['status' => Order::STATUS_SHIPPED]);

        $this->assertTrue($pendingOrder->canBeCancelled());
        $this->assertTrue($confirmedOrder->canBeCancelled());
        $this->assertTrue($processingOrder->canBeCancelled());
        $this->assertFalse($shippedOrder->canBeCancelled());
    }

    /** @test */
    public function it_allows_refund_for_delivered_paid_orders()
    {
        $deliveredPaidOrder = Order::factory()->create([
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_PAID,
        ]);

        $pendingOrder = Order::factory()->create([
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PAID,
        ]);

        $deliveredUnpaidOrder = Order::factory()->create([
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_PENDING,
        ]);

        $this->assertTrue($deliveredPaidOrder->canBeRefunded());
        $this->assertFalse($pendingOrder->canBeRefunded());
        $this->assertFalse($deliveredUnpaidOrder->canBeRefunded());
    }

    /** @test */
    public function it_gets_total_items_count()
    {
        $order = Order::factory()->create();
        
        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 2]);
        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 3]);

        $this->assertEquals(5, $order->getTotalItemsAttribute());
    }

    /** @test */
    public function it_gets_formatted_total()
    {
        $order = Order::factory()->create(['total_amount' => 123.45]);
        $this->assertEquals('₱123.45', $order->getFormattedTotalAttribute());
    }

    /** @test */
    public function it_gets_status_labels()
    {
        $order = Order::factory()->create(['status' => Order::STATUS_PENDING]);
        $this->assertEquals('Pending', $order->getStatusLabelAttribute());

        $order->status = Order::STATUS_SHIPPED;
        $this->assertEquals('Shipped', $order->getStatusLabelAttribute());
    }

    /** @test */
    public function it_gets_payment_status_labels()
    {
        $order = Order::factory()->create(['payment_status' => Order::PAYMENT_PENDING]);
        $this->assertEquals('Pending', $order->getPaymentStatusLabelAttribute());

        $order->payment_status = Order::PAYMENT_PAID;
        $this->assertEquals('Paid', $order->getPaymentStatusLabelAttribute());
    }

    /** @test */
    public function it_checks_if_order_is_paid()
    {
        $paidOrder = Order::factory()->create(['payment_status' => Order::PAYMENT_PAID]);
        $unpaidOrder = Order::factory()->create(['payment_status' => Order::PAYMENT_PENDING]);

        $this->assertTrue($paidOrder->isPaid());
        $this->assertFalse($unpaidOrder->isPaid());
    }

    /** @test */
    public function it_checks_if_order_is_completed()
    {
        $completedOrder = Order::factory()->create(['status' => Order::STATUS_DELIVERED]);
        $shippedOrder = Order::factory()->create(['status' => Order::STATUS_SHIPPED]);

        $this->assertTrue($completedOrder->isCompleted());
        $this->assertFalse($shippedOrder->isCompleted());
    }

    /** @test */
    public function it_calculates_estimated_delivery_date()
    {
        // Order that has been shipped
        $shippedOrder = Order::factory()->create([
            'status' => Order::STATUS_SHIPPED,
            'shipped_at' => now()->subDays(1),
        ]);
        $expectedDelivery = now()->subDays(1)->addDays(3)->format('Y-m-d');
        $this->assertEquals($expectedDelivery, $shippedOrder->getEstimatedDeliveryAttribute());

        // Order that is processing
        $processingOrder = Order::factory()->state([
            'status' => Order::STATUS_PROCESSING,
            'shipped_at' => null,
        ])->create();
        $actualDelivery = $processingOrder->getEstimatedDeliveryAttribute();
        $this->assertNotNull($actualDelivery);
        // Should be approximately 5 days from now (allow for small timing differences)
        $expectedDate = now()->addDays(5);
        $actualDate = \Carbon\Carbon::createFromFormat('Y-m-d', $actualDelivery);
        $daysDiff = abs($actualDate->diffInDays($expectedDate));
        $this->assertTrue($daysDiff <= 1, "Expected delivery date should be within 1 day of 5 days from now. Actual: {$actualDelivery}, Expected around: " . $expectedDate->format('Y-m-d'));

        // Order that is pending
        $pendingOrder = Order::factory()->pending()->create();
        $this->assertNull($pendingOrder->getEstimatedDeliveryAttribute());
    }

    /** @test */
    public function it_awards_loyalty_credits_for_completed_orders()
    {
        $user = User::factory()->create([
            'loyalty_credits' => 0,
            'total_spent' => 0,
            'loyalty_tier' => 'bronze',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_PAID,
            'subtotal' => 1000.00,
        ]);

        $order->awardLoyaltyCredits();

        $user->refresh();
        
        // Credits should be 5% of subtotal (1000 * 0.05 = 50) with bronze multiplier (1.0)
        $this->assertEquals(50.00, $user->loyalty_credits);
        $this->assertEquals(1000.00, $user->total_spent);

        // Check loyalty transaction was created
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'transaction_type' => 'earned',
            'amount' => 50.00,
        ]);
    }

    /** @test */
    public function it_does_not_award_credits_for_non_delivered_orders()
    {
        $user = User::factory()->create(['loyalty_credits' => 0]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_SHIPPED,
            'payment_status' => Order::PAYMENT_PAID,
            'subtotal' => 1000.00,
        ]);

        $order->awardLoyaltyCredits();

        $user->refresh();
        $this->assertEquals(0, $user->loyalty_credits);
    }

    /** @test */
    public function it_does_not_award_credits_for_unpaid_orders()
    {
        $user = User::factory()->create(['loyalty_credits' => 0]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 1000.00,
        ]);

        $order->awardLoyaltyCredits();

        $user->refresh();
        $this->assertEquals(0, $user->loyalty_credits);
    }

    /** @test */
    public function it_applies_tier_multiplier_to_loyalty_credits()
    {
        $user = User::factory()->create([
            'loyalty_credits' => 0,
            'total_spent' => 0,
            'loyalty_tier' => 'gold', // 1.5x multiplier
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_PAID,
            'subtotal' => 1000.00,
        ]);

        $order->awardLoyaltyCredits();

        $user->refresh();
        
        // Credits should be 5% of subtotal (1000 * 0.05 = 50) with gold multiplier (1.5) = 75
        $this->assertEquals(75.00, $user->loyalty_credits);
    }
}