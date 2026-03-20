<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Exception;

class BankTransferGateway implements PaymentGatewayInterface
{
    private array $banks;
    private bool $enabled;

    public function __construct()
    {
        $config = config('payments.gateways.bank_transfer');
        $this->banks = $config['banks'];
        $this->enabled = $config['enabled'];
    }

    /**
     * Process a bank transfer payment.
     * For bank transfers, we generate instructions and mark as pending.
     */
    public function processPayment(array $paymentData): array
    {
        try {
            if (!$this->enabled) {
                return [
                    'success' => false,
                    'error' => 'Bank transfer payments are currently disabled',
                ];
            }

            $selectedBank = $paymentData['bank'] ?? 'bpi';
            
            if (!isset($this->banks[$selectedBank])) {
                return [
                    'success' => false,
                    'error' => 'Invalid bank selected',
                ];
            }

            $bankInfo = $this->banks[$selectedBank];
            $referenceNumber = $this->generateReferenceNumber($paymentData['reference_id']);

            $instructions = [
                'bank_name' => $bankInfo['name'],
                'account_number' => $bankInfo['account_number'],
                'account_name' => $bankInfo['account_name'],
                'amount' => $paymentData['amount'],
                'reference_number' => $referenceNumber,
                'instructions' => [
                    '1. Transfer the exact amount to the bank account details provided',
                    '2. Use the reference number as your transfer description/memo',
                    '3. Keep your transfer receipt for verification',
                    '4. Upload your receipt or send it to our customer service',
                    '5. Payment will be verified within 24 hours',
                ],
                'verification_requirements' => [
                    'Transfer receipt or screenshot',
                    'Reference number must match',
                    'Amount must be exact',
                    'Transfer must be from registered customer name',
                ],
            ];

            return [
                'success' => true,
                'transaction_id' => $referenceNumber,
                'status' => 'pending',
                'payment_instructions' => $instructions,
                'gateway_response' => [
                    'type' => 'bank_transfer',
                    'bank' => $selectedBank,
                    'reference_number' => $referenceNumber,
                    'instructions' => $instructions,
                ],
            ];

        } catch (Exception $e) {
            Log::error('Bank transfer processing failed', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData,
            ]);

            return [
                'success' => false,
                'error' => 'Bank transfer processing error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify a bank transfer payment.
     * This requires manual verification by admin.
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            // For bank transfers, verification is manual
            // We return the current status from our database
            return [
                'success' => true,
                'status' => 'pending', // Always pending until manually verified
                'message' => 'Bank transfer requires manual verification',
                'gateway_response' => [
                    'type' => 'bank_transfer',
                    'reference_number' => $transactionId,
                    'verification_status' => 'pending',
                ],
            ];

        } catch (Exception $e) {
            Log::error('Bank transfer verification failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => false,
                'error' => 'Bank transfer verification error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook notification.
     * Bank transfers don't have webhooks, but we can handle manual verification updates.
     */
    public function handleWebhook(array $payload): array
    {
        try {
            // Bank transfers don't have automatic webhooks
            // This could be used for manual verification updates from admin panel
            return [
                'success' => true,
                'transaction_id' => $payload['reference_number'] ?? '',
                'status' => $payload['status'] ?? 'pending',
                'verification_notes' => $payload['notes'] ?? '',
                'gateway_response' => $payload,
            ];

        } catch (Exception $e) {
            Log::error('Bank transfer webhook handling failed', [
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
     * Refund a bank transfer payment.
     * This requires manual processing.
     */
    public function refundPayment(string $transactionId, float $amount): array
    {
        try {
            // Bank transfer refunds are manual processes
            return [
                'success' => true,
                'refund_id' => 'BT-REFUND-' . $transactionId,
                'status' => 'pending',
                'message' => 'Bank transfer refund requires manual processing',
                'gateway_response' => [
                    'type' => 'bank_transfer_refund',
                    'reference_number' => $transactionId,
                    'refund_amount' => $amount,
                    'status' => 'pending_manual_processing',
                ],
            ];

        } catch (Exception $e) {
            Log::error('Bank transfer refund processing failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);

            return [
                'success' => false,
                'error' => 'Bank transfer refund error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment status.
     */
    public function getPaymentStatus(string $transactionId): string
    {
        // Bank transfers are always pending until manually verified
        return Payment::STATUS_PENDING;
    }

    /**
     * Validate webhook signature.
     * Bank transfers don't have webhook signatures.
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        // Bank transfers don't have webhook signatures
        return true;
    }

    /**
     * Generate a unique reference number for bank transfers.
     */
    private function generateReferenceNumber(string $baseReference): string
    {
        return 'BT-' . strtoupper($baseReference) . '-' . now()->format('YmdHis');
    }

    /**
     * Get available banks for transfer.
     */
    public function getAvailableBanks(): array
    {
        return array_map(function ($bank, $code) {
            return [
                'code' => $code,
                'name' => $bank['name'],
                'account_number' => $bank['account_number'],
                'account_name' => $bank['account_name'],
            ];
        }, $this->banks, array_keys($this->banks));
    }

    /**
     * Manually verify a bank transfer payment.
     * This would be called from admin panel.
     */
    public function manualVerification(string $transactionId, array $verificationData): array
    {
        try {
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'status' => $verificationData['status'], // 'completed' or 'failed'
                'verified_by' => $verificationData['verified_by'],
                'verification_notes' => $verificationData['notes'] ?? '',
                'verified_at' => now()->toISOString(),
                'gateway_response' => [
                    'type' => 'manual_verification',
                    'reference_number' => $transactionId,
                    'verification_data' => $verificationData,
                ],
            ];

        } catch (Exception $e) {
            Log::error('Bank transfer manual verification failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'verification_data' => $verificationData,
            ]);

            return [
                'success' => false,
                'error' => 'Manual verification error: ' . $e->getMessage(),
            ];
        }
    }
}