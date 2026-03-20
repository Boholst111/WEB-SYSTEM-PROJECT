<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\PreOrder;
use App\Models\Payment;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Feature: diecast-empire, Property 2: Pre-order payment flow integrity**
 * **Validates: Requirements 1.3**
 * 
 * Property: For any pre-order transaction, the sum of deposit amount and remaining amount 
 * should always equal the total product price, and payment status transitions should follow 
 * the defined workflow (deposit_pending → deposit_paid → ready_for_payment → payment_completed).
 */
class PreOrderPaymentFlowIntegrityPropertyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function preorder_payment_amounts_always_sum_to_total_price()
    {
        // Test with various price and deposit percentage combinations
        $testCases = [
            ['price' => 100.00, 'quantity' => 1, 'deposit_pct' => 0.3],
            ['price' => 250.50, 'quantity' => 2, 'deposit_pct' => 0.4],
            ['price' => 75.99, 'quantity' => 3, 'deposit_pct' => 0.5],
            ['price' => 1000.00, 'quantity' => 1, 'deposit_pct' => 0.25],
            ['price' => 45.75, 'quantity' => 4, 'deposit_pct' => 0.6],
        ];

        foreach ($testCases as $case) {
            // Setup
            $brand = Brand::factory()->create();
            $category = Category::factory()->create();
            $user = User::factory()->create();
            
            $product = Product::factory()->create([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'current_price' => $case['price'],
                'is_preorder' => true,
                'preorder_date' => now()->addMonth(),
            ]);

            // Create pre-order
            $preorder = new PreOrder([
                'preorder_number' => PreOrder::generatePreOrderNumber(),
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $case['quantity'],
                'status' => 'deposit_pending',
            ]);

            // Calculate amounts using the specified deposit percentage
            $preorder->calculateAmounts($case['deposit_pct']);
            $preorder->save();

            // Property: deposit + remaining = total price
            $expectedTotal = $case['price'] * $case['quantity'];
            $actualTotal = $preorder->deposit_amount + $preorder->remaining_amount;
            
            $this->assertEquals(
                round($expectedTotal, 2),
                round($actualTotal, 2),
                "Deposit ({$preorder->deposit_amount}) + Remaining ({$preorder->remaining_amount}) " .
                "should equal Total ({$expectedTotal}) for price {$case['price']}, quantity {$case['quantity']}, " .
                "deposit {$case['deposit_pct']}%"
            );

            // Property: deposit should be within expected range
            $expectedDeposit = round($expectedTotal * $case['deposit_pct'], 2);
            $this->assertEquals(
                $expectedDeposit,
                round($preorder->deposit_amount, 2),
                "Deposit amount should be {$case['deposit_pct']}% of total"
            );

            // Property: remaining should be the difference
            $expectedRemaining = round($expectedTotal - $expectedDeposit, 2);
            $this->assertEquals(
                $expectedRemaining,
                round($preorder->remaining_amount, 2),
                "Remaining amount should be total minus deposit"
            );
        }
    }

    /** @test */
    public function preorder_status_transitions_follow_defined_workflow()
    {
        $paymentMethods = ['gcash', 'maya', 'bank_transfer'];
        
        foreach ($paymentMethods as $paymentMethod) {
            // Setup
            $brand = Brand::factory()->create();
            $category = Category::factory()->create();
            $user = User::factory()->create();
            
            $product = Product::factory()->create([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'current_price' => 1000.00,
                'is_preorder' => true,
                'preorder_date' => now()->addMonth(),
            ]);

            // Create pre-order in initial state
            $preorder = PreOrder::factory()->depositPending()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'deposit_amount' => 300.00,
                'remaining_amount' => 700.00,
            ]);

            // Property: Initial state should be deposit_pending
            $this->assertEquals('deposit_pending', $preorder->status);
            $this->assertFalse($preorder->isDepositPaid());
            $this->assertFalse($preorder->isPaymentCompleted());

            // Transition 1: deposit_pending → deposit_paid
            $depositPaymentSuccess = $preorder->processDepositPayment($paymentMethod);
            $this->assertTrue($depositPaymentSuccess, 'Deposit payment should succeed');
            
            $preorder->refresh();
            $this->assertEquals('deposit_paid', $preorder->status);
            $this->assertTrue($preorder->isDepositPaid());
            $this->assertFalse($preorder->isPaymentCompleted());
            $this->assertNotNull($preorder->deposit_paid_at);

            // Transition 2: deposit_paid → ready_for_payment (when product arrives)
            $preorder->actual_arrival_date = now();
            $readyForPaymentSuccess = $preorder->markReadyForPayment();
            $this->assertTrue($readyForPaymentSuccess, 'Mark ready for payment should succeed');
            
            $preorder->refresh();
            $this->assertEquals('ready_for_payment', $preorder->status);
            $this->assertTrue($preorder->isDepositPaid());
            $this->assertFalse($preorder->isPaymentCompleted());

            // Transition 3: ready_for_payment → payment_completed
            $completionSuccess = $preorder->completePayment();
            $this->assertTrue($completionSuccess, 'Payment completion should succeed');
            
            $preorder->refresh();
            $this->assertEquals('payment_completed', $preorder->status);
            $this->assertTrue($preorder->isDepositPaid());
            $this->assertTrue($preorder->isPaymentCompleted());

            // Property: No further transitions should be possible from payment_completed
            $this->assertFalse($preorder->canBeCancelled());
        }
    }

    /** @test */
    public function preorder_payment_records_maintain_integrity()
    {
        $testCases = [
            ['deposit' => 300.00, 'remaining' => 700.00, 'method' => 'gcash'],
            ['deposit' => 150.00, 'remaining' => 350.00, 'method' => 'maya'],
            ['deposit' => 500.00, 'remaining' => 1000.00, 'method' => 'bank_transfer'],
        ];

        foreach ($testCases as $case) {
            // Setup
            $brand = Brand::factory()->create();
            $category = Category::factory()->create();
            $user = User::factory()->create();
            
            $totalAmount = $case['deposit'] + $case['remaining'];
            
            $product = Product::factory()->create([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'current_price' => $totalAmount,
                'is_preorder' => true,
            ]);

            $preorder = PreOrder::factory()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'status' => 'deposit_pending',
                'deposit_amount' => $case['deposit'],
                'remaining_amount' => $case['remaining'],
            ]);

            // Create deposit payment
            $depositPayment = Payment::factory()->create([
                'payment_id' => 'PAY_' . uniqid(),
                'preorder_id' => $preorder->id,
                'user_id' => $user->id,
                'payment_method' => $case['method'],
                'payment_type' => 'deposit',
                'amount' => $case['deposit'],
                'status' => Payment::STATUS_COMPLETED,
            ]);

            // Update pre-order status
            $preorder->processDepositPayment($case['method']);
            $preorder->markReadyForPayment();

            // Create final payment
            $finalPayment = Payment::factory()->create([
                'payment_id' => 'PAY_' . uniqid(),
                'preorder_id' => $preorder->id,
                'user_id' => $user->id,
                'payment_method' => $case['method'],
                'payment_type' => 'remaining_payment',
                'amount' => $case['remaining'],
                'status' => Payment::STATUS_COMPLETED,
            ]);

            $preorder->completePayment();

            // Property: Total payments should equal total pre-order amount
            $totalPaid = $preorder->payments()->where('status', Payment::STATUS_COMPLETED)->sum('amount');
            $this->assertEquals(
                round($totalAmount, 2),
                round($totalPaid, 2),
                "Total payments ({$totalPaid}) should equal pre-order total ({$totalAmount})"
            );

            // Property: Payment count should be exactly 2 for completed pre-order
            $completedPayments = $preorder->payments()->where('status', Payment::STATUS_COMPLETED)->count();
            $this->assertEquals(2, $completedPayments, 'Should have exactly 2 completed payments');

            // Property: Each payment should reference the correct pre-order
            foreach ($preorder->payments as $payment) {
                $this->assertEquals($preorder->id, $payment->preorder_id);
                $this->assertNotNull($payment->payment_method);
                $this->assertGreaterThan(0, $payment->amount);
            }
        }
    }

    /** @test */
    public function preorder_cancellation_rules_are_enforced()
    {
        $testCases = [
            ['status' => 'deposit_pending', 'arrived' => false, 'should_cancel' => true],
            ['status' => 'deposit_paid', 'arrived' => false, 'should_cancel' => true],
            ['status' => 'ready_for_payment', 'arrived' => false, 'should_cancel' => true],
            ['status' => 'ready_for_payment', 'arrived' => true, 'should_cancel' => false],
            ['status' => 'payment_completed', 'arrived' => true, 'should_cancel' => false],
            ['status' => 'cancelled', 'arrived' => false, 'should_cancel' => false],
        ];

        foreach ($testCases as $case) {
            // Setup
            $brand = Brand::factory()->create();
            $category = Category::factory()->create();
            $user = User::factory()->create();
            
            $product = Product::factory()->create([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'is_preorder' => true,
            ]);

            $preorder = PreOrder::factory()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'status' => $case['status'],
                'actual_arrival_date' => $case['arrived'] ? now() : null,
            ]);

            // Property: Cancellation rules based on status and arrival
            $canBeCancelled = $preorder->canBeCancelled();
            
            if ($case['should_cancel']) {
                $this->assertTrue($canBeCancelled, 
                    "Pre-order with status '{$case['status']}' and arrival status " . 
                    ($case['arrived'] ? 'arrived' : 'not arrived') . " should be cancellable");
            } else {
                $this->assertFalse($canBeCancelled, 
                    "Pre-order with status '{$case['status']}' and arrival status " . 
                    ($case['arrived'] ? 'arrived' : 'not arrived') . " should not be cancellable");
            }
        }
    }
}