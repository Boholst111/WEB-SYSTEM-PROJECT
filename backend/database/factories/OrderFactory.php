<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 5000);
        $creditsUsed = fake()->randomFloat(2, 0, min($subtotal * 0.2, 500));
        $discountAmount = fake()->randomFloat(2, 0, $subtotal * 0.1);
        $shippingFee = fake()->randomFloat(2, 0, 200);
        $taxAmount = fake()->randomFloat(2, 0, $subtotal * 0.12);
        $totalAmount = $subtotal - $creditsUsed - $discountAmount + $shippingFee + $taxAmount;
        
        return [
            'order_number' => 'DE' . fake()->dateTimeThisYear()->format('ymd') . fake()->numberBetween(1000, 9999),
            'user_id' => User::factory(),
            'status' => fake()->randomElement([
                Order::STATUS_PENDING,
                Order::STATUS_CONFIRMED,
                Order::STATUS_PROCESSING,
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
            ]),
            'subtotal' => $subtotal,
            'credits_used' => $creditsUsed,
            'discount_amount' => $discountAmount,
            'shipping_fee' => $shippingFee,
            'tax_amount' => $taxAmount,
            'total_amount' => max(0, $totalAmount),
            'payment_method' => fake()->randomElement(['gcash', 'maya', 'bank_transfer', 'credit_card']),
            'payment_status' => fake()->randomElement([
                Order::PAYMENT_PENDING,
                Order::PAYMENT_PAID,
                Order::PAYMENT_FAILED,
                Order::PAYMENT_REFUNDED,
            ]),
            'shipping_address' => [
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'address_line_1' => fake()->streetAddress(),
                'address_line_2' => fake()->optional()->secondaryAddress(),
                'city' => fake()->city(),
                'province' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => 'Philippines',
                'phone' => fake()->phoneNumber(),
            ],
            'billing_address' => [
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'address_line_1' => fake()->streetAddress(),
                'address_line_2' => fake()->optional()->secondaryAddress(),
                'city' => fake()->city(),
                'province' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => 'Philippines',
                'phone' => fake()->phoneNumber(),
            ],
            'tracking_number' => fake()->optional()->regexify('[A-Z]{2}[0-9]{10}'),
            'courier_service' => fake()->optional()->randomElement(['LBC', 'J&T', '2GO', 'Ninja Van']),
            'shipped_at' => fake()->optional()->dateTimeThisMonth(),
            'delivered_at' => fake()->optional()->dateTimeThisMonth(),
            'notes' => fake()->optional()->sentence(),
            'admin_notes' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'shipped_at' => null,
            'delivered_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PAID,
            'shipped_at' => null,
            'delivered_at' => null,
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_SHIPPED,
            'payment_status' => Order::PAYMENT_PAID,
            'shipped_at' => fake()->dateTimeThisMonth(),
            'delivered_at' => null,
            'tracking_number' => fake()->regexify('[A-Z]{2}[0-9]{10}'),
            'courier_service' => fake()->randomElement(['LBC', 'J&T', '2GO', 'Ninja Van']),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_PAID,
            'shipped_at' => fake()->dateTimeThisMonth(),
            'delivered_at' => fake()->dateTimeThisMonth(),
            'tracking_number' => fake()->regexify('[A-Z]{2}[0-9]{10}'),
            'courier_service' => fake()->randomElement(['LBC', 'J&T', '2GO', 'Ninja Van']),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
            'shipped_at' => null,
            'delivered_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => Order::PAYMENT_PAID,
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => Order::PAYMENT_PENDING,
        ]);
    }
}