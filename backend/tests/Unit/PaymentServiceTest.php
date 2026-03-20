<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Payment\PaymentService;
use App\Services\Payment\FraudPreventionService;
use App\Models\Payment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Auth::login($this->user);
        
        // Disable fraud detection for tests
        config(['payments.security.fraud_detection' => false]);
        
        $fraudService = new FraudPreventionService();
        $this->paymentService = new PaymentService($fraudService);
    }

    public function test_can_get_available_payment_methods()
    {
        $methods = $this->paymentService->getAvailablePaymentMethods();
        
        $this->assertIsArray($methods);
        $this->assertNotEmpty($methods);
        
        // Check that each method has required fields
        foreach ($methods as $method) {
            $this->assertArrayHasKey('code', $method);
            $this->assertArrayHasKey('name', $method);
            $this->assertArrayHasKey('type', $method);
            $this->assertArrayHasKey('description', $method);
        }
    }

    public function test_validates_payment_data()
    {
        $invalidPaymentData = [
            'gateway' => 'invalid_gateway',
            'amount' => 100,
            'currency' => 'PHP',
        ];

        $result = $this->paymentService->processPayment($invalidPaymentData);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('reference_id', $result['error']);
    }

    public function test_validates_amount_limits()
    {
        $paymentData = [
            'gateway' => 'gcash',
            'amount' => 0.5, // Below minimum
            'currency' => 'PHP',
            'reference_id' => 'TEST-001',
        ];

        $result = $this->paymentService->processPayment($paymentData);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('minimum limit', $result['error']);
    }

    public function test_creates_payment_record()
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);
        
        $paymentData = [
            'gateway' => 'bank_transfer',
            'order_id' => $order->id,
            'amount' => 1000,
            'currency' => 'PHP',
            'reference_id' => 'TEST-001',
            'bank' => 'bpi',
        ];

        $result = $this->paymentService->processPayment($paymentData);
        
        // Debug the result if it fails
        if (!isset($result['success'])) {
            $this->fail('Payment processing returned unexpected result: ' . json_encode($result));
        }
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('payment_id', $result);
        
        $payment = Payment::find($result['payment_id']);
        $this->assertNotNull($payment);
        $this->assertEquals($order->id, $payment->order_id);
        $this->assertEquals(1000, $payment->amount);
        $this->assertEquals('bank_transfer', $payment->gateway);
    }

    public function test_can_verify_payment()
    {
        $payment = Payment::factory()->create([
            'gateway' => 'bank_transfer',
            'gateway_transaction_id' => 'BT-TEST-001',
            'status' => Payment::STATUS_PENDING,
        ]);

        $result = $this->paymentService->verifyPayment($payment->id);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('amount', $result);
    }

    public function test_handles_invalid_payment_verification()
    {
        $result = $this->paymentService->verifyPayment(999999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Payment not found', $result['error']);
    }

    public function test_can_refund_completed_payment()
    {
        $payment = Payment::factory()->create([
            'gateway' => 'bank_transfer',
            'status' => Payment::STATUS_COMPLETED,
            'amount' => 1000,
        ]);

        $result = $this->paymentService->refundPayment($payment->id, 500);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(500, $result['amount_refunded']);
        
        $payment->refresh();
        $this->assertEquals(Payment::STATUS_REFUNDED, $payment->status);
    }

    public function test_cannot_refund_pending_payment()
    {
        $payment = Payment::factory()->create([
            'status' => Payment::STATUS_PENDING,
        ]);

        $result = $this->paymentService->refundPayment($payment->id);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Only completed payments', $result['error']);
    }

    public function test_cannot_refund_more_than_payment_amount()
    {
        $payment = Payment::factory()->create([
            'status' => Payment::STATUS_COMPLETED,
            'amount' => 1000,
        ]);

        $result = $this->paymentService->refundPayment($payment->id, 1500);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('cannot exceed payment amount', $result['error']);
    }

    public function test_handles_webhook_with_valid_transaction()
    {
        $payment = Payment::factory()->create([
            'gateway' => 'gcash',
            'gateway_transaction_id' => 'GCASH-123456',
            'status' => Payment::STATUS_PENDING,
        ]);

        $webhookPayload = [
            'transaction_id' => 'GCASH-123456',
            'status' => 'completed',
            'amount' => $payment->amount,
            'currency' => 'PHP',
            'reference_id' => 'TEST-REF-123',
        ];

        $result = $this->paymentService->handleWebhook('gcash', $webhookPayload);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($payment->id, $result['payment_id']);
        
        $payment->refresh();
        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status);
    }

    public function test_handles_webhook_with_invalid_transaction()
    {
        $webhookPayload = [
            'transaction_id' => 'INVALID-123456',
            'status' => 'completed',
            'amount' => 1000,
            'currency' => 'PHP',
            'reference_id' => 'TEST-REF-456',
        ];

        $result = $this->paymentService->handleWebhook('gcash', $webhookPayload);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Payment not found', $result['error']);
    }
}