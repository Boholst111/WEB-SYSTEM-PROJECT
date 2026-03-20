<?php

namespace Database\Factories;

use App\Models\ShoppingCart;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShoppingCartFactory extends Factory
{
    protected $model = ShoppingCart::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'price' => fake()->randomFloat(2, 10, 500),
            'session_id' => fake()->optional()->regexify('[a-z0-9]{40}'),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'session_id' => null,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'price' => $product->current_price,
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'session_id' => fake()->regexify('[a-z0-9]{40}'),
        ]);
    }

    public function authenticated(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'session_id' => null,
        ]);
    }

    public function singleItem(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 1,
        ]);
    }

    public function multipleItems(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->numberBetween(2, 10),
        ]);
    }
}