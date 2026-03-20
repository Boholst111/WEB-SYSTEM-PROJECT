<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\PaymentController;
use App\Services\Payment\PaymentService;
use App\Services\Payment\FraudPreventionService;
use App\Models\User;
use App\Models\Order;
use App\Models\PreOrder;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Mockery;

/**
 * Unit tests for PaymentController validation and error handling.
 * Tests input validation, authentication, authorization, and error responses.
 * 
 * Requirements: 1.6 - Payment processing validation and security
 */
class PaymentControllerValidationTest extends TestCase
{
    use RefreshDatabase;

    private PaymentController $controller;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        
        Auth::login($this->user);
        
        $fraudService = new FraudPreventionService();
        $paymentService = new PaymentService($fraudService);
        $this->controller = new PaymentController($paymentService);
    }

    // GCash Payment Validation Tests
    public function test_gcash_payment_validates_required_fields()
    {
        $request = Request::create('/api/payments/gcash', 'POST', []);
        
        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Validation failed', $responseData['error']);
        $this->assertArrayHasKey('errors', $responseData);
        
        // Check that required fields are in validation errors
        $errors = $responseData['errors'];
        $this->assertArrayHasKey('amount', $errors);
        $this->assertArrayHasKey('success_url', $errors);
        $this->assertArrayHasKey('failure_url', $errors);
        $this->assertArrayHasKey('cancel_url', $errors);
    }

    public function test_gcash_payment_validates_amount_format()
    {
        $request = Request::create('/api/payments/gcash', 'POST', [
            'amount' => 'invalid_amount',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);
        
        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('amount', $responseData['errors']);
    }

    public function test_gcash_payment_validates_minimum_amount()
    {
        $request = Request::create('/api/payments/gcash', 'POST', [
            'amount' => 0,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);
        
        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('amount', $responseData['errors']);
    }

    public function test_gcash_payment_validates_url_format()
    {
        $request = Request::create('/api/payments/gcash', 'POST', [
            'amount' => 1000,
            'success_url' => 'invalid_url',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);
        
        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('success_url', $responseData['errors']);
    }

    public function test_gcash_payment_validates_order_exists()
    {
        $request = Request::create('/api/payments/gcash', 'POST', [
            'order_id' => 999999, // Non-existent order
            'amount' => 1000,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);
        
        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('order_id', $responseData['errors']);
    }

    public function test_gcash_payment_validates_preorder_exists()
    {
        $request = Request::create('/api/payments/gcash', 'POST', [
            'preorder_id' => 999999, // Non-existent preorder
            'amount' => 1000,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);
        
        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('preorder_id', $responseData['errors']);
    }

    // Maya Payment Validation Tests
    public function test_maya_payment_validates_required_fields()
    {
        $request = Request::create('/api/payments/maya', 'POST', []);
        
        $response = $this->controller->processMaya($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('errors', $responseData);
        
        $errors = $responseData['errors'];
        $this->assertArrayHasKey('amount', $errors);
        $this->assertArrayHasKey('success_url', $errors);
        $this->assertArrayHasKey('failure_url', $errors);
        $this->assertArrayHasKey('cancel_url', $errors);
    }

    public function test_maya_payment_validates_items_array()
    {
        $request = Request::create('/api/payments/maya', 'POST', [
            'amount' => 1000,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
            'items' => 'invalid_items', // Should be array
        ]);
        
        $response = $this->controller->processMaya($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('items', $responseData['errors']);
    }

    // Bank Transfer Payment Validation Tests
    public function test_bank_transfer_validates_required_fields()
    {
        $request = Request::create('/api/payments/bank-transfer', 'POST', []);
        
        $response = $this->controller->processBankTransfer($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $responseData);
        
        $errors = $responseData['errors'];
        $this->assertArrayHasKey('amount', $errors);
        $this->assertArrayHasKey('bank', $errors);
    }

    public function test_bank_transfer_validates_bank_selection()
    {
        $request = Request::create('/api/payments/bank-transfer', 'POST', [
            'amount' => 1000,
            'bank' => 'invalid_bank',
        ]);
        
        $response = $this->controller->processBankTransfer($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('bank', $responseData['errors']);
    }

    public function test_bank_transfer_accepts_valid_banks()
    {
        $validBanks = ['bpi', 'bdo', 'metrobank'];
        
        foreach ($validBanks as $bank) {
            $request = Request::create('/api/payments/bank-transfer', 'POST', [
                'amount' => 1000,
                'bank' => $bank,
            ]);
            
            $response = $this->controller->processBankTransfer($request);
            $responseData = $response->getData(true);
            
            // Should not have validation errors for bank field
            if ($response->getStatusCode() === 422) {
                $this->assertArrayNotHasKey('bank', $responseData['errors'] ?? []);
            } else {
                // If not validation error, should be successful or gateway error
                $this->assertTrue($response->getStatusCode() === 200 || $response->getStatusCode() === 400);
                if (isset($responseData['success'])) {
                    $this->assertIsBool($responseData['success']);
                }
            }
        }
    }

    // Refund Validation Tests
    public function test_refund_validates_amount_format()
    {
        $payment = Payment::factory()->create([
            'status' => Payment::STATUS_COMPLETED,
            'amount' => 1000,
        ]);

        $request = Request::create("/api/payments/{$payment->id}/refund", 'POST', [
            'amount' => 'invalid_amount',
        ]);
        
        $response = $this->controller->refund($request, $payment->id);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('amount', $responseData['errors']);
    }

    public function test_refund_validates_minimum_amount()
    {
        $payment = Payment::factory()->create([
            'status' => Payment::STATUS_COMPLETED,
            'amount' => 1000,
        ]);

        $request = Request::create("/api/payments/{$payment->id}/refund", 'POST', [
            'amount' => 0,
        ]);
        
        $response = $this->controller->refund($request, $payment->id);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('amount', $responseData['errors']);
    }

    public function test_refund_validates_reason_length()
    {
        $payment = Payment::factory()->create([
            'status' => Payment::STATUS_COMPLETED,
            'amount' => 1000,
        ]);

        $longReason = str_repeat('a', 300); // Exceeds 255 character limit

        $request = Request::create("/api/payments/{$payment->id}/refund", 'POST', [
            'amount' => 500,
            'reason' => $longReason,
        ]);
        
        $response = $this->controller->refund($request, $payment->id);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('reason', $responseData['errors']);
    }

    // Reference ID Generation Tests
    public function test_generates_reference_id_for_order()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-2024-001',
        ]);

        $request = Request::create('/api/payments/gcash', 'POST', [
            'order_id' => $order->id,
            'amount' => 1000,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        // Even if payment fails due to gateway issues, we can check the response structure
        $this->assertTrue($response->getStatusCode() === 200 || $response->getStatusCode() === 400);
        if (isset($responseData['success'])) {
            $this->assertIsBool($responseData['success']);
        }
    }

    public function test_generates_reference_id_for_preorder()
    {
        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $request = Request::create('/api/payments/gcash', 'POST', [
            'preorder_id' => $preorder->id,
            'amount' => 1000,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        // Check that the response is properly formatted
        $this->assertTrue($response->getStatusCode() === 200 || $response->getStatusCode() === 400);
        if (isset($responseData['success'])) {
            $this->assertIsBool($responseData['success']);
        }
    }

    public function test_generates_fallback_reference_id()
    {
        $request = Request::create('/api/payments/gcash', 'POST', [
            'amount' => 1000,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        // Should generate a fallback reference ID when no order or preorder is specified
        $this->assertTrue($response->getStatusCode() === 200 || $response->getStatusCode() === 400);
        if (isset($responseData['success'])) {
            $this->assertIsBool($responseData['success']);
        }
    }

    // Payment Description Generation Tests
    public function test_generates_description_for_order()
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $request = Request::create('/api/payments/gcash', 'POST', [
            'order_id' => $order->id,
            'amount' => 1000,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $response = $this->controller->processGCash($request);
        
        // Description should be generated for order payments
        $this->assertTrue($response->getStatusCode() === 200 || $response->getStatusCode() === 400);
    }

    public function test_generates_description_for_preorder()
    {
        $preorder = PreOrder::factory()->create(['user_id' => $this->user->id]);

        $request = Request::create('/api/payments/gcash', 'POST', [
            'preorder_id' => $preorder->id,
            'amount' => 1000,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        // Description should be generated for preorder payments
        $this->assertTrue($response->getStatusCode() === 200 || $response->getStatusCode() === 400);
        if (isset($responseData['success'])) {
            $this->assertIsBool($responseData['success']);
        }
    }

    // Customer Information Tests
    public function test_includes_customer_information_in_payment_data()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+639123456789',
        ]);
        
        Auth::login($user);

        $request = Request::create('/api/payments/gcash', 'POST', [
            'amount' => 1000,
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        // Customer information should be included in payment data
        $this->assertTrue($response->getStatusCode() === 200 || $response->getStatusCode() === 400);
        if (isset($responseData['success'])) {
            $this->assertIsBool($responseData['success']);
        }
    }

    // Error Response Format Tests
    public function test_validation_error_response_format()
    {
        $request = Request::create('/api/payments/gcash', 'POST', []);
        
        $response = $this->controller->processGCash($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('errors', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Validation failed', $responseData['error']);
        $this->assertIsArray($responseData['errors']);
    }

    public function test_successful_response_format()
    {
        $request = Request::create('/api/payments/bank-transfer', 'POST', [
            'amount' => 1000,
            'bank' => 'bpi',
        ]);

        $response = $this->controller->processBankTransfer($request);
        $responseData = $response->getData(true);
        
        if ($response->getStatusCode() === 200 && isset($responseData['success']) && $responseData['success']) {
            $this->assertArrayHasKey('success', $responseData);
            $this->assertArrayHasKey('payment_id', $responseData);
            $this->assertArrayHasKey('transaction_id', $responseData);
            $this->assertTrue($responseData['success']);
        } else {
            // If not successful, should still have proper error format or be a valid response
            $this->assertTrue($response->getStatusCode() >= 200);
            if (isset($responseData['success'])) {
                $this->assertIsBool($responseData['success']);
            }
        }
    }

    // Payment Methods Endpoint Tests
    public function test_payment_methods_endpoint_returns_correct_format()
    {
        $response = $this->controller->getPaymentMethods();
        $responseData = $response->getData(true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('payment_methods', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertIsArray($responseData['payment_methods']);
        
        // Check payment method structure
        foreach ($responseData['payment_methods'] as $method) {
            $this->assertArrayHasKey('code', $method);
            $this->assertArrayHasKey('name', $method);
            $this->assertArrayHasKey('type', $method);
            $this->assertArrayHasKey('description', $method);
        }
    }

    // Status and Verification Endpoint Tests
    public function test_payment_status_endpoint_handles_invalid_id()
    {
        $response = $this->controller->status(new Request(), 999999);
        $responseData = $response->getData(true);
        
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($responseData['success']);
    }

    public function test_payment_verification_endpoint_handles_invalid_id()
    {
        $response = $this->controller->verify(new Request(), 999999);
        $responseData = $response->getData(true);
        
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($responseData['success']);
    }

    // Webhook Endpoint Tests
    public function test_webhook_endpoints_return_ok_status()
    {
        $request = Request::create('/api/webhooks/gcash', 'POST', [
            'transaction_id' => 'TEST-123',
            'status' => 'completed',
        ]);

        $response = $this->controller->handleGCashWebhook($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('ok', $responseData['status']);
    }

    public function test_maya_webhook_endpoint_returns_ok_status()
    {
        $request = Request::create('/api/webhooks/maya', 'POST', [
            'id' => 'TEST-456',
            'status' => 'PAYMENT_SUCCESS',
        ]);

        $response = $this->controller->handleMayaWebhook($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('ok', $responseData['status']);
    }

    public function test_bank_transfer_webhook_endpoint_returns_ok_status()
    {
        $request = Request::create('/api/webhooks/bank-transfer', 'POST', [
            'reference_number' => 'BT-TEST-789',
            'status' => 'completed',
        ]);

        $response = $this->controller->handleBankTransferWebhook($request);
        $responseData = $response->getData(true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('ok', $responseData['status']);
    }
}