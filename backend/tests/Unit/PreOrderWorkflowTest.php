<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\User;
use App\Models\Payment;
use App\Models\LoyaltyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Event;

class PreOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up loyalty configuration for testing
        Config::set('loyalty.credits_rate', 0.05);
        Config::set('loyalty.tier_multipliers', [
            'bronze' => 1.0,
            'silver' => 1.2,
            'gold' => 1.5,
            'platinum' => 2.0,
        ]);

        $this->user = User::factory()->create([
            'loyalty_credits' => 100.00,
            'total_spent' => 5000.00,
            'loyalty_tier' => 'silver',
        ]);

        $this->product = Product::factory()->create([
            'current_price' => 1000.00,
            'is_preorder' => true,
            'preorder_date' => now()->addMonth(),
        ]);

        Notification::fake();
        Event::fake();
    }

    /** @test */
    public function it_calculates_deposit_amounts_correctly_with_different_percentages()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);

        // Test 30% deposit (default)
        $preorder->calculateAmounts(0.3);
        $this->assertEquals(3000.00, $preorder->total_amount); // 1000 * 3
        $this->assertEquals(900.00, $preorder->deposit_amount); // 3000 * 0.3
        $this->assertEquals(2100.00, $preorder->remaining_amount); // 3000 - 900

        // Test 50% deposit
        $preorder->calculateAmounts(0.5);
        $this->assertEquals(3000.00, $preorder->total_amount);
        $this->assertEquals(1500.00, $preorder->deposit_amount); // 3000 * 0.5
        $this->assertEquals(1500.00, $preorder->remaining_amount); // 3000 - 1500

        // Test 100% deposit (full payment)
        $preorder->calculateAmounts(1.0);
        $this->assertEquals(3000.00, $preorder->total_amount);
        $this->assertEquals(3000.00, $preorder->deposit_amount); // 3000 * 1.0
        $this->assertEquals(0.00, $preorder->remaining_amount); // 3000 - 3000
    }

    /** @test */
    public function it_handles_deposit_payment_workflow_correctly()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 300.00,
            'remaining_amount' => 700.00,
        ]);

        // Process deposit payment
        $result = $preorder->processDepositPayment('gcash');

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertEquals('gcash', $preorder->payment_method);
        $this->assertNotNull($preorder->deposit_paid_at);
        $this->assertTrue($preorder->isDepositPaid());
    }

    /** @test */
    public function it_prevents_duplicate_deposit_payments()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'deposit_paid_at' => now(),
        ]);

        $result = $preorder->processDepositPayment('maya');

        $this->assertFalse($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
    }

    /** @test */
    public function it_handles_arrival_notification_workflow()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'notification_sent' => false,
            'deposit_paid_at' => now()->subWeek(),
        ]);

        $result = $preorder->sendArrivalNotification();

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
        $this->assertTrue($preorder->notification_sent);
        $this->assertNotNull($preorder->full_payment_due_date);
        
        // Should set due date to 30 days from now
        $expectedDueDate = now()->addDays(30)->format('Y-m-d');
        $this->assertEquals($expectedDueDate, $preorder->full_payment_due_date->format('Y-m-d'));
    }

    /** @test */
    public function it_prevents_duplicate_arrival_notifications()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'notification_sent' => true,
        ]);

        $result = $preorder->sendArrivalNotification();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_payment_completion_workflow()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'deposit_amount' => 300.00,
            'remaining_amount' => 700.00,
            'full_payment_due_date' => now()->addWeek(),
        ]);

        $result = $preorder->completePayment();

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_PAYMENT_COMPLETED, $preorder->status);
        $this->assertTrue($preorder->isPaymentCompleted());
    }

    /** @test */
    public function it_validates_status_transitions_correctly()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        // Valid transitions
        $this->assertTrue($preorder->updateStatus(PreOrder::STATUS_DEPOSIT_PAID));
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);

        $this->assertTrue($preorder->updateStatus(PreOrder::STATUS_READY_FOR_PAYMENT));
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);

        $this->assertTrue($preorder->updateStatus(PreOrder::STATUS_PAYMENT_COMPLETED));
        $this->assertEquals(PreOrder::STATUS_PAYMENT_COMPLETED, $preorder->status);

        $this->assertTrue($preorder->updateStatus(PreOrder::STATUS_SHIPPED));
        $this->assertEquals(PreOrder::STATUS_SHIPPED, $preorder->status);

        $this->assertTrue($preorder->updateStatus(PreOrder::STATUS_DELIVERED));
        $this->assertEquals(PreOrder::STATUS_DELIVERED, $preorder->status);
    }

    /** @test */
    public function it_rejects_invalid_status_transitions()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        // Invalid transitions - skipping steps
        $this->assertFalse($preorder->updateStatus(PreOrder::STATUS_PAYMENT_COMPLETED));
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PENDING, $preorder->status);

        $this->assertFalse($preorder->updateStatus(PreOrder::STATUS_SHIPPED));
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PENDING, $preorder->status);

        $this->assertFalse($preorder->updateStatus(PreOrder::STATUS_DELIVERED));
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PENDING, $preorder->status);
    }

    /** @test */
    public function it_allows_cancellation_from_appropriate_statuses()
    {
        $validCancellationStatuses = [
            PreOrder::STATUS_DEPOSIT_PENDING,
            PreOrder::STATUS_DEPOSIT_PAID,
            PreOrder::STATUS_READY_FOR_PAYMENT,
            PreOrder::STATUS_PAYMENT_COMPLETED,
        ];

        foreach ($validCancellationStatuses as $status) {
            $preorder = PreOrder::factory()->create(['status' => $status]);
            $this->assertTrue($preorder->canBeCancelled(), "Should allow cancellation from {$status}");
            
            $result = $preorder->updateStatus(PreOrder::STATUS_CANCELLED);
            $this->assertTrue($result, "Should successfully cancel from {$status}");
        }
    }

    /** @test */
    public function it_prevents_cancellation_from_shipped_and_delivered_statuses()
    {
        $invalidCancellationStatuses = [
            PreOrder::STATUS_SHIPPED,
            PreOrder::STATUS_DELIVERED,
        ];

        foreach ($invalidCancellationStatuses as $status) {
            $preorder = PreOrder::factory()->create(['status' => $status]);
            $this->assertFalse($preorder->canBeCancelled(), "Should not allow cancellation from {$status}");
        }
    }

    /** @test */
    public function it_detects_overdue_payments_correctly()
    {
        // Overdue payment
        $overduePreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->subDays(5),
        ]);
        $this->assertTrue($overduePreorder->isPaymentOverdue());

        // Not overdue payment
        $notOverduePreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(5),
        ]);
        $this->assertFalse($notOverduePreorder->isPaymentOverdue());

        // Completed payment should not be overdue
        $completedPreorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_PAYMENT_COMPLETED,
            'full_payment_due_date' => now()->subDays(5),
        ]);
        $this->assertFalse($completedPreorder->isPaymentOverdue());
    }

    /** @test */
    public function it_calculates_days_until_due_correctly()
    {
        $preorder = PreOrder::factory()->create([
            'full_payment_due_date' => now()->addDays(10),
        ]);
        $this->assertEquals(10, $preorder->getDaysUntilDueAttribute());

        $overduePreorder = PreOrder::factory()->create([
            'full_payment_due_date' => now()->subDays(3),
        ]);
        $this->assertEquals(-3, $overduePreorder->getDaysUntilDueAttribute());

        $noDueDatePreorder = PreOrder::factory()->create([
            'full_payment_due_date' => null,
        ]);
        $this->assertNull($noDueDatePreorder->getDaysUntilDueAttribute());
    }

    /** @test */
    public function it_sends_payment_reminders_correctly()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => now()->addDays(5),
            'payment_reminder_sent_at' => null,
        ]);

        $result = $preorder->sendPaymentReminder();

        $this->assertTrue($result);
        $this->assertNotNull($preorder->payment_reminder_sent_at);
    }

    /** @test */
    public function it_prevents_duplicate_payment_reminders()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'payment_reminder_sent_at' => now()->subHour(),
        ]);

        $result = $preorder->sendPaymentReminder();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_awards_loyalty_credits_with_tier_multipliers()
    {
        $testCases = [
            ['bronze', 1.0, 50.00],   // 1000 * 0.05 * 1.0
            ['silver', 1.2, 60.00],  // 1000 * 0.05 * 1.2
            ['gold', 1.5, 75.00],    // 1000 * 0.05 * 1.5
            ['platinum', 2.0, 100.00], // 1000 * 0.05 * 2.0
        ];

        foreach ($testCases as [$tier, $multiplier, $expectedCredits]) {
            $user = User::factory()->create([
                'loyalty_credits' => 0,
                'total_spent' => 0,
                'loyalty_tier' => $tier,
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
            $this->assertEquals($expectedCredits, $user->loyalty_credits, "Credits for {$tier} tier");
            $this->assertEquals(1000.00, $user->total_spent);

            // Check loyalty transaction
            $this->assertDatabaseHas('loyalty_transactions', [
                'user_id' => $user->id,
                'preorder_id' => $preorder->id,
                'transaction_type' => 'earned',
                'amount' => $expectedCredits,
            ]);
        }
    }

    /** @test */
    public function it_prevents_duplicate_loyalty_credit_awards()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DELIVERED,
            'total_amount' => 1000.00,
        ]);

        // Create existing loyalty transaction
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'preorder_id' => $preorder->id,
            'transaction_type' => 'earned',
            'amount' => 60.00,
        ]);

        $initialCredits = $this->user->loyalty_credits;

        $preorder->awardLoyaltyCredits();

        $this->user->refresh();
        $this->assertEquals($initialCredits, $this->user->loyalty_credits);
    }

    /** @test */
    public function it_formats_currency_amounts_correctly()
    {
        $preorder = PreOrder::factory()->create([
            'total_amount' => 12345.67,
            'deposit_amount' => 3703.70,
            'remaining_amount' => 8641.97,
        ]);

        $this->assertEquals('₱12,345.67', $preorder->getFormattedTotalAttribute());
        $this->assertEquals('₱3,703.70', $preorder->getFormattedDepositAttribute());
        $this->assertEquals('₱8,641.97', $preorder->getFormattedRemainingAttribute());
    }

    /** @test */
    public function it_generates_unique_preorder_numbers()
    {
        $numbers = [];
        
        for ($i = 0; $i < 10; $i++) {
            $number = PreOrder::generatePreOrderNumber();
            $this->assertStringStartsWith('PO', $number);
            $this->assertEquals(12, strlen($number)); // PO + 8 digit date + 4 digit random
            $this->assertNotContains($number, $numbers);
            $numbers[] = $number;
        }
    }

    /** @test */
    public function it_handles_edge_case_calculations()
    {
        // Test with very small amounts
        $product = Product::factory()->create(['current_price' => 0.01]);
        $preorder = PreOrder::factory()->create([
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $preorder->calculateAmounts(0.3);
        $this->assertEquals(0.01, $preorder->total_amount);
        $this->assertEquals(0.00, $preorder->deposit_amount); // Rounded down
        $this->assertEquals(0.01, $preorder->remaining_amount);

        // Test with large amounts
        $expensiveProduct = Product::factory()->create(['current_price' => 999999.99]);
        $expensivePreorder = PreOrder::factory()->create([
            'product_id' => $expensiveProduct->id,
            'quantity' => 1,
        ]);

        $expensivePreorder->calculateAmounts(0.3);
        $this->assertEquals(999999.99, $expensivePreorder->total_amount);
        $this->assertEquals(300000.00, $expensivePreorder->deposit_amount); // 999999.99 * 0.3 rounded
        $this->assertEquals(699999.99, $expensivePreorder->remaining_amount);
    }

    /** @test */
    public function it_handles_zero_quantity_edge_case()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 0,
        ]);

        $preorder->calculateAmounts();
        $this->assertEquals(0.00, $preorder->total_amount);
        $this->assertEquals(0.00, $preorder->deposit_amount);
        $this->assertEquals(0.00, $preorder->remaining_amount);
    }
}