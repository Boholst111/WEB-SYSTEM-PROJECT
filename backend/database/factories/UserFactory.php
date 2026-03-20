<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->date(),
            'loyalty_tier' => fake()->randomElement(['bronze', 'silver', 'gold', 'platinum']),
            'loyalty_credits' => fake()->randomFloat(2, 0, 1000),
            'total_spent' => fake()->randomFloat(2, 0, 50000),
            'email_verified_at' => now(),
            'phone_verified_at' => fake()->optional()->dateTime(),
            'status' => fake()->randomElement(['active', 'inactive', 'suspended']),
            'preferences' => [
                'theme' => fake()->randomElement(['light', 'dark']),
                'notifications' => fake()->boolean(),
                'newsletter' => fake()->boolean(),
            ],
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function bronze(): static
    {
        return $this->state(fn (array $attributes) => [
            'loyalty_tier' => 'bronze',
            'total_spent' => fake()->randomFloat(2, 0, 9999),
        ]);
    }

    public function silver(): static
    {
        return $this->state(fn (array $attributes) => [
            'loyalty_tier' => 'silver',
            'total_spent' => fake()->randomFloat(2, 10000, 49999),
        ]);
    }

    public function gold(): static
    {
        return $this->state(fn (array $attributes) => [
            'loyalty_tier' => 'gold',
            'total_spent' => fake()->randomFloat(2, 50000, 99999),
        ]);
    }

    public function platinum(): static
    {
        return $this->state(fn (array $attributes) => [
            'loyalty_tier' => 'platinum',
            'total_spent' => fake()->randomFloat(2, 100000, 500000),
        ]);
    }
}