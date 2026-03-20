<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\Order;
use App\Models\PreOrder;
use Illuminate\Support\Facades\DB;
use App\Services\Payment\FraudPreventionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentService
{
    private array $gateways;
    private FraudPreventionService $fraudService;

    public function __construct(FraudPreventionService $fraudService)
    {
        $this->gateways = [
            'gcash' => new GCashGateway(),
            'maya' => new MayaGateway(),
            'bank_transfer' => new BankTransferGateway(),
        ];
        $this->fraudService = $fraudService;
    }

    /**
     * Process a payment through the specified gateway.
     */
    public function processPayment(array $paymentData): array
    {
        try {
            // Validate payment data
            $validation = $this->validatePaymentData($paymentData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error'],
                ];
            }

            $gateway = $paymentData['gateway'];
            $gatewayInstance = $this->getGateway($gateway);

            if (!$gatewayInstance) {
                return [
                    'success' => false,
                    'error' => 'Invalid payment gateway',
                ];
            }

            // Check security limits
            $securityCheck = $this->performSecurityChecks($paymentData);
            if (!$securityCheck['passed']) {
                return [
                    'success' => false,
                    'error' => $securityCheck['error'],
                ];
            }

            // Perform fraud detection
            if (config('payments.security.fraud_detection')) {
                $user = Auth::user();
                if ($user) {
                    $fraudCheck = $this->fraudService->checkPayment($paymentData, $user);
                    
                    if (!$fraudCheck['allow_payment']) {
                        $this->fraudService->logFraudAttempt($paymentData, $user, $fraudCheck);
                        
                        return [
                            'success' => false,
                            'error' => 'Payment blocked due to security concerns',
                            'fraud_flags' => $fraudCheck['flags'],
                        ];
                    }
                    
                    if ($fraudCheck['require_verification']) {
                        return $this->fraudService->requireVerification($user, $paymentData);
                    }
                }
            }

            DB::beginTransaction();

            try {
                // Create payment record
                $payment = $this->createPaymentRecord($paymentData);

                // Process payment through gateway
                $result = $gatewayInstance->processPayment($paymentData);

                if ($result['success']) {
                    // Update payment record with gateway response
                    $payment->update([
                        'gateway_transaction_id' => $result['transaction_id'],
                        'status' => $this->mapGatewayStatus($result['status']),
                        'gateway_response' => $result['gateway_response'],
                    ]);

                    DB::commit();

                    return [
                        'success' => true,
                        'payment_id' => $payment->id,
                        'transaction_id' => $result['transaction_id'],
                        'payment_url' => $result['payment_url'] ?? null,
                        'payment_instructions' => $result['payment_instructions'] ?? null,
                        'status' => $payment->status,
                    ];
                } else {
                    // Update payment record with failure
                    $payment->update([
                        'status' => Payment::STATUS_FAILED,
                        'failed_at' => now(),
                        'failure_reason' => $result['error'],
                        'gateway_response' => $result['gateway_response'] ?? null,
                    ]);

                    DB::commit();

                    return [
                        'success' => false,
                        'payment_id' => $payment->id,
                        'error' => $result['error'],
                    ];
                }

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData,
            ]);

            return [
                'success' => false,
                'error' => 'Payment processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify a payment status.
     */
    public function verifyPayment(int $paymentId): array
    {
        try {
            $payment = Payment::find($paymentId);

            if (!$payment) {
                return [
                    'success' => false,
                    'error' => 'Payment not found',
                ];
            }

            $gatewayInstance = $this->getGateway($payment->gateway);

            if (!$gatewayInstance) {
                return [
                    'success' => false,
                    'error' => 'Invalid payment gateway',
                ];
            }

            $result = $gatewayInstance->verifyPayment($payment->gateway_transaction_id);

            if ($result['success']) {
                $newStatus = $this->mapGatewayStatus($result['status']);
                
                if ($payment->status !== $newStatus) {
                    $payment->update([
                        'status' => $newStatus,
                        'gateway_response' => $result['gateway_response'],
                        'processed_at' => $newStatus === Payment::STATUS_COMPLETED ? now() : null,
                    ]);

                    // Update related order/preorder status
                    $this->updateRelatedOrderStatus($payment);
                }

                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'gateway_response' => $result['gateway_response'],
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'],
            ];

        } catch (Exception $e) {
            Log::error('Payment verification failed', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            return [
                'success' => false,
                'error' => 'Payment verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook notifications.
     */
    public function handleWebhook(string $gateway, array $payload): array
    {
        try {
            $gatewayInstance = $this->getGateway($gateway);

            if (!$gatewayInstance) {
                return [
                    'success' => false,
                    'error' => 'Invalid payment gateway',
                ];
            }

            $result = $gatewayInstance->handleWebhook($payload);

            if ($result['success']) {
                // Find payment by transaction ID
                $payment = Payment::where('gateway_transaction_id', $result['transaction_id'])->first();

                if ($payment) {
                    $newStatus = $this->mapGatewayStatus($result['status']);
                    
                    $payment->update([
                        'status' => $newStatus,
                        'gateway_response' => $result['gateway_response'],
                        'processed_at' => $newStatus === Payment::STATUS_COMPLETED ? now() : null,
                    ]);

                    // Update related order/preorder status
                    $this->updateRelatedOrderStatus($payment);

                    return [
                        'success' => true,
                        'payment_id' => $payment->id,
                        'status' => $payment->status,
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'Payment not found for transaction ID: ' . $result['transaction_id'],
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'],
            ];

        } catch (Exception $e) {
            Log::error('Webhook handling failed', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error' => 'Webhook processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Refund a payment.
     */
    public function refundPayment(int $paymentId, float $amount = null): array
    {
        try {
            $payment = Payment::find($paymentId);

            if (!$payment) {
                return [
                    'success' => false,
                    'error' => 'Payment not found',
                ];
            }

            if ($payment->status !== Payment::STATUS_COMPLETED) {
                return [
                    'success' => false,
                    'error' => 'Only completed payments can be refunded',
                ];
            }

            $refundAmount = $amount ?? $payment->amount;

            if ($refundAmount > $payment->amount) {
                return [
                    'success' => false,
                    'error' => 'Refund amount cannot exceed payment amount',
                ];
            }

            $gatewayInstance = $this->getGateway($payment->gateway);

            if (!$gatewayInstance) {
                return [
                    'success' => false,
                    'error' => 'Invalid payment gateway',
                ];
            }

            $result = $gatewayInstance->refundPayment($payment->gateway_transaction_id, $refundAmount);

            if ($result['success']) {
                $payment->update([
                    'status' => Payment::STATUS_REFUNDED,
                    'gateway_response' => $result['gateway_response'],
                ]);

                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'refund_id' => $result['refund_id'],
                    'amount_refunded' => $refundAmount,
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'],
            ];

        } catch (Exception $e) {
            Log::error('Payment refund failed', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'amount' => $amount,
            ]);

            return [
                'success' => false,
                'error' => 'Payment refund failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get available payment methods.
     */
    public function getAvailablePaymentMethods(): array
    {
        $methods = [];

        if (config('payments.gateways.gcash.enabled')) {
            $methods[] = [
                'code' => 'gcash',
                'name' => 'GCash',
                'type' => 'e_wallet',
                'description' => 'Pay using your GCash account',
            ];
        }

        if (config('payments.gateways.maya.enabled')) {
            $methods[] = [
                'code' => 'maya',
                'name' => 'Maya (PayMaya)',
                'type' => 'e_wallet',
                'description' => 'Pay using your Maya account',
            ];
        }

        if (config('payments.gateways.bank_transfer.enabled')) {
            $methods[] = [
                'code' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'type' => 'bank_transfer',
                'description' => 'Transfer to our bank account',
                'banks' => $this->gateways['bank_transfer']->getAvailableBanks(),
            ];
        }

        return $methods;
    }

    /**
     * Get gateway instance.
     */
    private function getGateway(string $gateway): ?PaymentGatewayInterface
    {
        return $this->gateways[$gateway] ?? null;
    }

    /**
     * Validate payment data.
     */
    private function validatePaymentData(array $paymentData): array
    {
        $required = ['gateway', 'amount', 'currency', 'reference_id'];

        foreach ($required as $field) {
            if (!isset($paymentData[$field]) || empty($paymentData[$field])) {
                return [
                    'valid' => false,
                    'error' => "Missing required field: {$field}",
                ];
            }
        }

        if (!isset($this->gateways[$paymentData['gateway']])) {
            return [
                'valid' => false,
                'error' => 'Invalid payment gateway',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Perform security checks.
     */
    private function performSecurityChecks(array $paymentData): array
    {
        $security = config('payments.security');

        // Check amount limits
        if ($paymentData['amount'] < $security['min_amount']) {
            return [
                'passed' => false,
                'error' => 'Amount below minimum limit',
            ];
        }

        if ($paymentData['amount'] > $security['max_amount']) {
            return [
                'passed' => false,
                'error' => 'Amount exceeds maximum limit',
            ];
        }

        // Additional fraud detection could be implemented here
        if ($security['fraud_detection']) {
            // Implement fraud detection logic
        }

        return ['passed' => true];
    }

    /**
     * Create payment record.
     */
    private function createPaymentRecord(array $paymentData): Payment
    {
        return Payment::create([
            'order_id' => $paymentData['order_id'] ?? null,
            'preorder_id' => $paymentData['preorder_id'] ?? null,
            'payment_method' => $paymentData['gateway'], // Use gateway as payment method
            'gateway' => $paymentData['gateway'],
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'],
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /**
     * Map gateway status to internal status.
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

    /**
     * Update related order/preorder status based on payment status.
     */
    private function updateRelatedOrderStatus(Payment $payment): void
    {
        if ($payment->order_id && $payment->status === Payment::STATUS_COMPLETED) {
            $order = Order::find($payment->order_id);
            if ($order && $order->payment_status === 'pending') {
                $order->update(['payment_status' => 'paid']);
            }
        }

        if ($payment->preorder_id && $payment->status === Payment::STATUS_COMPLETED) {
            $preorder = PreOrder::find($payment->preorder_id);
            if ($preorder) {
                // Update preorder status based on payment type (deposit or full payment)
                if ($payment->amount == $preorder->deposit_amount) {
                    $preorder->update([
                        'status' => 'deposit_paid',
                        'deposit_paid_at' => now(),
                    ]);
                } else {
                    $preorder->update(['status' => 'completed']);
                }
            }
        }
    }
}