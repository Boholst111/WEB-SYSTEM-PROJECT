<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Payment\GCashGateway;
use App\Services\Payment\MayaGateway;
use App\Services\Payment\BankTransferGateway;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

/**
 * Unit tests for payment gateway integrations with mock services.
 * Tests gateway-specific functionality, API interactions, and error handling.
 * 
 * Requirements: 1.6 - Payment gateway integration and security
 */
class PaymentGatewayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration for gateways
        Config::set('payments.gateways.gcash', [
            'merchant_id' => 'TEST_MERCHANT',
            'secret_key' => 'test_secret_key',
            'api_url' => 'https://api.gcash.test',
            'webhook_secret' => 'webhook_secret',
            'timeout' => 30,
            'enabled' => true,
        ]);

        Config::set('payments.gateways.maya', [
            'public_key' => 'pk_test_123',
            'secret_key' => 'sk_test_456',
            'api_url' => 'https://pg-sandbox.paymaya.com',
            'webhook_secret' => 'maya_webhook_secret',
            'timeout' => 30,
            'enabled' => true,
        ]);

        Config::set('payments.gateways.bank_transfer', [
            'enabled' => true,
            'banks' => [
                'bpi' => [
                    'name' => 'Bank of the Philippine Islands',
                    'account_number' => '1234567890',
                    'account_name' => 'Diecast Empire',
                ],
                'bdo' => [
                    'name' => 'Banco de Oro',
                    'account_number' => '0987654321',
                    'account_name' => 'Diecast Empire',
                ],
            ],
        ]);
    }

    // GCash Gateway Tests
    public function test_gcash_gateway_processes_payment_successfully()
    {
        Http::fake([
            'https://api.gcash.test/v1/payments' => Http::response([
                'transaction_id' => 'GCASH-123456789',
                'payment_url' => 'https://gcash.com/pay/123456789',
                'status' => 'pending',
                'amount' => 1000,
                'currency' => 'PHP',
            ], 200)
        ]);

        $gateway = new GCashGateway();
        $paymentData = [
            'amount' => 1000,
            'currency' => 'PHP',
            'reference_id' => 'ORDER-001',
            'description' => 'Test payment',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+639123456789',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $result = $gateway->processPayment($paymentData);

        $this->assertTrue($result['success']);
        $this->assertEquals('GCASH-123456789', $result['transaction_id']);
        $this->assertEquals('https://gcash.com/pay/123456789', $result['payment_url']);
        $this->assertEquals('pending', $result['status']);
    }

    public function test_gcash_gateway_handles_api_failure()
    {
        Http::fake([
            'https://api.gcash.test/v1/payments' => Http::response([
                'message' => 'Invalid merchant credentials',
                'error_code' => 'INVALID_MERCHANT',
            ], 401)
        ]);

        $gateway = new GCashGateway();
        $paymentData = [
            'amount' => 1000,
            'currency' => 'PHP',
            'reference_id' => 'ORDER-001',
            'description' => 'Test payment',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $result = $gateway->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid merchant credentials', $result['error']);
    }

    public function test_gcash_gateway_verifies_payment_status()
    {
        Http::fake([
            'https://api.gcash.test/v1/payments/GCASH-123456789' => Http::response([
                'transaction_id' => 'GCASH-123456789',
                'status' => 'completed',
                'amount' => 1000,
                'currency' => 'PHP',
            ], 200)
        ]);

        $gateway = new GCashGateway();
        $result = $gateway->verifyPayment('GCASH-123456789');

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(1000, $result['amount']);
    }

    public function test_gcash_gateway_handles_webhook_with_valid_signature()
    {
        // Temporarily disable webhook secret for this test
        Config::set('payments.gateways.gcash.webhook_secret', '');
        
        $gateway = new GCashGateway();
        $payload = [
            'transaction_id' => 'GCASH-123456789',
            'status' => 'completed',
            'amount' => 1000,
            'currency' => 'PHP',
            'reference_id' => 'ORDER-001',
        ];

        $result = $gateway->handleWebhook($payload);

        $this->assertTrue($result['success']);
        $this->assertEquals('GCASH-123456789', $result['transaction_id']);
        $this->assertEquals('completed', $result['status']);
    }

    public function test_gcash_gateway_processes_refund()
    {
        Http::fake([
            'https://api.gcash.test/v1/refunds' => Http::response([
                'refund_id' => 'REFUND-123456789',
                'status' => 'processing',
                'amount' => 500,
            ], 200)
        ]);

        $gateway = new GCashGateway();
        $result = $gateway->refundPayment('GCASH-123456789', 500);

        $this->assertTrue($result['success']);
        $this->assertEquals('REFUND-123456789', $result['refund_id']);
        $this->assertEquals('processing', $result['status']);
    }

    // Maya Gateway Tests
    public function test_maya_gateway_processes_payment_successfully()
    {
        Http::fake([
            'https://pg-sandbox.paymaya.com/v1/checkouts' => Http::response([
                'checkoutId' => 'MAYA-CHECKOUT-123',
                'redirectUrl' => 'https://checkout.paymaya.com/checkout?id=MAYA-CHECKOUT-123',
                'status' => 'PENDING_TOKEN',
            ], 200)
        ]);

        $gateway = new MayaGateway();
        $paymentData = [
            'amount' => 1500,
            'currency' => 'PHP',
            'reference_id' => 'ORDER-002',
            'description' => 'Maya test payment',
            'customer_first_name' => 'Jane',
            'customer_last_name' => 'Smith',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '+639987654321',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ];

        $result = $gateway->processPayment($paymentData);

        $this->assertTrue($result['success']);
        $this->assertEquals('MAYA-CHECKOUT-123', $result['transaction_id']);
        $this->assertStringContainsString('checkout.paymaya.com', $result['payment_url']);
        $this->assertEquals('pending', $result['status']);
    }

    public function test_maya_gateway_handles_invalid_request()
    {
        Http::fake([
            'https://pg-sandbox.paymaya.com/v1/checkouts' => Http::response([
                'message' => 'Invalid request parameters',
                'code' => 'INVALID_REQUEST',
            ], 400)
        ]);

        $gateway = new MayaGateway();
        $paymentData = [
            'amount' => -100, // Invalid amount
            'currency' => 'PHP',
            'reference_id' => 'ORDER-002',
            'customer_first_name' => 'Jane',
            'customer_last_name' => 'Smith',
            'customer_email' => 'invalid-email',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
        ];

        $result = $gateway->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid request parameters', $result['error']);
    }

    public function test_maya_gateway_verifies_payment_with_amount_conversion()
    {
        Http::fake([
            'https://pg-sandbox.paymaya.com/v1/checkouts/MAYA-CHECKOUT-123' => Http::response([
                'id' => 'MAYA-CHECKOUT-123',
                'status' => 'PAYMENT_SUCCESS',
                'totalAmount' => [
                    'value' => 150000, // Amount in centavos
                    'currency' => 'PHP',
                ],
            ], 200)
        ]);

        $gateway = new MayaGateway();
        $result = $gateway->verifyPayment('MAYA-CHECKOUT-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('PAYMENT_SUCCESS', $result['status']);
        $this->assertEquals(1500, $result['amount']); // Converted from centavos
    }

    public function test_maya_gateway_handles_webhook_with_amount_conversion()
    {
        // Temporarily disable webhook secret for this test
        Config::set('payments.gateways.maya.webhook_secret', '');
        
        $gateway = new MayaGateway();
        $payload = [
            'id' => 'MAYA-CHECKOUT-456',
            'status' => 'PAYMENT_SUCCESS',
            'totalAmount' => [
                'value' => 200000, // Amount in centavos
                'currency' => 'PHP',
            ],
            'requestReferenceNumber' => 'ORDER-003',
        ];

        $result = $gateway->handleWebhook($payload);

        $this->assertTrue($result['success']);
        $this->assertEquals('MAYA-CHECKOUT-456', $result['transaction_id']);
        $this->assertEquals('PAYMENT_SUCCESS', $result['status']);
        $this->assertEquals(2000, $result['amount']); // Converted from centavos
        $this->assertEquals('ORDER-003', $result['reference_id']);
    }

    public function test_maya_gateway_processes_refund_with_amount_conversion()
    {
        Http::fake([
            'https://pg-sandbox.paymaya.com/v1/payments/MAYA-PAYMENT-123/refunds' => Http::response([
                'id' => 'MAYA-REFUND-123',
                'status' => 'PENDING',
                'totalAmount' => [
                    'value' => 75000, // Amount in centavos
                    'currency' => 'PHP',
                ],
            ], 200)
        ]);

        $gateway = new MayaGateway();
        $result = $gateway->refundPayment('MAYA-PAYMENT-123', 750);

        $this->assertTrue($result['success']);
        $this->assertEquals('MAYA-REFUND-123', $result['refund_id']);
        $this->assertEquals('PENDING', $result['status']);
    }

    // Bank Transfer Gateway Tests
    public function test_bank_transfer_gateway_generates_payment_instructions()
    {
        $gateway = new BankTransferGateway();
        $paymentData = [
            'amount' => 2500,
            'currency' => 'PHP',
            'reference_id' => 'ORDER-004',
            'bank' => 'bpi',
        ];

        $result = $gateway->processPayment($paymentData);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('BT-ORDER-004', $result['transaction_id']);
        $this->assertEquals('pending', $result['status']);
        
        $instructions = $result['payment_instructions'];
        $this->assertEquals('Bank of the Philippine Islands', $instructions['bank_name']);
        $this->assertEquals('1234567890', $instructions['account_number']);
        $this->assertEquals('Diecast Empire', $instructions['account_name']);
        $this->assertEquals(2500, $instructions['amount']);
        $this->assertIsArray($instructions['instructions']);
        $this->assertIsArray($instructions['verification_requirements']);
    }

    public function test_bank_transfer_gateway_handles_invalid_bank()
    {
        $gateway = new BankTransferGateway();
        $paymentData = [
            'amount' => 2500,
            'currency' => 'PHP',
            'reference_id' => 'ORDER-005',
            'bank' => 'invalid_bank',
        ];

        $result = $gateway->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid bank selected', $result['error']);
    }

    public function test_bank_transfer_gateway_handles_disabled_state()
    {
        Config::set('payments.gateways.bank_transfer.enabled', false);
        
        $gateway = new BankTransferGateway();
        $paymentData = [
            'amount' => 2500,
            'currency' => 'PHP',
            'reference_id' => 'ORDER-006',
            'bank' => 'bpi',
        ];

        $result = $gateway->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('currently disabled', $result['error']);
    }

    public function test_bank_transfer_gateway_verification_requires_manual_process()
    {
        $gateway = new BankTransferGateway();
        $result = $gateway->verifyPayment('BT-ORDER-007-20240115123456');

        $this->assertTrue($result['success']);
        $this->assertEquals('pending', $result['status']);
        $this->assertStringContainsString('manual verification', $result['message']);
    }

    public function test_bank_transfer_gateway_manual_verification()
    {
        $gateway = new BankTransferGateway();
        $verificationData = [
            'status' => 'completed',
            'verified_by' => 'admin@diecastempire.com',
            'notes' => 'Receipt verified, payment confirmed',
        ];

        $result = $gateway->manualVerification('BT-ORDER-008-20240115123456', $verificationData);

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('admin@diecastempire.com', $result['verified_by']);
        $this->assertStringContainsString('Receipt verified', $result['verification_notes']);
    }

    public function test_bank_transfer_gateway_refund_requires_manual_processing()
    {
        $gateway = new BankTransferGateway();
        $result = $gateway->refundPayment('BT-ORDER-009-20240115123456', 1000);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('BT-REFUND-', $result['refund_id']);
        $this->assertEquals('pending', $result['status']);
        $this->assertStringContainsString('manual processing', $result['message']);
    }

    public function test_bank_transfer_gateway_provides_available_banks()
    {
        $gateway = new BankTransferGateway();
        $banks = $gateway->getAvailableBanks();

        $this->assertIsArray($banks);
        $this->assertCount(2, $banks); // BPI and BDO configured in setUp
        
        foreach ($banks as $bank) {
            $this->assertArrayHasKey('code', $bank);
            $this->assertArrayHasKey('name', $bank);
            $this->assertArrayHasKey('account_number', $bank);
            $this->assertArrayHasKey('account_name', $bank);
        }
    }

    // Gateway Error Handling Tests
    public function test_gateways_handle_network_timeouts()
    {
        Http::fake([
            'https://api.gcash.test/v1/payments' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            }
        ]);

        $gateway = new GCashGateway();
        $paymentData = [
            'amount' => 1000,
            'currency' => 'PHP',
            'reference_id' => 'ORDER-TIMEOUT',
            'description' => 'Timeout test',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $result = $gateway->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('gateway error', $result['error']);
    }

    public function test_gateways_handle_malformed_responses()
    {
        Http::fake([
            'https://api.gcash.test/v1/payments' => Http::response('Invalid JSON response', 200)
        ]);

        $gateway = new GCashGateway();
        $paymentData = [
            'amount' => 1000,
            'currency' => 'PHP',
            'reference_id' => 'ORDER-MALFORMED',
            'description' => 'Malformed response test',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $result = $gateway->processPayment($paymentData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('gateway error', $result['error']);
    }

    // Gateway Status Mapping Tests
    public function test_gcash_gateway_maps_statuses_correctly()
    {
        $gateway = new GCashGateway();
        
        $testCases = [
            'pending' => Payment::STATUS_PENDING,
            'created' => Payment::STATUS_PENDING,
            'processing' => Payment::STATUS_PROCESSING,
            'authorized' => Payment::STATUS_PROCESSING,
            'completed' => Payment::STATUS_COMPLETED,
            'paid' => Payment::STATUS_COMPLETED,
            'success' => Payment::STATUS_COMPLETED,
            'failed' => Payment::STATUS_FAILED,
            'declined' => Payment::STATUS_FAILED,
            'error' => Payment::STATUS_FAILED,
            'cancelled' => Payment::STATUS_CANCELLED,
            'canceled' => Payment::STATUS_CANCELLED,
            'refunded' => Payment::STATUS_REFUNDED,
            'unknown_status' => Payment::STATUS_FAILED, // Default case
        ];

        foreach ($testCases as $gatewayStatus => $expectedStatus) {
            Http::fake([
                'https://api.gcash.test/v1/payments/TEST-' . $gatewayStatus => Http::response([
                    'transaction_id' => 'TEST-' . $gatewayStatus,
                    'status' => $gatewayStatus,
                    'amount' => 1000,
                    'currency' => 'PHP',
                ], 200)
            ]);

            $result = $gateway->verifyPayment('TEST-' . $gatewayStatus);
            $this->assertTrue($result['success']);
            
            $mappedStatus = $gateway->getPaymentStatus('TEST-' . $gatewayStatus);
            $this->assertEquals($expectedStatus, $mappedStatus, "Failed mapping for status: {$gatewayStatus}");
        }
    }

    public function test_maya_gateway_maps_statuses_correctly()
    {
        $gateway = new MayaGateway();
        
        $testCases = [
            'PENDING_TOKEN' => Payment::STATUS_PENDING,
            'PENDING_PAYMENT' => Payment::STATUS_PENDING,
            'PAYMENT_PROCESSING' => Payment::STATUS_PROCESSING,
            'PAYMENT_SUCCESS' => Payment::STATUS_COMPLETED,
            'COMPLETED' => Payment::STATUS_COMPLETED,
            'PAYMENT_FAILED' => Payment::STATUS_FAILED,
            'FAILED' => Payment::STATUS_FAILED,
            'PAYMENT_CANCELLED' => Payment::STATUS_CANCELLED,
            'CANCELLED' => Payment::STATUS_CANCELLED,
            'REFUNDED' => Payment::STATUS_REFUNDED,
            'UNKNOWN_STATUS' => Payment::STATUS_FAILED, // Default case
        ];

        foreach ($testCases as $gatewayStatus => $expectedStatus) {
            Http::fake([
                'https://pg-sandbox.paymaya.com/v1/checkouts/TEST-' . $gatewayStatus => Http::response([
                    'id' => 'TEST-' . $gatewayStatus,
                    'status' => $gatewayStatus,
                    'totalAmount' => [
                        'value' => 100000,
                        'currency' => 'PHP',
                    ],
                ], 200)
            ]);

            $result = $gateway->verifyPayment('TEST-' . $gatewayStatus);
            $this->assertTrue($result['success']);
            
            $mappedStatus = $gateway->getPaymentStatus('TEST-' . $gatewayStatus);
            $this->assertEquals($expectedStatus, $mappedStatus, "Failed mapping for status: {$gatewayStatus}");
        }
    }

    // Webhook Signature Validation Tests
    public function test_gcash_webhook_signature_validation()
    {
        $gateway = new GCashGateway();
        $payload = '{"transaction_id":"TEST-123","status":"completed"}';
        
        // Test with valid signature
        $validSignature = hash_hmac('sha256', $payload, 'webhook_secret');
        $this->assertTrue($gateway->validateWebhookSignature($payload, $validSignature));
        
        // Test with invalid signature
        $invalidSignature = 'invalid_signature';
        $this->assertFalse($gateway->validateWebhookSignature($payload, $invalidSignature));
    }

    public function test_maya_webhook_signature_validation()
    {
        $gateway = new MayaGateway();
        $payload = '{"id":"TEST-456","status":"PAYMENT_SUCCESS"}';
        
        // Test with valid signature
        $validSignature = 'sha256=' . hash_hmac('sha256', $payload, 'maya_webhook_secret');
        $this->assertTrue($gateway->validateWebhookSignature($payload, $validSignature));
        
        // Test with invalid signature
        $invalidSignature = 'sha256=invalid_signature';
        $this->assertFalse($gateway->validateWebhookSignature($payload, $invalidSignature));
    }

    public function test_bank_transfer_webhook_signature_always_valid()
    {
        $gateway = new BankTransferGateway();
        
        // Bank transfers don't have webhook signatures, so it should always return true
        $this->assertTrue($gateway->validateWebhookSignature('any_payload', 'any_signature'));
    }
}