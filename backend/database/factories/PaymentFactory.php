<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Order;
use App\Models\PreOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $gateway = fake()->randomElement(['gcash', 'maya', 'bank_transfer']);
        
        return [
            'order_id' => null, // Don't create by default
            'preorder_id' => null, // Don't create by default
            'payment_method' => $gateway, // Match gateway
            'gateway' => $gateway,
            'gateway_transaction_id' => fake()->regexify('[A-Z0-9]{12}'),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'PHP',
            'status' => fake()->randomElement([
                Payment::STATUS_PENDING,
                Payment::STATUS_PROCESSING,
                Payment::STATUS_COMPLETED,
                Payment::STATUS_FAILED,
                Payment::STATUS_CANCELLED,
                Payment::STATUS_REFUNDED,
            ]),
            'gateway_response' => [
                'transaction_id' => fake()->regexify('[A-Z0-9]{12}'),
                'status' => 'success',
                'reference_number' => fake()->regexify('[0-9]{10}'),
                'gateway_fee' => fake()->randomFloat(2, 1, 50),
                'processed_at' => fake()->dateTimeThisMonth()->format('Y-m-d H:i:s'),
            ],
            'processed_at' => fake()->optional()->dateTimeThisMonth(),
            'failed_at' => fake()->optional()->dateTimeThisMonth(),
            'failure_reason' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_PENDING,
            'processed_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_PROCESSING,
            'processed_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_COMPLETED,
            'processed_at' => fake()->dateTimeThisMonth(),
            'failed_at' => null,
            'failure_reason' => null,
            'gateway_response' => [
                'transaction_id' => fake()->regexify('[A-Z0-9]{12}'),
                'status' => 'success',
                'reference_number' => fake()->regexify('[0-9]{10}'),
                'gateway_fee' => fake()->randomFloat(2, 1, 50),
                'processed_at' => fake()->dateTimeThisMonth()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_FAILED,
            'processed_at' => null,
            'failed_at' => fake()->dateTimeThisMonth(),
            'failure_reason' => fake()->randomElement([
                'Insufficient funds',
                'Invalid card details',
                'Transaction declined by bank',
                'Gateway timeout',
                'Network error',
            ]),
            'gateway_response' => [
                'transaction_id' => fake()->regexify('[A-Z0-9]{12}'),
                'status' => 'failed',
                'error_code' => fake()->regexify('[A-Z]{3}[0-9]{3}'),
                'error_message' => fake()->sentence(),
            ],
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_CANCELLED,
            'processed_at' => null,
            'failed_at' => null,
            'failure_reason' => 'Cancelled by user',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_REFUNDED,
            'processed_at' => fake()->dateTimeThisMonth(),
            'gateway_response' => [
                'transaction_id' => fake()->regexify('[A-Z0-9]{12}'),
                'status' => 'refunded',
                'refund_id' => fake()->regexify('[A-Z0-9]{12}'),
                'refunded_at' => fake()->dateTimeThisMonth()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'preorder_id' => null,
            'amount' => $order->total_amount,
        ]);
    }

    public function forPreOrder(PreOrder $preorder): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => null,
            'preorder_id' => $preorder->id,
            'amount' => $preorder->deposit_amount,
        ]);
    }

    public function gcash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'gcash',
            'gateway' => 'gcash',
        ]);
    }

    public function maya(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'maya',
            'gateway' => 'maya',
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'bank_transfer',
            'gateway' => 'bank_transfer',
        ]);
    }
}