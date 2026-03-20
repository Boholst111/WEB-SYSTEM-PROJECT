<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\User;
use App\Models\LoyaltyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class PreOrderModelTest extends TestCase
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
            'preorder_number',
            'product_id',
            'user_id',
            'quantity',
            'deposit_amount',
            'remaining_amount',
            'total_amount',
            'deposit_paid_at',
            'full_payment_due_date',
            'status',
            'estimated_arrival_date',
            'actual_arrival_date',
            'payment_method',
            'shipping_address',
            'notes',
            'admin_notes',
            'notification_sent',
            'payment_reminder_sent_at',
        ];

        $preorder = new PreOrder();
        $this->assertEquals($fillable, $preorder->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $preorder = PreOrder::factory()->create([
            'deposit_amount' => '150.75',
            'remaining_amount' => '349.25',
            'total_amount' => '500.00',
            'deposit_paid_at' => '2024-01-15 10:30:00',
            'full_payment_due_date' => '2024-02-15',
            'estimated_arrival_date' => '2024-03-01',
            'actual_arrival_date' => '2024-03-05',
            'shipping_address' => ['street' => '123 Main St', 'city' => 'Manila'],
            'notification_sent' => true,
            'payment_reminder_sent_at' => '2024-02-10 09:00:00',
        ]);

        $this->assertEquals(150.75, $preorder->deposit_amount);
        $this->assertEquals(349.25, $preorder->remaining_amount);
        $this->assertEquals(500.00, $preorder->total_amount);
        $this->assertInstanceOf(\Carbon\Carbon::class, $preorder->deposit_paid_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $preorder->full_payment_due_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $preorder->estimated_arrival_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $preorder->actual_arrival_date);
        $this->assertIsArray($preorder->shipping_address);
        $this->assertTrue($preorder->notification_sent);
        $this->assertInstanceOf(\Carbon\Carbon::class, $preorder->payment_reminder_sent_at);
    }

    /** @test */
    public function it_belongs_to_product()
    {
        $product = Product::factory()->create();
        $preorder = PreOrder::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $preorder->product);
        $this->assertEquals($product->id, $preorder->product->id);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $preorder = PreOrder::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $preorder->user);
        $this->assertEquals($user->id, $preorder->user->id);
    }

    /** @test */
    public function it_has_loyalty_transactions_relationship()
    {
        $preorder = PreOrder::factory()->create();
        $transaction = LoyaltyTransaction::factory()->create(['preorder_id' => $preorder->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $preorder->loyaltyTransactions);
        $this->assertTrue($preorder->loyaltyTransactions->contains($transaction));
    }

    /** @test */
    public function it_scopes_by_status()
    {
        $depositPendingPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);
        $depositPaidPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PAID]);

        $depositPendingPreorders = PreOrder::byStatus(PreOrder::STATUS_DEPOSIT_PENDING)->get();

        $this->assertTrue($depositPendingPreorders->contains($depositPendingPreorder));
        $this->assertFalse($depositPendingPreorders->contains($depositPaidPreorder));
    }

    /** @test */
    public function it_scopes_by_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $preorder1 = PreOrder::factory()->create(['user_id' => $user1->id]);
        $preorder2 = PreOrder::factory()->create(['user_id' => $user2->id]);

        $user1Preorders = PreOrder::byUser($user1->id)->get();

        $this->assertTrue($user1Preorders->contains($preorder1));
        $this->assertFalse($user1Preorders->contains($preorder2));
    }

    /** @test */
    public function it_scopes_by_product()
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $preorder1 = PreOrder::factory()->create(['product_id' => $product1->id]);
        $preorder2 = PreOrder::factory()->create(['product_id' => $product2->id]);

        $product1Preorders = PreOrder::byProduct($product1->id)->get();

        $this->assertTrue($product1Preorders->contains($preorder1));
        $this->assertFalse($product1Preorders->contains($preorder2));
    }

    /** @test */
    public function it_scopes_ready_for_payment()
    {
        $readyPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_READY_FOR_PAYMENT]);
        $depositPendingPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        $readyPreorders = PreOrder::readyForPayment()->get();

        $this->assertTrue($readyPreorders->contains($readyPreorder));
        $this->assertFalse($readyPreorders->contains($depositPendingPreorder));
    }

    /** @test */
    public function it_scopes_deposit_pending()
    {
        $depositPendingPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);
        $depositPaidPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PAID]);

        $depositPendingPreorders = PreOrder::depositPending()->get();

        $this->assertTrue($depositPendingPreorders->contains($depositPendingPreorder));
        $this->assertFalse($depositPendingPreorders->contains($depositPaidPreorder));
    }

    /** @test */
    public function it_scopes_arrived_preorders()
    {
        $arrivedPreorder = PreOrder::factory()->create(['actual_arrival_date' => now()]);
        $notArrivedPreorder = PreOrder::factory()->create(['actual_arrival_date' => null]);

        $arrivedPreorders = PreOrder::arrived()->get();

        $this->assertTrue($arrivedPreorders->contains($arrivedPreorder));
        $this->assertFalse($arrivedPreorders->contains($notArrivedPreorder));
    }

    /** @test */
    public function it_scopes_due_for_reminder()
    {
        $duePreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(5),
            'payment_reminder_sent_at' => null,
        ]);

        $notDuePreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(10),
            'payment_reminder_sent_at' => null,
        ]);

        $duePreorders = PreOrder::dueForReminder()->get();

        $this->assertTrue($duePreorders->contains($duePreorder));
        $this->assertFalse($duePreorders->contains($notDuePreorder));
    }

    /** @test */
    public function it_generates_unique_preorder_number()
    {
        $preorderNumber1 = PreOrder::generatePreOrderNumber();
        $preorderNumber2 = PreOrder::generatePreOrderNumber();

        $this->assertNotEquals($preorderNumber1, $preorderNumber2);
        $this->assertStringStartsWith('PO', $preorderNumber1);
        $this->assertStringStartsWith('PO', $preorderNumber2);
        $this->assertEquals(10, strlen($preorderNumber1)); // PO + 6 digit date + 4 digit random
    }

    /** @test */
    public function it_calculates_amounts_with_default_deposit_percentage()
    {
        $product = Product::factory()->create(['current_price' => 1000.00]);
        $preorder = PreOrder::factory()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $preorder->calculateAmounts();

        $this->assertEquals(2000.00, $preorder->total_amount); // 1000 * 2
        $this->assertEquals(600.00, $preorder->deposit_amount); // 2000 * 0.3
        $this->assertEquals(1400.00, $preorder->remaining_amount); // 2000 - 600
    }

    /** @test */
    public function it_calculates_amounts_with_custom_deposit_percentage()
    {
        $product = Product::factory()->create(['current_price' => 1000.00]);
        $preorder = PreOrder::factory()->create([
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $preorder->calculateAmounts(0.5); // 50% deposit

        $this->assertEquals(1000.00, $preorder->total_amount);
        $this->assertEquals(500.00, $preorder->deposit_amount);
        $this->assertEquals(500.00, $preorder->remaining_amount);
    }

    /** @test */
    public function it_processes_deposit_payment()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        $result = $preorder->processDepositPayment('gcash');

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertEquals('gcash', $preorder->payment_method);
        $this->assertNotNull($preorder->deposit_paid_at);
    }

    /** @test */
    public function it_fails_to_process_deposit_payment_for_wrong_status()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PAID]);

        $result = $preorder->processDepositPayment('gcash');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_marks_ready_for_payment()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PAID]);

        $result = $preorder->markReadyForPayment();

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
        $this->assertNotNull($preorder->full_payment_due_date);
        $this->assertFalse($preorder->notification_sent);
    }

    /** @test */
    public function it_fails_to_mark_ready_for_payment_for_wrong_status()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        $result = $preorder->markReadyForPayment();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_completes_payment()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_READY_FOR_PAYMENT]);

        $result = $preorder->completePayment();

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_PAYMENT_COMPLETED, $preorder->status);
    }

    /** @test */
    public function it_fails_to_complete_payment_for_wrong_status()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PAID]);

        $result = $preorder->completePayment();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_updates_status_with_valid_transitions()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        // Valid transition: deposit_pending -> deposit_paid
        $result = $preorder->updateStatus(PreOrder::STATUS_DEPOSIT_PAID);
        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);

        // Valid transition: deposit_paid -> ready_for_payment
        $result = $preorder->updateStatus(PreOrder::STATUS_READY_FOR_PAYMENT);
        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);

        // Valid transition: ready_for_payment -> payment_completed
        $result = $preorder->updateStatus(PreOrder::STATUS_PAYMENT_COMPLETED);
        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_PAYMENT_COMPLETED, $preorder->status);

        // Valid transition: payment_completed -> shipped
        $result = $preorder->updateStatus(PreOrder::STATUS_SHIPPED);
        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_SHIPPED, $preorder->status);

        // Valid transition: shipped -> delivered
        $result = $preorder->updateStatus(PreOrder::STATUS_DELIVERED);
        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_DELIVERED, $preorder->status);
    }

    /** @test */
    public function it_rejects_invalid_status_transitions()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        // Invalid transition: deposit_pending -> payment_completed (skipping steps)
        $result = $preorder->updateStatus(PreOrder::STATUS_PAYMENT_COMPLETED);
        $this->assertFalse($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PENDING, $preorder->status);
    }

    /** @test */
    public function it_allows_cancellation_from_valid_statuses()
    {
        $depositPendingPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);
        $depositPaidPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PAID]);
        $readyForPaymentPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_READY_FOR_PAYMENT]);
        $paymentCompletedPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_PAYMENT_COMPLETED]);
        $shippedPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_SHIPPED]);

        $this->assertTrue($depositPendingPreorder->canBeCancelled());
        $this->assertTrue($depositPaidPreorder->canBeCancelled());
        $this->assertTrue($readyForPaymentPreorder->canBeCancelled());
        $this->assertTrue($paymentCompletedPreorder->canBeCancelled());
        $this->assertFalse($shippedPreorder->canBeCancelled());
    }

    /** @test */
    public function it_checks_if_deposit_is_paid()
    {
        $paidPreorder = PreOrder::factory()->create(['deposit_paid_at' => now()]);
        $unpaidPreorder = PreOrder::factory()->create(['deposit_paid_at' => null]);

        $this->assertTrue($paidPreorder->isDepositPaid());
        $this->assertFalse($unpaidPreorder->isDepositPaid());
    }

    /** @test */
    public function it_checks_if_payment_is_completed()
    {
        $completedPreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_PAYMENT_COMPLETED]);
        $incompletePreorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_READY_FOR_PAYMENT]);

        $this->assertTrue($completedPreorder->isPaymentCompleted());
        $this->assertFalse($incompletePreorder->isPaymentCompleted());
    }

    /** @test */
    public function it_checks_if_payment_is_overdue()
    {
        $overduePreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->subDays(1),
        ]);

        $notOverduePreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(1),
        ]);

        $this->assertTrue($overduePreorder->isPaymentOverdue());
        $this->assertFalse($notOverduePreorder->isPaymentOverdue());
    }

    /** @test */
    public function it_gets_status_labels()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);
        $this->assertEquals('Deposit Pending', $preorder->getStatusLabelAttribute());

        $preorder->status = PreOrder::STATUS_READY_FOR_PAYMENT;
        $this->assertEquals('Ready for Payment', $preorder->getStatusLabelAttribute());

        $preorder->status = PreOrder::STATUS_DELIVERED;
        $this->assertEquals('Delivered', $preorder->getStatusLabelAttribute());
    }

    /** @test */
    public function it_gets_formatted_amounts()
    {
        $preorder = PreOrder::factory()->create([
            'total_amount' => 1234.56,
            'deposit_amount' => 370.37,
            'remaining_amount' => 864.19,
        ]);

        $this->assertEquals('₱1,234.56', $preorder->getFormattedTotalAttribute());
        $this->assertEquals('₱370.37', $preorder->getFormattedDepositAttribute());
        $this->assertEquals('₱864.19', $preorder->getFormattedRemainingAttribute());
    }

    /** @test */
    public function it_calculates_days_until_due()
    {
        $preorder = PreOrder::factory()->create([
            'full_payment_due_date' => now()->addDays(5),
        ]);
        $this->assertEquals(5, $preorder->getDaysUntilDueAttribute());

        $overduePreorder = PreOrder::factory()->create([
            'full_payment_due_date' => now()->subDays(2),
        ]);
        $this->assertEquals(-2, $overduePreorder->getDaysUntilDueAttribute());

        $preorderWithoutDueDate = PreOrder::factory()->create([
            'full_payment_due_date' => null,
        ]);
        $this->assertNull($preorderWithoutDueDate->getDaysUntilDueAttribute());
    }

    /** @test */
    public function it_sends_arrival_notification()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'notification_sent' => false,
        ]);

        $result = $preorder->sendArrivalNotification();

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
        $this->assertTrue($preorder->notification_sent);
        $this->assertNotNull($preorder->full_payment_due_date);
    }

    /** @test */
    public function it_does_not_send_arrival_notification_for_wrong_status()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'notification_sent' => false,
        ]);

        $result = $preorder->sendArrivalNotification();

        $this->assertFalse($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PENDING, $preorder->status);
        $this->assertFalse($preorder->notification_sent);
    }

    /** @test */
    public function it_sends_payment_reminder()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'payment_reminder_sent_at' => null,
        ]);

        $result = $preorder->sendPaymentReminder();

        $this->assertTrue($result);
        $this->assertNotNull($preorder->payment_reminder_sent_at);
    }

    /** @test */
    public function it_does_not_send_payment_reminder_for_wrong_status()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'payment_reminder_sent_at' => null,
        ]);

        $result = $preorder->sendPaymentReminder();

        $this->assertFalse($result);
        $this->assertNull($preorder->payment_reminder_sent_at);
    }

    /** @test */
    public function it_awards_loyalty_credits_for_completed_preorders()
    {
        $user = User::factory()->create([
            'loyalty_credits' => 0,
            'total_spent' => 0,
            'loyalty_tier' => 'bronze',
        ]);

        $preorder = PreOrder::factory()->create([
            'user_id' => $user->id,
            'status' => PreOrder::STATUS_DELIVERED,
            'total_amount' => 1000.00,
            'deposit_amount' => 300.00,
            'remaining_amount' => 0.00, // Payment completed
            'deposit_paid_at' => now()->subDays(30), // Deposit was paid
        ]);

        $preorder->awardLoyaltyCredits();

        $user->refresh();
        
        // Credits should be 5% of total amount (1000 * 0.05 = 50) with bronze multiplier (1.0)
        $this->assertEquals(50.00, $user->loyalty_credits);
        $this->assertEquals(1000.00, $user->total_spent);

        // Check loyalty transaction was created
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $user->id,
            'preorder_id' => $preorder->id,
            'transaction_type' => 'earned',
            'amount' => 50.00,
        ]);
    }

    /** @test */
    public function it_does_not_award_credits_for_non_delivered_preorders()
    {
        $user = User::factory()->create(['loyalty_credits' => 0]);

        $preorder = PreOrder::factory()->create([
            'user_id' => $user->id,
            'status' => PreOrder::STATUS_SHIPPED,
            'total_amount' => 1000.00,
        ]);

        $preorder->awardLoyaltyCredits();

        $user->refresh();
        $this->assertEquals(0, $user->loyalty_credits);
    }

    /** @test */
    public function it_applies_tier_multiplier_to_preorder_loyalty_credits()
    {
        $user = User::factory()->create([
            'loyalty_credits' => 0,
            'total_spent' => 0,
            'loyalty_tier' => 'gold', // 1.5x multiplier
        ]);

        $preorder = PreOrder::factory()->create([
            'user_id' => $user->id,
            'status' => PreOrder::STATUS_DELIVERED,
            'total_amount' => 1000.00,
            'deposit_amount' => 300.00,
            'remaining_amount' => 0.00, // Payment completed
            'deposit_paid_at' => now()->subDays(30), // Deposit was paid
        ]);

        $preorder->awardLoyaltyCredits();

        $user->refresh();
        
        // Credits should be 5% of total amount (1000 * 0.05 = 50) with gold multiplier (1.5) = 75
        $this->assertEquals(75.00, $user->loyalty_credits);
    }
}