<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        $quantityBefore = fake()->numberBetween(0, 200);
        $quantityChange = fake()->numberBetween(-50, 50);
        $quantityAfter = max(0, $quantityBefore + $quantityChange);
        
        return [
            'product_id' => Product::factory(),
            'movement_type' => fake()->randomElement([
                InventoryMovement::TYPE_PURCHASE,
                InventoryMovement::TYPE_SALE,
                InventoryMovement::TYPE_RETURN,
                InventoryMovement::TYPE_ADJUSTMENT,
                InventoryMovement::TYPE_DAMAGE,
                InventoryMovement::TYPE_RESERVATION,
                InventoryMovement::TYPE_RELEASE,
            ]),
            'quantity_change' => $quantityChange,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reference_type' => fake()->optional()->randomElement(['order', 'preorder', 'adjustment', 'return']),
            'reference_id' => fake()->optional()->regexify('[A-Z]{2}[0-9]{6}'),
            'reason' => fake()->sentence(),
            'created_by' => fake()->optional()->randomElement([User::factory(), null]),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    public function purchase(): static
    {
        return $this->state(function (array $attributes) {
            $quantityChange = fake()->numberBetween(10, 100);
            $quantityBefore = $attributes['quantity_before'] ?? fake()->numberBetween(0, 100);
            
            return [
                'movement_type' => InventoryMovement::TYPE_PURCHASE,
                'quantity_change' => $quantityChange,
                'quantity_after' => $quantityBefore + $quantityChange,
                'reference_type' => 'purchase_order',
                'reason' => 'Stock replenishment from supplier',
            ];
        });
    }

    public function sale(): static
    {
        return $this->state(function (array $attributes) {
            $quantityChange = -fake()->numberBetween(1, 10);
            $quantityBefore = $attributes['quantity_before'] ?? fake()->numberBetween(10, 100);
            
            return [
                'movement_type' => InventoryMovement::TYPE_SALE,
                'quantity_change' => $quantityChange,
                'quantity_after' => max(0, $quantityBefore + $quantityChange),
                'reference_type' => 'order',
                'reference_id' => 'ORD' . fake()->numberBetween(100000, 999999),
                'reason' => 'Sold to customer',
            ];
        });
    }

    public function return(): static
    {
        return $this->state(function (array $attributes) {
            $quantityChange = fake()->numberBetween(1, 5);
            $quantityBefore = $attributes['quantity_before'] ?? fake()->numberBetween(0, 100);
            
            return [
                'movement_type' => InventoryMovement::TYPE_RETURN,
                'quantity_change' => $quantityChange,
                'quantity_after' => $quantityBefore + $quantityChange,
                'reference_type' => 'return',
                'reference_id' => 'RET' . fake()->numberBetween(100000, 999999),
                'reason' => 'Customer return - ' . fake()->randomElement(['defective', 'wrong item', 'changed mind']),
            ];
        });
    }

    public function adjustment(): static
    {
        return $this->state(function (array $attributes) {
            $quantityChange = fake()->numberBetween(-20, 20);
            $quantityBefore = $attributes['quantity_before'] ?? fake()->numberBetween(20, 100);
            
            return [
                'movement_type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity_change' => $quantityChange,
                'quantity_after' => max(0, $quantityBefore + $quantityChange),
                'reference_type' => 'adjustment',
                'reason' => 'Inventory count correction',
                'created_by' => User::factory(),
            ];
        });
    }

    public function damage(): static
    {
        return $this->state(function (array $attributes) {
            $quantityChange = -fake()->numberBetween(1, 10);
            $quantityBefore = $attributes['quantity_before'] ?? fake()->numberBetween(10, 100);
            
            return [
                'movement_type' => InventoryMovement::TYPE_DAMAGE,
                'quantity_change' => $quantityChange,
                'quantity_after' => max(0, $quantityBefore + $quantityChange),
                'reference_type' => 'damage_report',
                'reason' => 'Damaged during ' . fake()->randomElement(['shipping', 'handling', 'storage']),
            ];
        });
    }

    public function reservation(): static
    {
        return $this->state(function (array $attributes) {
            $quantityChange = -fake()->numberBetween(1, 5);
            $quantityBefore = $attributes['quantity_before'] ?? fake()->numberBetween(5, 100);
            
            return [
                'movement_type' => InventoryMovement::TYPE_RESERVATION,
                'quantity_change' => $quantityChange,
                'quantity_after' => max(0, $quantityBefore + $quantityChange),
                'reference_type' => 'cart',
                'reference_id' => 'CART' . fake()->numberBetween(100000, 999999),
                'reason' => 'Reserved for checkout',
            ];
        });
    }

    public function release(): static
    {
        return $this->state(function (array $attributes) {
            $quantityChange = fake()->numberBetween(1, 5);
            $quantityBefore = $attributes['quantity_before'] ?? fake()->numberBetween(0, 100);
            
            return [
                'movement_type' => InventoryMovement::TYPE_RELEASE,
                'quantity_change' => $quantityChange,
                'quantity_after' => $quantityBefore + $quantityChange,
                'reference_type' => 'cart',
                'reference_id' => 'CART' . fake()->numberBetween(100000, 999999),
                'reason' => 'Released from abandoned cart',
            ];
        });
    }

    public function withReference(string $type, string $id): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $type,
            'reference_id' => $id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}