<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 10, 500);
        $quantity = fake()->numberBetween(1, 5);
        
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_sku' => fake()->regexify('[A-Z]{2}[0-9]{6}'),
            'product_name' => fake()->words(3, true) . ' Diecast Model',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
            'product_snapshot' => [
                'name' => fake()->words(3, true) . ' Diecast Model',
                'images' => [fake()->imageUrl(400, 300, 'transport')],
                'specifications' => ['scale' => '1:64', 'material' => 'diecast'],
            ],
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'unit_price' => $product->current_price,
            'product_snapshot' => [
                'name' => $product->name,
                'images' => $product->images ?? [],
                'specifications' => $product->specifications ?? [],
            ],
        ]);
    }
}