<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GCashGateway implements PaymentGatewayInterface
{
    private string $merchantId;
    private string $secretKey;
    private string $apiUrl;
    private string $webhookSecret;
    private int $timeout;

    public function __construct()
    {
        $config = config('payments.gateways.gcash');
        $this->merchantId = $config['merchant_id'];
        $this->secretKey = $config['secret_key'];
        $this->apiUrl = $config['api_url'];
        $this->webhookSecret = $config['webhook_secret'] ?? '';
        $this->timeout = $config['timeout'];
    }

    /**
     * Process a payment through GCash.
     */
    public function processPayment(array $paymentData): array
    {
        try {
            $payload = [
                'merchant_id' => $this->merchantId,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'PHP',
                'reference_id' => $paymentData['reference_id'],
                'description' => $paymentData['description'] ?? 'Diecast Empire Purchase',
                'customer' => [
                    'name' => $paymentData['customer_name'],
                    'email' => $paymentData['customer_email'],
                    'phone' => $paymentData['customer_phone'] ?? null,
                ],
                'redirect_urls' => [
                    'success' => $paymentData['success_url'],
                    'failure' => $paymentData['failure_url'],
                    'cancel' => $paymentData['cancel_url'],
                ],
                'webhook_url' => $paymentData['webhook_url'],
                'timestamp' => now()->timestamp,
            ];

            // Generate signature
            $payload['signature'] = $this->generateSignature($payload);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->secretKey,
                ])
                ->post($this->apiUrl . '/v1/payments', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'transaction_id' => $data['transaction_id'],
                    'payment_url' => $data['payment_url'],
                    'status' => $data['status'],
                    'gateway_response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Payment processing failed',
                'gateway_response' => $response->json(),
            ];

        } catch (Exception $e) {
            Log::error('GCash payment processing failed', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData,
            ]);

            return [
                'success' => false,
                'error' => 'Payment gateway error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify a payment status.
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->secretKey,
                ])
                ->get($this->apiUrl . '/v1/payments/' . $transactionId);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'status' => $data['status'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'gateway_response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to verify payment',
                'gateway_response' => $response->json(),
            ];

        } catch (Exception $e) {
            Log::error('GCash payment verification failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => false,
                'error' => 'Payment verification error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook notification.
     */
    public function handleWebhook(array $payload): array
    {
        try {
            // Validate webhook signature if configured
            if ($this->webhookSecret && !$this->validateWebhookSignature(
                json_encode($payload), 
                request()->header('X-GCash-Signature', '')
            )) {
                return [
                    'success' => false,
                    'error' => 'Invalid webhook signature',
                ];
            }

            return [
                'success' => true,
                'transaction_id' => $payload['transaction_id'],
                'status' => $payload['status'],
                'amount' => $payload['amount'],
                'currency' => $payload['currency'],
                'reference_id' => $payload['reference_id'] ?? null,
                'gateway_response' => $payload,
            ];

        } catch (Exception $e) {
            Log::error('GCash webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error' => 'Webhook processing error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Refund a payment.
     */
    public function refundPayment(string $transactionId, float $amount): array
    {
        try {
            $payload = [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'reason' => 'Customer refund request',
                'timestamp' => now()->timestamp,
            ];

            $payload['signature'] = $this->generateSignature($payload);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->secretKey,
                ])
                ->post($this->apiUrl . '/v1/refunds', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'refund_id' => $data['refund_id'],
                    'status' => $data['status'],
                    'gateway_response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Refund processing failed',
                'gateway_response' => $response->json(),
            ];

        } catch (Exception $e) {
            Log::error('GCash refund processing failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);

            return [
                'success' => false,
                'error' => 'Refund processing error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment status.
     */
    public function getPaymentStatus(string $transactionId): string
    {
        $result = $this->verifyPayment($transactionId);
        
        if ($result['success']) {
            return $this->mapGatewayStatus($result['status']);
        }

        return Payment::STATUS_FAILED;
    }

    /**
     * Validate webhook signature.
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return true; // Skip validation if no secret configured
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate signature for API requests.
     */
    private function generateSignature(array $payload): string
    {
        ksort($payload);
        $queryString = http_build_query($payload);
        
        return hash_hmac('sha256', $queryString, $this->secretKey);
    }

    /**
     * Map GCash status to internal payment status.
     */
    private function mapGatewayStatus(string $gatewayStatus): string
    {
        return match (strtolower($gatewayStatus)) {
            'pending', 'created' => Payment::STATUS_PENDING,
            'processing', 'authorized' => Payment::STATUS_PROCESSING,
            'completed', 'paid', 'success' => Payment::STATUS_COMPLETED,
            'failed', 'declined', 'error' => Payment::STATUS_FAILED,
            'cancelled', 'canceled' => Payment::STATUS_CANCELLED,
            'refunded' => Payment::STATUS_REFUNDED,
            default => Payment::STATUS_FAILED,
        };
    }
}