<?php

namespace Database\Factories;

use App\Models\ProductReview;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'order_id' => fake()->optional()->randomElement([Order::factory(), null]),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional()->sentence(4),
            'review_text' => fake()->paragraph(),
            'images' => fake()->optional()->randomElement([
                [fake()->imageUrl(400, 300), fake()->imageUrl(400, 300)],
                null,
            ]),
            'is_verified_purchase' => fake()->boolean(70), // 70% chance
            'is_approved' => fake()->boolean(90), // 90% chance
            'approved_at' => fake()->optional()->dateTimeThisMonth(),
            'approved_by' => fake()->optional()->randomElement([User::factory(), null]),
            'helpful_votes' => fake()->numberBetween(0, 50),
            'total_votes' => fake()->numberBetween(0, 100),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'is_verified_purchase' => true,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified_purchase' => true,
            'order_id' => Order::factory(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified_purchase' => false,
            'order_id' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
        ]);
    }

    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }

    public function fiveStars(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => 5,
            'title' => 'Excellent product!',
            'review_text' => 'This is an amazing diecast model with incredible attention to detail. Highly recommended!',
        ]);
    }

    public function oneStar(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => 1,
            'title' => 'Disappointed',
            'review_text' => 'The quality was not as expected. Several issues with the product.',
        ]);
    }

    public function withoutTitle(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => null,
        ]);
    }

    public function helpful(): static
    {
        return $this->state(fn (array $attributes) => [
            'helpful_votes' => fake()->numberBetween(10, 100),
            'total_votes' => fake()->numberBetween(15, 120),
        ]);
    }

    public function notHelpful(): static
    {
        return $this->state(fn (array $attributes) => [
            'helpful_votes' => 0,
            'total_votes' => fake()->numberBetween(0, 10),
        ]);
    }
}