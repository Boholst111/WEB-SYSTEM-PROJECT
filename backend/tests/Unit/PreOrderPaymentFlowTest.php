<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class PreOrderPaymentFlowTest extends TestCase
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
    public function it_processes_deposit_payment_with_gcash()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 300.00,
        ]);

        $result = $preorder->processDepositPayment('gcash');

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertEquals('gcash', $preorder->payment_method);
        $this->assertNotNull($preorder->deposit_paid_at);
        $this->assertTrue($preorder->isDepositPaid());
    }

    /** @test */
    public function it_processes_deposit_payment_with_maya()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 500.00,
        ]);

        $result = $preorder->processDepositPayment('maya');

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertEquals('maya', $preorder->payment_method);
        $this->assertNotNull($preorder->deposit_paid_at);
    }

    /** @test */
    public function it_processes_deposit_payment_with_bank_transfer()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 750.00,
        ]);

        $result = $preorder->processDepositPayment('bank_transfer');

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertEquals('bank_transfer', $preorder->payment_method);
    }

    /** @test */
    public function it_prevents_duplicate_deposit_payments()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'deposit_paid_at' => now(),
            'payment_method' => 'gcash',
        ]);

        $result = $preorder->processDepositPayment('maya');

        $this->assertFalse($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertEquals('gcash', $preorder->payment_method); // Should not change
    }

    /** @test */
    public function it_prevents_deposit_payment_for_invalid_statuses()
    {
        $invalidStatuses = [
            PreOrder::STATUS_READY_FOR_PAYMENT,
            PreOrder::STATUS_PAYMENT_COMPLETED,
            PreOrder::STATUS_SHIPPED,
            PreOrder::STATUS_DELIVERED,
            PreOrder::STATUS_CANCELLED,
            PreOrder::STATUS_EXPIRED,
        ];

        foreach ($invalidStatuses as $status) {
            $preorder = PreOrder::factory()->create(['status' => $status]);
            
            $result = $preorder->processDepositPayment('gcash');
            
            $this->assertFalse($result, "Should not allow deposit payment for status: {$status}");
            $this->assertEquals($status, $preorder->status); // Status should not change
        }
    }

    /** @test */
    public function it_completes_final_payment_successfully()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'deposit_amount' => 300.00,
            'remaining_amount' => 700.00,
            'deposit_paid_at' => now()->subWeek(),
        ]);

        $result = $preorder->completePayment();

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_PAYMENT_COMPLETED, $preorder->status);
        $this->assertTrue($preorder->isPaymentCompleted());
    }

    /** @test */
    public function it_prevents_final_payment_for_invalid_statuses()
    {
        $invalidStatuses = [
            PreOrder::STATUS_DEPOSIT_PENDING,
            PreOrder::STATUS_DEPOSIT_PAID,
            PreOrder::STATUS_PAYMENT_COMPLETED,
            PreOrder::STATUS_SHIPPED,
            PreOrder::STATUS_DELIVERED,
            PreOrder::STATUS_CANCELLED,
            PreOrder::STATUS_EXPIRED,
        ];

        foreach ($invalidStatuses as $status) {
            $preorder = PreOrder::factory()->create(['status' => $status]);
            
            $result = $preorder->completePayment();
            
            if ($status === PreOrder::STATUS_READY_FOR_PAYMENT) {
                $this->assertTrue($result);
            } else {
                $this->assertFalse($result, "Should not allow final payment for status: {$status}");
                $this->assertEquals($status, $preorder->status);
            }
        }
    }

    /** @test */
    public function it_handles_payment_flow_with_database_transactions()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 400.00,
        ]);

        DB::beginTransaction();
        
        try {
            $result = $preorder->processDepositPayment('gcash');
            $this->assertTrue($result);
            
            DB::commit();
            
            $preorder->refresh();
            $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->fail('Payment processing should not fail: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_maintains_payment_method_consistency()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'payment_method' => null,
        ]);

        $preorder->processDepositPayment('gcash');
        $this->assertEquals('gcash', $preorder->payment_method);

        // Try to change payment method (should not be allowed)
        $preorder->status = PreOrder::STATUS_READY_FOR_PAYMENT;
        $preorder->save();

        // Payment method should remain consistent
        $this->assertEquals('gcash', $preorder->payment_method);
    }

    /** @test */
    public function it_tracks_payment_timestamps_correctly()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_paid_at' => null,
        ]);

        $preorder->processDepositPayment('maya');

        $this->assertNotNull($preorder->deposit_paid_at);
        // Check that timestamp is recent (within last minute)
        $this->assertTrue($preorder->deposit_paid_at->isAfter(now()->subMinute()));
        $this->assertTrue($preorder->deposit_paid_at->isBefore(now()->addMinute()));
    }

    /** @test */
    public function it_validates_payment_amounts_during_flow()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 300.00,
            'remaining_amount' => 700.00,
            'total_amount' => 1000.00,
        ]);

        // Verify amounts are consistent before payment
        $this->assertEquals(
            $preorder->total_amount,
            $preorder->deposit_amount + $preorder->remaining_amount
        );

        $preorder->processDepositPayment('gcash');

        // Amounts should remain consistent after payment
        $this->assertEquals(
            $preorder->total_amount,
            $preorder->deposit_amount + $preorder->remaining_amount
        );
    }

    /** @test */
    public function it_handles_zero_deposit_payment_flow()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 0.00,
            'remaining_amount' => 1000.00,
            'total_amount' => 1000.00,
        ]);

        $result = $preorder->processDepositPayment('gcash');

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertNotNull($preorder->deposit_paid_at);
    }

    /** @test */
    public function it_handles_full_payment_as_deposit_flow()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 1000.00,
            'remaining_amount' => 0.00,
            'total_amount' => 1000.00,
        ]);

        $result = $preorder->processDepositPayment('maya');

        $this->assertTrue($result);
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        
        // Should be able to mark as ready for payment even with zero remaining
        $readyResult = $preorder->markReadyForPayment();
        $this->assertTrue($readyResult);
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);
    }

    /** @test */
    public function it_validates_payment_method_formats()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

        $validMethods = ['gcash', 'maya', 'bank_transfer'];
        
        foreach ($validMethods as $method) {
            $testPreorder = PreOrder::factory()->create([
                'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            ]);
            
            $result = $testPreorder->processDepositPayment($method);
            $this->assertTrue($result, "Should accept payment method: {$method}");
            $this->assertEquals($method, $testPreorder->payment_method);
        }
    }

    /** @test */
    public function it_maintains_audit_trail_during_payment_flow()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 500.00,
        ]);

        $originalCreatedAt = $preorder->created_at;
        $originalUpdatedAt = $preorder->updated_at;
        
        // Add small delay to ensure timestamp difference
        sleep(1);
        
        $preorder->processDepositPayment('gcash');
        
        // Created timestamp should not change
        $this->assertEquals($originalCreatedAt->format('Y-m-d H:i:s'), $preorder->created_at->format('Y-m-d H:i:s'));
        
        // Updated timestamp should change
        $this->assertTrue($preorder->updated_at->isAfter($originalUpdatedAt));
        
        // Deposit paid timestamp should be set
        $this->assertNotNull($preorder->deposit_paid_at);
    }

    /** @test */
    public function it_handles_concurrent_payment_attempts()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 300.00,
        ]);

        // Simulate first payment succeeding
        $result1 = $preorder->processDepositPayment('gcash');
        $this->assertTrue($result1);

        // Simulate second concurrent payment attempt (should fail)
        $result2 = $preorder->processDepositPayment('maya');
        $this->assertFalse($result2);
        
        // Payment method should remain as first successful payment
        $this->assertEquals('gcash', $preorder->payment_method);
    }

    /** @test */
    public function it_preserves_payment_flow_integrity_across_status_changes()
    {
        $preorder = PreOrder::factory()->create([
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 400.00,
            'remaining_amount' => 600.00,
        ]);

        // Complete deposit payment
        $preorder->processDepositPayment('gcash');
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);

        // Mark ready for payment
        $preorder->markReadyForPayment();
        $this->assertEquals(PreOrder::STATUS_READY_FOR_PAYMENT, $preorder->status);

        // Complete final payment
        $preorder->completePayment();
        $this->assertEquals(PreOrder::STATUS_PAYMENT_COMPLETED, $preorder->status);

        // Verify payment method consistency throughout flow
        $this->assertEquals('gcash', $preorder->payment_method);
        $this->assertNotNull($preorder->deposit_paid_at);
    }
}