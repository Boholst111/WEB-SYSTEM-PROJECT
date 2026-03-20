<?php

namespace App\Services\Payment;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Process a payment through the gateway.
     *
     * @param array $paymentData
     * @return array
     */
    public function processPayment(array $paymentData): array;

    /**
     * Verify a payment status.
     *
     * @param string $transactionId
     * @return array
     */
    public function verifyPayment(string $transactionId): array;

    /**
     * Handle webhook notification.
     *
     * @param array $payload
     * @return array
     */
    public function handleWebhook(array $payload): array;

    /**
     * Refund a payment.
     *
     * @param string $transactionId
     * @param float $amount
     * @return array
     */
    public function refundPayment(string $transactionId, float $amount): array;

    /**
     * Get payment status.
     *
     * @param string $transactionId
     * @return string
     */
    public function getPaymentStatus(string $transactionId): string;

    /**
     * Validate webhook signature.
     *
     * @param string $payload
     * @param string $signature
     * @return bool
     */
    public function validateWebhookSignature(string $payload, string $signature): bool;
}