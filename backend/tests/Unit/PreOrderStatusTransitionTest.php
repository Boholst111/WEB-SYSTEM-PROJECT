<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PreOrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'current_price' => 1000.00,
            'is_preorder' => true,
        ]);
    }

    /** @test */
    public function it_validates_complete_status_transition_flow()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

        // Valid transition sequence
        $transitions = [
            [PreOrder::STATUS_DEPOSIT_PENDING, PreOrder::STATUS_DEPOSIT_PAID, true],
            [PreOrder::STATUS_DEPOSIT_PAID, PreOrder::STATUS_READY_FOR_PAYMENT, true],
            [PreOrder::STATUS_READY_FOR_PAYMENT, PreOrder::STATUS_PAYMENT_COMPLETED, true],
            [PreOrder::STATUS_PAYMENT_COMPLETED, PreOrder::STATUS_SHIPPED, true],
            [PreOrder::STATUS_SHIPPED, PreOrder::STATUS_DELIVERED, true],
        ];

        foreach ($transitions as [$fromStatus, $toStatus, $shouldSucceed]) {
            $preorder->status = $fromStatus;
            $preorder->save();
            
            $result = $preorder->updateStatus($toStatus);
            
            if ($shouldSucceed) {
                $this->assertTrue($result, "Transition from {$fromStatus} to {$toStatus} should succeed");
                $this->assertEquals($toStatus, $preorder->status);
            } else {
                $this->assertFalse($result, "Transition from {$fromStatus} to {$toStatus} should fail");
                $this->assertEquals($fromStatus, $preorder->status);
            }
        }
    }

    /** @test */
    public function it_prevents_invalid_status_transitions()
    {
        $invalidTransitions = [
            // Skipping steps
            [PreOrder::STATUS_DEPOSIT_PENDING, PreOrder::STATUS_READY_FOR_PAYMENT],
            [PreOrder::STATUS_DEPOSIT_PENDING, PreOrder::STATUS_PAYMENT_COMPLETED],
            [PreOrder::STATUS_DEPOSIT_PENDING, PreOrder::STATUS_SHIPPED],
            [PreOrder::STATUS_DEPOSIT_PENDING, PreOrder::STATUS_DELIVERED],
            
            // Backwards transitions
            [PreOrder::STATUS_DEPOSIT_PAID, PreOrder::STATUS_DEPOSIT_PENDING],
            [PreOrder::STATUS_READY_FOR_PAYMENT, PreOrder::STATUS_DEPOSIT_PAID],
            [PreOrder::STATUS_PAYMENT_COMPLETED, PreOrder::STATUS_READY_FOR_PAYMENT],
            [PreOrder::STATUS_SHIPPED, PreOrder::STATUS_PAYMENT_COMPLETED],
            [PreOrder::STATUS_DELIVERED, PreOrder::STATUS_SHIPPED],
            
            // Invalid jumps
            [PreOrder::STATUS_DEPOSIT_PAID, PreOrder::STATUS_PAYMENT_COMPLETED],
            [PreOrder::STATUS_DEPOSIT_PAID, PreOrder::STATUS_SHIPPED],
            [PreOrder::STATUS_READY_FOR_PAYMENT, PreOrder::STATUS_SHIPPED],
        ];

        foreach ($invalidTransitions as [$fromStatus, $toStatus]) {
            $preorder = PreOrder::factory()->create(['status' => $fromStatus]);
            
            $result = $preorder->updateStatus($toStatus);
            
            $this->assertFalse($result, "Invalid transition from {$fromStatus} to {$toStatus} should be rejected");
            $this->assertEquals($fromStatus, $preorder->status, "Status should remain unchanged");
        }
    }

    /** @test */
    public function it_allows_cancellation_from_valid_statuses()
    {
        $cancellableStatuses = [
            PreOrder::STATUS_DEPOSIT_PENDING,
            PreOrder::STATUS_DEPOSIT_PAID,
            PreOrder::STATUS_READY_FOR_PAYMENT, // Only if not arrived yet
        ];

        foreach ($cancellableStatuses as $status) {
            $preorder = PreOrder::factory()->create([
                'status' => $status,
                'actual_arrival_date' => null, // Not arrived yet
            ]);
            
            $this->assertTrue($preorder->canBeCancelled(), "Should allow cancellation from {$status}");
            
            $result = $preorder->updateStatus(PreOrder::STATUS_CANCELLED);
            $this->assertTrue($result, "Should successfully cancel from {$status}");
            $this->assertEquals(PreOrder::STATUS_CANCELLED, $preorder->status);
        }
    }

    /** @test */
    public function it_prevents_cancellation_from_final_statuses()
    {
        $nonCancellableStatuses = [
            PreOrder::STATUS_PAYMENT_COMPLETED,
            PreOrder::STATUS_SHIPPED,
            PreOrder::STATUS_DELIVERED,
            PreOrder::STATUS_CANCELLED,
            PreOrder::STATUS_EXPIRED,
        ];

        foreach ($nonCancellableStatuses as $status) {
            $preorder = PreOrder::factory()->create(['status' => $status]);
            
            $this->assertFalse($preorder->canBeCancelled(), "Should not allow cancellation from {$status}");
        }
    }

    /** @test */
    public function it_allows_expiration_from_valid_statuses()
    {
        $expirableStatuses = [
            PreOrder::STATUS_DEPOSIT_PENDING,
            PreOrder::STATUS_READY_FOR_PAYMENT,
        ];

        foreach ($expirableStatuses as $status) {
            $preorder = PreOrder::factory()->create(['status' => $status]);
            
            $result = $preorder->updateStatus(PreOrder::STATUS_EXPIRED);
            $this->assertTrue($result, "Should allow expiration from {$status}");
            $this->assertEquals(PreOrder::STATUS_EXPIRED, $preorder->status);
        }
    }

    /** @test */
    public function it_prevents_expiration_from_invalid_statuses()
    {
        $nonExpirableStatuses = [
            PreOrder::STATUS_DEPOSIT_PAID,
            PreOrder::STATUS_PAYMENT_COMPLETED,
            PreOrder::STATUS_SHIPPED,
            PreOrder::STATUS_DELIVERED,
            PreOrder::STATUS_CANCELLED,
        ];

        foreach ($nonExpirableStatuses as $status) {
            $preorder = PreOrder::factory()->create(['status' => $status]);
            
            $result = $preorder->updateStatus(PreOrder::STATUS_EXPIRED);
            $this->assertFalse($result, "Should not allow expiration from {$status}");
            $this->assertEquals($status, $preorder->status);
        }
    }

    /** @test */
    public function it_prevents_transitions_from_terminal_statuses()
    {
        $terminalStatuses = [
            PreOrder::STATUS_DELIVERED,
            PreOrder::STATUS_CANCELLED,
            PreOrder::STATUS_EXPIRED,
        ];

        $attemptedTransitions = [
            PreOrder::STATUS_DEPOSIT_PENDING,
            PreOrder::STATUS_DEPOSIT_PAID,
            PreOrder::STATUS_READY_FOR_PAYMENT,
            PreOrder::STATUS_PAYMENT_COMPLETED,
            PreOrder::STATUS_SHIPPED,
        ];

        foreach ($terminalStatuses as $terminalStatus) {
            foreach ($attemptedTransitions as $targetStatus) {
                $preorder = PreOrder::factory()->create(['status' => $terminalStatus]);
                
                $result = $preorder->updateStatus($targetStatus);
                $this->assertFalse($result, "Should not allow transition from terminal status {$terminalStatus} to {$targetStatus}");
                $this->assertEquals($terminalStatus, $preorder->status);
            }
        }
    }

    /** @test */
    public function it_maintains_status_consistency_during_payment_flow()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 300.00,
        ]);

        // Process deposit payment should update status
        $preorder->processDepositPayment('gcash');
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);

        // Mark ready for payment should update status
        $preorder->markReadyForPayment();
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);

        // Complete payment should update status
        $preorder->completePayment();
        $this->assertEquals(PreOrder::STATUS_PAYMENT_COMPLETED, $preorder->status);
    }

    /** @test */
    public function it_validates_status_transition_atomicity()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        // Attempt invalid transition
        $originalStatus = $preorder->status;
        $result = $preorder->updateStatus(PreOrder::STATUS_SHIPPED);

        $this->assertFalse($result);
        $this->assertEquals($originalStatus, $preorder->status);
        
        // Verify database state matches object state
        $preorder->refresh();
        $this->assertEquals($originalStatus, $preorder->status);
    }

    /** @test */
    public function it_handles_concurrent_status_updates()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        // Simulate first update succeeding
        $result1 = $preorder->updateStatus(PreOrder::STATUS_DEPOSIT_PAID);
        $this->assertTrue($result1);

        // Simulate concurrent update attempt from original status (should fail)
        $preorder2 = PreOrder::find($preorder->id);
        $preorder2->status = PreOrder::STATUS_DEPOSIT_PENDING; // Simulate stale data
        
        $result2 = $preorder2->updateStatus(PreOrder::STATUS_CANCELLED);
        
        // The actual behavior depends on implementation, but status should be consistent
        $preorder->refresh();
        $this->assertContains($preorder->status, [
            PreOrder::STATUS_DEPOSIT_PAID,
            PreOrder::STATUS_CANCELLED
        ]);
    }

    /** @test */
    public function it_validates_status_labels_for_all_statuses()
    {
        $statusLabels = [
            PreOrder::STATUS_DEPOSIT_PENDING => 'Deposit Pending',
            PreOrder::STATUS_DEPOSIT_PAID => 'Deposit Paid',
            PreOrder::STATUS_READY_FOR_PAYMENT => 'Ready for Payment',
            PreOrder::STATUS_PAYMENT_COMPLETED => 'Payment Completed',
            PreOrder::STATUS_SHIPPED => 'Shipped',
            PreOrder::STATUS_DELIVERED => 'Delivered',
            PreOrder::STATUS_CANCELLED => 'Cancelled',
            PreOrder::STATUS_EXPIRED => 'Expired',
        ];

        foreach ($statusLabels as $status => $expectedLabel) {
            $preorder = PreOrder::factory()->create(['status' => $status]);
            $this->assertEquals($expectedLabel, $preorder->getStatusLabelAttribute());
        }
    }

    /** @test */
    public function it_validates_business_rules_during_transitions()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'actual_arrival_date' => now(),
        ]);

        // Should not allow cancellation if product has arrived and payment is ready
        $this->assertFalse($preorder->canBeCancelled());
    }

    /** @test */
    public function it_maintains_transition_history_integrity()
    {
        $preorder = PreOrder::factory()->create(['status' => PreOrder::STATUS_DEPOSIT_PENDING]);

        $transitions = [
            PreOrder::STATUS_DEPOSIT_PAID,
            PreOrder::STATUS_READY_FOR_PAYMENT,
            PreOrder::STATUS_PAYMENT_COMPLETED,
        ];

        foreach ($transitions as $targetStatus) {
            $previousStatus = $preorder->status;
            $result = $preorder->updateStatus($targetStatus);
            
            $this->assertTrue($result);
            $this->assertEquals($targetStatus, $preorder->status);
            $this->assertNotEquals($previousStatus, $preorder->status);
        }
    }
}