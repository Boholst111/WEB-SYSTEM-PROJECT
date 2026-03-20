<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShoppingCartModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'product_id',
            'quantity',
            'price',
            'session_id',
        ];

        $cartItem = new ShoppingCart();
        $this->assertEquals($fillable, $cartItem->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $cartItem = ShoppingCart::factory()->create([
            'price' => '99.99',
        ]);

        $this->assertEquals(99.99, $cartItem->price);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $cartItem = ShoppingCart::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $cartItem->user);
        $this->assertEquals($user->id, $cartItem->user->id);
    }

    /** @test */
    public function it_belongs_to_product()
    {
        $product = Product::factory()->create();
        $cartItem = ShoppingCart::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $cartItem->product);
        $this->assertEquals($product->id, $cartItem->product->id);
    }

    /** @test */
    public function it_calculates_total_price()
    {
        $cartItem = ShoppingCart::factory()->create([
            'price' => 50.00,
            'quantity' => 3,
        ]);

        $this->assertEquals(150.00, $cartItem->getTotalAttribute());
    }

    /** @test */
    public function it_gets_formatted_total()
    {
        $cartItem = ShoppingCart::factory()->create([
            'price' => 75.50,
            'quantity' => 2,
        ]);

        $this->assertEquals('₱151.00', $cartItem->getFormattedTotalAttribute());
    }

    /** @test */
    public function it_handles_zero_quantity()
    {
        $cartItem = ShoppingCart::factory()->create([
            'price' => 50.00,
            'quantity' => 0,
        ]);

        $this->assertEquals(0.00, $cartItem->getTotalAttribute());
    }

    /** @test */
    public function it_handles_zero_price()
    {
        $cartItem = ShoppingCart::factory()->create([
            'price' => 0.00,
            'quantity' => 5,
        ]);

        $this->assertEquals(0.00, $cartItem->getTotalAttribute());
    }

    /** @test */
    public function it_can_have_session_id_for_guest_users()
    {
        $cartItem = ShoppingCart::factory()->create([
            'user_id' => null,
            'session_id' => 'guest_session_123',
        ]);

        $this->assertNull($cartItem->user_id);
        $this->assertEquals('guest_session_123', $cartItem->session_id);
    }

    /** @test */
    public function it_can_have_user_id_for_authenticated_users()
    {
        $user = User::factory()->create();
        $cartItem = ShoppingCart::factory()->create([
            'user_id' => $user->id,
            'session_id' => null,
        ]);

        $this->assertEquals($user->id, $cartItem->user_id);
        $this->assertNull($cartItem->session_id);
    }

    /** @test */
    public function it_handles_decimal_quantities()
    {
        $cartItem = ShoppingCart::factory()->create([
            'price' => 100.00,
            'quantity' => 2.5,
        ]);

        $this->assertEquals(250.00, $cartItem->getTotalAttribute());
    }

    /** @test */
    public function it_handles_large_quantities()
    {
        $cartItem = ShoppingCart::factory()->create([
            'price' => 10.00,
            'quantity' => 100,
        ]);

        $this->assertEquals(1000.00, $cartItem->getTotalAttribute());
        $this->assertEquals('₱1,000.00', $cartItem->getFormattedTotalAttribute());
    }

    /** @test */
    public function it_handles_small_prices()
    {
        $cartItem = ShoppingCart::factory()->create([
            'price' => 0.01,
            'quantity' => 1000,
        ]);

        $this->assertEquals(10.00, $cartItem->getTotalAttribute());
    }
}