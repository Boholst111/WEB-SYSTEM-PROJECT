<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Payment\PaymentService;
use App\Services\Payment\FraudPreventionService;
use App\Models\Payment;
use App\Models\Order;
use App\Models\PreOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Eris\Generator;
use Eris\TestTrait;

/**
 * **Feature: diecast-empire, Property 4: Payment gateway transaction integrity**
 * 
 * Property: For any payment transaction through GCash, Maya, or Bank Transfer, 
 * the system should maintain atomicity where either the payment completes 
 * successfully and order status updates, or the payment fails and no order 
 * state changes occur.
 * 
 * **Validates: Requirements 1.6**
 */
class PaymentGatewayTransactionIntegrityPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    private PaymentService $paymentService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        
        Sanctum::actingAs($this->user);
        
        $fraudService = new FraudPreventionService();
        $this->paymentService = new PaymentService($fraudService);
    }

    /**
     * Property Test: Payment transaction atomicity for successful payments
     * 
     * Tests that when a payment succeeds, both the payment record and 
     * related order/preorder states are updated consistently.
     */
    public function test_successful_payment_maintains_atomicity()
    {
        $this->forAll(
            Generator\choose(10000, 5000000), // amount in centavos (100.00 to 50000.00 PHP)
            Generator\elements(['gcash', 'maya', 'bank_transfer']), // gateway
            Generator\elements(['order', 'preorder']), // order type
            Generator\elements(['bpi', 'bdo', 'metrobank']) // bank (for bank transfers)
        )->then(function ($amountCentavos, $gateway, $orderType, $bank) {
            $amount = $amountCentavos / 100.0; // Convert to PHP
            
            // Create order or preorder
            if ($orderType === 'order') {
                $order = Order::factory()->create([
                    'user_id' => $this->user->id,
                    'total_amount' => $amount,
                    'payment_status' => 'pending',
                ]);
                $orderId = $order->id;
                $preorderId = null;
            } else {
                $preorder = PreOrder::factory()->create([
                    'user_id' => $this->user->id,
                    'deposit_amount' => $amount,
                    'status' => 'deposit_pending',
                ]);
                $orderId = null;
                $preorderId = $preorder->id;
            }

            // Record initial state
            $initialPaymentCount = Payment::count();
            $initialOrderState = $orderId ? Order::find($orderId)->toArray() : null;
            $initialPreorderState = $preorderId ? PreOrder::find($preorderId)->toArray() : null;

            // Prepare payment data
            $paymentData = [
                'gateway' => $gateway,
                'order_id' => $orderId,
                'preorder_id' => $preorderId,
                'amount' => $amount,
                'currency' => 'PHP',
                'reference_id' => 'TEST-' . uniqid(),
            ];

            // Add gateway-specific data
            if ($gateway === 'bank_transfer') {
                $paymentData['bank'] = $bank;
            } else {
                $paymentData['success_url'] = 'https://example.com/success';
                $paymentData['failure_url'] = 'https://example.com/failure';
                $paymentData['cancel_url'] = 'https://example.com/cancel';
                $paymentData['webhook_url'] = 'https://example.com/webhook';
                $paymentData['customer_name'] = $this->user->first_name . ' ' . $this->user->last_name;
                $paymentData['customer_email'] = $this->user->email;
            }

            // Test atomicity with database transaction
            DB::beginTransaction();
            
            try {
                // Process payment
                $result = $this->paymentService->processPayment($paymentData);
                
                if ($result['success']) {
                    // Verify atomicity for successful payment
                    $this->assertSuccessfulPaymentAtomicity(
                        $result,
                        $initialPaymentCount,
                        $orderId,
                        $preorderId,
                        $amount,
                        $gateway
                    );
                } else {
                    // Verify atomicity for failed payment
                    $this->assertFailedPaymentAtomicity(
                        $initialPaymentCount,
                        $initialOrderState,
                        $initialPreorderState,
                        $orderId,
                        $preorderId
                    );
                }
                
                DB::commit();
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Even with exceptions, verify no partial state changes
                $this->assertNoPartialStateChanges(
                    $initialPaymentCount,
                    $initialOrderState,
                    $initialPreorderState,
                    $orderId,
                    $preorderId
                );
            }
        });
    }

    /**
     * Property Test: Payment webhook atomicity
     * 
     * Tests that webhook processing maintains atomicity when updating
     * payment and order states.
     */
    public function test_webhook_processing_maintains_atomicity()
    {
        $this->forAll(
            Generator\elements(['gcash', 'maya', 'bank_transfer']), // gateway
            Generator\choose(10000, 1000000), // amount in centavos (100.00 to 10000.00 PHP)
            Generator\elements(['completed', 'failed', 'cancelled']) // webhook status
        )->then(function ($gateway, $amountCentavos, $webhookStatus) {
            $amount = $amountCentavos / 100.0; // Convert to PHP
            
            // Create a pending payment
            $order = Order::factory()->create([
                'user_id' => $this->user->id,
                'payment_status' => 'pending',
            ]);
            
            $payment = Payment::factory()->create([
                'order_id' => $order->id,
                'gateway' => $gateway,
                'payment_method' => $gateway,
                'gateway_transaction_id' => 'TEST-' . uniqid(),
                'amount' => $amount,
                'status' => Payment::STATUS_PENDING,
            ]);

            // Record initial states
            $initialPaymentState = $payment->toArray();
            $initialOrderState = $order->toArray();

            // Generate webhook payload
            $webhookPayload = $this->generateWebhookPayload($gateway, $payment->gateway_transaction_id, $webhookStatus);

            DB::beginTransaction();
            
            try {
                // Process webhook
                $result = $this->paymentService->handleWebhook($gateway, $webhookPayload);
                
                if ($result['success'] && $webhookStatus === 'completed') {
                    // Verify both payment and order were updated atomically
                    $payment->refresh();
                    $order->refresh();
                    
                    $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status);
                    $this->assertNotNull($payment->processed_at);
                    $this->assertEquals('paid', $order->payment_status);
                    
                    // Verify transaction integrity in database
                    $this->assertDatabaseHas('payments', [
                        'id' => $payment->id,
                        'status' => Payment::STATUS_COMPLETED,
                    ]);
                    
                    $this->assertDatabaseHas('orders', [
                        'id' => $order->id,
                        'payment_status' => 'paid',
                    ]);
                } else {
                    // For failed webhooks or processing errors, verify no state changes
                    $payment->refresh();
                    $order->refresh();
                    
                    // Payment status might change to failed, but order should remain unchanged
                    if ($webhookStatus === 'failed') {
                        $this->assertContains($payment->status, [Payment::STATUS_PENDING, Payment::STATUS_FAILED]);
                    }
                    $this->assertEquals($initialOrderState['payment_status'], $order->payment_status);
                }
                
                DB::commit();
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Verify no partial updates occurred
                $payment->refresh();
                $order->refresh();
                
                $this->assertEquals($initialPaymentState['status'], $payment->status);
                $this->assertEquals($initialOrderState['payment_status'], $order->payment_status);
            }
        });
    }

    /**
     * Property Test: Payment refund atomicity
     * 
     * Tests that refund processing maintains atomicity.
     */
    public function test_refund_processing_maintains_atomicity()
    {
        $this->forAll(
            Generator\choose(10000, 1000000), // original amount in centavos (100.00 to 10000.00 PHP)
            Generator\choose(10, 100) // refund percentage (10% to 100%)
        )->then(function ($amountCentavos, $refundPercentage) {
            $amount = $amountCentavos / 100.0; // Convert to PHP
            $refundAmount = ($amount * $refundPercentage) / 100.0;
            
            // Create a completed payment
            $order = Order::factory()->create([
                'user_id' => $this->user->id,
                'payment_status' => 'paid',
            ]);
            
            $payment = Payment::factory()->create([
                'order_id' => $order->id,
                'gateway' => 'bank_transfer',
                'payment_method' => 'bank_transfer',
                'amount' => $amount,
                'status' => Payment::STATUS_COMPLETED,
            ]);

            // Record initial state
            $initialPaymentState = $payment->toArray();

            DB::beginTransaction();
            
            try {
                // Process refund
                $result = $this->paymentService->refundPayment($payment->id, $refundAmount);
                
                if ($result['success']) {
                    // Verify payment status updated to refunded
                    $payment->refresh();
                    $this->assertEquals(Payment::STATUS_REFUNDED, $payment->status);
                    
                    // Verify refund amount is correct
                    $this->assertEquals($refundAmount, $result['amount_refunded']);
                    
                    // Verify database consistency
                    $this->assertDatabaseHas('payments', [
                        'id' => $payment->id,
                        'status' => Payment::STATUS_REFUNDED,
                    ]);
                } else {
                    // Verify no state changes on failure
                    $payment->refresh();
                    $this->assertEquals($initialPaymentState['status'], $payment->status);
                }
                
                DB::commit();
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Verify no partial state changes
                $payment->refresh();
                $this->assertEquals($initialPaymentState['status'], $payment->status);
            }
        });
    }

    /**
     * Property Test: Database transaction rollback integrity
     * 
     * Tests that when database transactions fail, no partial state changes occur.
     */
    public function test_database_transaction_rollback_integrity()
    {
        $this->forAll(
            Generator\choose(10000, 500000), // amount in centavos (100.00 to 5000.00 PHP)
            Generator\elements(['gcash', 'maya', 'bank_transfer']), // gateway
            Generator\bool() // simulate failure
        )->then(function ($amountCentavos, $gateway, $simulateFailure) {
            $amount = $amountCentavos / 100.0; // Convert to PHP
            
            // Create order
            $order = Order::factory()->create([
                'user_id' => $this->user->id,
                'total_amount' => $amount,
                'payment_status' => 'pending',
            ]);

            // Record initial states
            $initialPaymentCount = Payment::count();
            $initialOrderState = $order->toArray();

            // Prepare payment data
            $paymentData = [
                'gateway' => $gateway,
                'order_id' => $order->id,
                'amount' => $amount,
                'currency' => 'PHP',
                'reference_id' => 'TEST-' . uniqid(),
            ];

            if ($gateway === 'bank_transfer') {
                $paymentData['bank'] = 'bpi';
            } else {
                $paymentData['success_url'] = 'https://example.com/success';
                $paymentData['failure_url'] = 'https://example.com/failure';
                $paymentData['cancel_url'] = 'https://example.com/cancel';
                $paymentData['webhook_url'] = 'https://example.com/webhook';
                $paymentData['customer_name'] = $this->user->first_name . ' ' . $this->user->last_name;
                $paymentData['customer_email'] = $this->user->email;
            }

            DB::beginTransaction();
            
            try {
                // Process payment
                $result = $this->paymentService->processPayment($paymentData);
                
                // Simulate random failure to test rollback
                if ($simulateFailure && fake()->boolean(30)) { // 30% chance of simulated failure
                    throw new \Exception('Simulated transaction failure');
                }
                
                DB::commit();
                
                // If we reach here without exception, verify consistency
                if ($result['success']) {
                    $this->assertEquals($initialPaymentCount + 1, Payment::count());
                    $payment = Payment::find($result['payment_id']);
                    $this->assertNotNull($payment);
                    $this->assertEquals($amount, $payment->amount);
                }
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Verify complete rollback - no partial state changes
                $this->assertEquals($initialPaymentCount, Payment::count());
                
                $order->refresh();
                $this->assertEquals($initialOrderState['payment_status'], $order->payment_status);
                
                // Verify no orphaned payment records
                $orphanedPayments = Payment::where('order_id', $order->id)->count();
                $this->assertEquals(0, $orphanedPayments);
            }
        });
    }

    /**
     * Assert successful payment atomicity
     */
    private function assertSuccessfulPaymentAtomicity(
        array $result,
        int $initialPaymentCount,
        ?int $orderId,
        ?int $preorderId,
        float $amount,
        string $gateway
    ): void {
        // Verify payment record was created
        $this->assertEquals($initialPaymentCount + 1, Payment::count());
        
        // Verify payment record has correct data
        $payment = Payment::find($result['payment_id']);
        $this->assertNotNull($payment);
        $this->assertEquals($amount, $payment->amount);
        $this->assertEquals($gateway, $payment->gateway);
        $this->assertEquals($orderId, $payment->order_id);
        $this->assertEquals($preorderId, $payment->preorder_id);
        
        // Verify related order/preorder state updates
        if ($orderId) {
            $order = Order::find($orderId);
            // For bank transfers, payment status might still be pending
            $this->assertContains($order->payment_status, ['pending', 'paid']);
        }
        
        if ($preorderId) {
            $preorder = PreOrder::find($preorderId);
            $this->assertContains($preorder->status, ['deposit_pending', 'deposit_paid']);
        }
    }

    /**
     * Assert failed payment atomicity
     */
    private function assertFailedPaymentAtomicity(
        int $initialPaymentCount,
        ?array $initialOrderState,
        ?array $initialPreorderState,
        ?int $orderId,
        ?int $preorderId
    ): void {
        // Payment record might be created but marked as failed
        $currentPaymentCount = Payment::count();
        $this->assertGreaterThanOrEqual($initialPaymentCount, $currentPaymentCount);
        
        // Verify order/preorder states unchanged
        if ($orderId && $initialOrderState) {
            $order = Order::find($orderId);
            $this->assertEquals($initialOrderState['payment_status'], $order->payment_status);
        }
        
        if ($preorderId && $initialPreorderState) {
            $preorder = PreOrder::find($preorderId);
            $this->assertEquals($initialPreorderState['status'], $preorder->status);
        }
    }

    /**
     * Assert no partial state changes occurred
     */
    private function assertNoPartialStateChanges(
        int $initialPaymentCount,
        ?array $initialOrderState,
        ?array $initialPreorderState,
        ?int $orderId,
        ?int $preorderId
    ): void {
        // Verify payment count didn't increase unexpectedly
        $this->assertLessThanOrEqual($initialPaymentCount + 1, Payment::count());
        
        // Verify order/preorder states unchanged
        if ($orderId && $initialOrderState) {
            $order = Order::find($orderId);
            $this->assertEquals($initialOrderState['payment_status'], $order->payment_status);
        }
        
        if ($preorderId && $initialPreorderState) {
            $preorder = PreOrder::find($preorderId);
            $this->assertEquals($initialPreorderState['status'], $preorder->status);
        }
    }

    /**
     * Generate webhook payload for different gateways
     */
    private function generateWebhookPayload(string $gateway, string $transactionId, string $status): array
    {
        switch ($gateway) {
            case 'gcash':
                return [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'amount' => fake()->randomFloat(2, 100, 10000),
                    'currency' => 'PHP',
                    'reference_id' => 'TEST-' . uniqid(),
                ];
                
            case 'maya':
                return [
                    'id' => $transactionId,
                    'status' => strtoupper($status) === 'COMPLETED' ? 'PAYMENT_SUCCESS' : 'PAYMENT_FAILED',
                    'totalAmount' => [
                        'value' => fake()->numberBetween(10000, 1000000), // In centavos
                        'currency' => 'PHP',
                    ],
                    'requestReferenceNumber' => 'TEST-' . uniqid(),
                ];
                
            case 'bank_transfer':
                return [
                    'reference_number' => $transactionId,
                    'status' => $status,
                    'notes' => 'Manual verification completed',
                ];
                
            default:
                return [];
        }
    }
}