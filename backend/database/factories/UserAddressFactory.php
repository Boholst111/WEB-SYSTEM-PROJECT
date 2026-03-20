<?php

namespace Database\Factories;

use App\Models\UserAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['shipping', 'billing']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => fake()->optional()->company(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'province' => fake()->randomElement([
                'Metro Manila', 'Cebu', 'Davao', 'Laguna', 'Cavite',
                'Bulacan', 'Rizal', 'Batangas', 'Pampanga', 'Quezon',
            ]),
            'postal_code' => fake()->postcode(),
            'country' => 'Philippines',
            'phone' => fake()->phoneNumber(),
            'is_default' => fake()->boolean(20), // 20% chance of being default
        ];
    }

    public function shipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'shipping',
        ]);
    }

    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'billing',
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function notDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => false,
        ]);
    }

    public function withCompany(): static
    {
        return $this->state(fn (array $attributes) => [
            'company' => fake()->company(),
        ]);
    }

    public function manila(): static
    {
        return $this->state(fn (array $attributes) => [
            'city' => fake()->randomElement(['Manila', 'Quezon City', 'Makati', 'Taguig', 'Pasig']),
            'province' => 'Metro Manila',
            'postal_code' => fake()->randomElement(['1000', '1100', '1200', '1600', '1700']),
        ]);
    }

    public function cebu(): static
    {
        return $this->state(fn (array $attributes) => [
            'city' => 'Cebu City',
            'province' => 'Cebu',
            'postal_code' => '6000',
        ]);
    }
}