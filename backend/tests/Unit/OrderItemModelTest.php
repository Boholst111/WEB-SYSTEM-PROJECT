<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderItemModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'order_id',
            'product_id',
            'product_sku',
            'product_name',
            'quantity',
            'unit_price',
            'total_price',
            'product_snapshot',
        ];

        $orderItem = new OrderItem();
        $this->assertEquals($fillable, $orderItem->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $orderItem = OrderItem::factory()->create([
            'unit_price' => '99.99',
            'quantity' => 2, // This will make total_price = 199.98
            'product_snapshot' => ['name' => 'Test Product', 'scale' => '1:64'],
        ]);

        $this->assertEquals(99.99, $orderItem->unit_price);
        $this->assertEquals(199.98, $orderItem->total_price);
        $this->assertIsArray($orderItem->product_snapshot);
        $this->assertEquals('Test Product', $orderItem->product_snapshot['name']);
    }

    /** @test */
    public function it_belongs_to_order()
    {
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(Order::class, $orderItem->order);
        $this->assertEquals($order->id, $orderItem->order->id);
    }

    /** @test */
    public function it_belongs_to_product()
    {
        $product = Product::factory()->create();
        $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $orderItem->product);
        $this->assertEquals($product->id, $orderItem->product->id);
    }

    /** @test */
    public function it_calculates_total_manually()
    {
        $orderItem = new OrderItem([
            'unit_price' => 50.00,
            'quantity' => 3,
            'total_price' => 0, // Will be calculated
        ]);

        $orderItem->calculateTotal();

        $this->assertEquals(150.00, $orderItem->total_price);
    }

    /** @test */
    public function it_gets_formatted_price()
    {
        $orderItem = OrderItem::factory()->create(['unit_price' => 123.45]);
        
        $this->assertEquals('₱123.45', $orderItem->getFormattedPriceAttribute());
    }

    /** @test */
    public function it_gets_formatted_total()
    {
        $orderItem = OrderItem::factory()->create([
            'unit_price' => 123.45,
            'quantity' => 2, // This will make total_price = 246.90
        ]);
        
        $this->assertEquals('₱246.90', $orderItem->getFormattedTotalAttribute());
    }

    /** @test */
    public function it_automatically_calculates_total_on_save()
    {
        $orderItem = OrderItem::factory()->create([
            'unit_price' => 75.50,
            'quantity' => 2,
        ]);

        // The boot method should automatically calculate total
        $this->assertEquals(151.00, $orderItem->total_price);
    }

    /** @test */
    public function it_recalculates_total_when_price_changes()
    {
        $orderItem = OrderItem::factory()->create([
            'unit_price' => 50.00,
            'quantity' => 2,
        ]);

        $this->assertEquals(100.00, $orderItem->total_price);

        $orderItem->unit_price = 60.00;
        $orderItem->save();

        $this->assertEquals(120.00, $orderItem->total_price);
    }

    /** @test */
    public function it_recalculates_total_when_quantity_changes()
    {
        $orderItem = OrderItem::factory()->create([
            'unit_price' => 50.00,
            'quantity' => 2,
        ]);

        $this->assertEquals(100.00, $orderItem->total_price);

        $orderItem->quantity = 3;
        $orderItem->save();

        $this->assertEquals(150.00, $orderItem->total_price);
    }

    /** @test */
    public function it_handles_zero_quantity()
    {
        $orderItem = OrderItem::factory()->create([
            'unit_price' => 50.00,
            'quantity' => 0,
        ]);

        $this->assertEquals(0.00, $orderItem->total_price);
    }

    /** @test */
    public function it_handles_zero_price()
    {
        $orderItem = OrderItem::factory()->create([
            'unit_price' => 0.00,
            'quantity' => 5,
        ]);

        $this->assertEquals(0.00, $orderItem->total_price);
    }
}