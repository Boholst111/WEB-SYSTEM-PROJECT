<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $basePrice = fake()->randomFloat(2, 50, 2000);
        $currentPrice = fake()->boolean(70) ? $basePrice : fake()->randomFloat(2, $basePrice * 0.7, $basePrice * 0.95);
        
        return [
            'sku' => fake()->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'name' => fake()->words(3, true) . ' Diecast Model',
            'description' => fake()->paragraph(),
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'scale' => fake()->randomElement(['1:18', '1:24', '1:32', '1:43', '1:64', '1:87']),
            'material' => fake()->randomElement(['diecast', 'plastic', 'resin']),
            'features' => fake()->randomElements([
                'opening_doors', 'detailed_interior', 'rubber_tires', 'working_lights',
                'opening_hood', 'opening_trunk', 'steerable_wheels', 'detailed_engine'
            ], fake()->numberBetween(1, 4)),
            'is_chase_variant' => fake()->boolean(10), // 10% chance
            'base_price' => $basePrice,
            'current_price' => $currentPrice,
            'stock_quantity' => fake()->numberBetween(0, 100),
            'is_preorder' => fake()->boolean(20), // 20% chance
            'preorder_date' => fake()->optional()->dateTimeBetween('now', '+6 months'),
            'estimated_arrival_date' => fake()->optional()->dateTimeBetween('+1 month', '+8 months'),
            'status' => fake()->randomElement(['active', 'inactive', 'discontinued']),
            'images' => [
                fake()->imageUrl(800, 600, 'transport'),
                fake()->imageUrl(800, 600, 'transport'),
                fake()->imageUrl(800, 600, 'transport'),
            ],
            'specifications' => [
                'length' => fake()->randomFloat(1, 5, 25) . 'cm',
                'width' => fake()->randomFloat(1, 3, 12) . 'cm',
                'height' => fake()->randomFloat(1, 2, 8) . 'cm',
                'manufacturer' => fake()->company(),
            ],
            'weight' => fake()->randomFloat(2, 0.1, 2.0),
            'dimensions' => [
                'length' => fake()->randomFloat(1, 5, 25),
                'width' => fake()->randomFloat(1, 3, 12),
                'height' => fake()->randomFloat(1, 2, 8),
            ],
            'minimum_age' => fake()->randomElement([3, 8, 14]),
        ];
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

    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => fake()->numberBetween(1, 100),
            'is_preorder' => false,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
            'is_preorder' => false,
        ]);
    }

    public function preorder(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_preorder' => true,
            'preorder_date' => fake()->dateTimeBetween('now', '+3 months'),
            'estimated_arrival_date' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'stock_quantity' => 0,
        ]);
    }

    public function chaseVariant(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_chase_variant' => true,
            'stock_quantity' => fake()->numberBetween(1, 10), // Limited stock
        ]);
    }

    public function onSale(): static
    {
        return $this->state(function (array $attributes) {
            $basePrice = $attributes['base_price'] ?? fake()->randomFloat(2, 50, 2000);
            return [
                'base_price' => $basePrice,
                'current_price' => fake()->randomFloat(2, $basePrice * 0.6, $basePrice * 0.9),
            ];
        });
    }
}