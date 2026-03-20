<?php

namespace Database\Factories;

use App\Models\PreOrder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreOrderFactory extends Factory
{
    protected $model = PreOrder::class;

    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 500, 3000);
        $depositAmount = $totalAmount * 0.3; // 30% deposit
        $remainingAmount = $totalAmount - $depositAmount;
        
        return [
            'preorder_number' => 'PO' . fake()->dateTimeThisYear()->format('ymd') . fake()->numberBetween(1000, 9999),
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'deposit_amount' => $depositAmount,
            'remaining_amount' => $remainingAmount,
            'total_amount' => $totalAmount,
            'deposit_paid_at' => fake()->optional()->dateTimeThisMonth(),
            'full_payment_due_date' => fake()->optional()->dateTimeBetween('+1 week', '+2 months'),
            'status' => fake()->randomElement([
                PreOrder::STATUS_DEPOSIT_PENDING,
                PreOrder::STATUS_DEPOSIT_PAID,
                PreOrder::STATUS_READY_FOR_PAYMENT,
                PreOrder::STATUS_PAYMENT_COMPLETED,
                PreOrder::STATUS_SHIPPED,
                PreOrder::STATUS_DELIVERED,
                PreOrder::STATUS_CANCELLED,
            ]),
            'estimated_arrival_date' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'actual_arrival_date' => fake()->optional()->dateTimeBetween('+1 month', '+6 months'),
            'payment_method' => fake()->optional()->randomElement(['gcash', 'maya', 'bank_transfer']),
            'shipping_address' => [
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'address_line_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'province' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => 'Philippines',
                'phone' => fake()->phoneNumber(),
            ],
            'notes' => fake()->optional()->sentence(),
            'admin_notes' => fake()->optional()->sentence(),
            'notification_sent' => fake()->boolean(),
            'payment_reminder_sent_at' => fake()->optional()->dateTimeThisMonth(),
        ];
    }

    public function depositPending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_paid_at' => null,
            'full_payment_due_date' => null,
        ]);
    }

    public function depositPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
            'deposit_paid_at' => fake()->dateTimeThisMonth(),
            'payment_method' => fake()->randomElement(['gcash', 'maya', 'bank_transfer']),
        ]);
    }

    public function readyForPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'deposit_paid_at' => fake()->dateTimeThisMonth(),
            'full_payment_due_date' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'notification_sent' => true,
        ]);
    }

    public function paymentCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PreOrder::STATUS_PAYMENT_COMPLETED,
            'deposit_paid_at' => fake()->dateTimeThisMonth(),
            'full_payment_due_date' => fake()->dateTimeThisMonth(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PreOrder::STATUS_DELIVERED,
            'deposit_paid_at' => fake()->dateTimeThisMonth(),
            'actual_arrival_date' => fake()->dateTimeThisMonth(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PreOrder::STATUS_CANCELLED,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'full_payment_due_date' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }
}