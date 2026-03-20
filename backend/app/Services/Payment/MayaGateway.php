<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MayaGateway implements PaymentGatewayInterface
{
    private string $publicKey;
    private string $secretKey;
    private string $apiUrl;
    private string $webhookSecret;
    private int $timeout;

    public function __construct()
    {
        $config = config('payments.gateways.maya');
        $this->publicKey = $config['public_key'];
        $this->secretKey = $config['secret_key'];
        $this->apiUrl = $config['api_url'];
        $this->webhookSecret = $config['webhook_secret'] ?? '';
        $this->timeout = $config['timeout'];
    }

    /**
     * Process a payment through Maya.
     */
    public function processPayment(array $paymentData): array
    {
        try {
            $payload = [
                'totalAmount' => [
                    'value' => $paymentData['amount'] * 100, // Maya expects amount in centavos
                    'currency' => $paymentData['currency'] ?? 'PHP',
                ],
                'buyer' => [
                    'firstName' => $paymentData['customer_first_name'] ?? '',
                    'lastName' => $paymentData['customer_last_name'] ?? '',
                    'contact' => [
                        'email' => $paymentData['customer_email'],
                        'phone' => $paymentData['customer_phone'] ?? '',
                    ],
                ],
                'items' => $paymentData['items'] ?? [[
                    'name' => $paymentData['description'] ?? 'Diecast Empire Purchase',
                    'quantity' => 1,
                    'code' => $paymentData['reference_id'],
                    'description' => $paymentData['description'] ?? 'Diecast Empire Purchase',
                    'amount' => [
                        'value' => $paymentData['amount'] * 100,
                        'currency' => $paymentData['currency'] ?? 'PHP',
                    ],
                    'totalAmount' => [
                        'value' => $paymentData['amount'] * 100,
                        'currency' => $paymentData['currency'] ?? 'PHP',
                    ],
                ]],
                'redirectUrl' => [
                    'success' => $paymentData['success_url'],
                    'failure' => $paymentData['failure_url'],
                    'cancel' => $paymentData['cancel_url'],
                ],
                'requestReferenceNumber' => $paymentData['reference_id'],
                'metadata' => [
                    'order_id' => $paymentData['order_id'] ?? null,
                    'preorder_id' => $paymentData['preorder_id'] ?? null,
                ],
            ];

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($this->publicKey . ':'),
                ])
                ->post($this->apiUrl . '/v1/checkouts', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'transaction_id' => $data['checkoutId'],
                    'payment_url' => $data['redirectUrl'],
                    'status' => 'pending',
                    'gateway_response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Payment processing failed',
                'gateway_response' => $response->json(),
            ];

        } catch (Exception $e) {
            Log::error('Maya payment processing failed', [
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
                    'Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'),
                ])
                ->get($this->apiUrl . '/v1/checkouts/' . $transactionId);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'status' => $data['status'],
                    'amount' => $data['totalAmount']['value'] / 100, // Convert from centavos
                    'currency' => $data['totalAmount']['currency'],
                    'gateway_response' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to verify payment',
                'gateway_response' => $response->json(),
            ];

        } catch (Exception $e) {
            Log::error('Maya payment verification failed', [
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
                request()->header('PayMaya-Signature', '')
            )) {
                return [
                    'success' => false,
                    'error' => 'Invalid webhook signature',
                ];
            }

            return [
                'success' => true,
                'transaction_id' => $payload['id'],
                'status' => $payload['status'],
                'amount' => $payload['totalAmount']['value'] / 100,
                'currency' => $payload['totalAmount']['currency'],
                'reference_id' => $payload['requestReferenceNumber'],
                'gateway_response' => $payload,
            ];

        } catch (Exception $e) {
            Log::error('Maya webhook handling failed', [
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
                'totalAmount' => [
                    'value' => $amount * 100, // Convert to centavos
                    'currency' => 'PHP',
                ],
                'reason' => 'Customer refund request',
            ];

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'),
                ])
                ->post($this->apiUrl . '/v1/payments/' . $transactionId . '/refunds', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'refund_id' => $data['id'],
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
            Log::error('Maya refund processing failed', [
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
        
        return hash_equals('sha256=' . $expectedSignature, $signature);
    }

    /**
     * Map Maya status to internal payment status.
     */
    private function mapGatewayStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'PENDING_TOKEN', 'PENDING_PAYMENT' => Payment::STATUS_PENDING,
            'PAYMENT_PROCESSING' => Payment::STATUS_PROCESSING,
            'PAYMENT_SUCCESS', 'COMPLETED' => Payment::STATUS_COMPLETED,
            'PAYMENT_FAILED', 'FAILED' => Payment::STATUS_FAILED,
            'PAYMENT_CANCELLED', 'CANCELLED' => Payment::STATUS_CANCELLED,
            'REFUNDED' => Payment::STATUS_REFUNDED,
            default => Payment::STATUS_FAILED,
        };
    }
}