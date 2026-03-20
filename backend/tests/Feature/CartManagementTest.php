<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'loyalty_tier' => 'bronze',
            'loyalty_credits' => 500.00,
        ]);

        // Create brand and category
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        // Create test product
        $this->product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 100.00,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function user_can_view_empty_cart()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'items' => [],
                    'summary' => [
                        'subtotal' => 0,
                        'items_count' => 0,
                        'total_quantity' => 0,
                    ],
                ],
            ]);
    }

    /** @test */
    public function user_can_add_item_to_cart()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Item added to cart successfully',
                'data' => [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'price' => 100.00,
                ],
            ]);

        $this->assertDatabaseHas('shopping_cart', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
    }

    /** @test */
    public function adding_same_product_increases_quantity()
    {
        // Add product first time
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        // Add same product again
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 3,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'quantity' => 5, // 2 + 3
                ],
            ]);

        $this->assertDatabaseHas('shopping_cart', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
    }

    /** @test */
    public function cannot_add_more_than_available_stock()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 15, // More than stock_quantity of 10
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient stock available',
                'available_quantity' => 10,
            ]);

        $this->assertDatabaseMissing('shopping_cart', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    /** @test */
    public function cannot_add_unavailable_product()
    {
        $this->product->update(['status' => 'inactive']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Product is not available for purchase',
            ]);
    }

    /** @test */
    public function user_can_update_cart_item_quantity()
    {
        $cartItem = ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/cart/items/{$cartItem->id}", [
                'quantity' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cart item updated successfully',
                'data' => [
                    'quantity' => 5,
                ],
            ]);

        $this->assertDatabaseHas('shopping_cart', [
            'id' => $cartItem->id,
            'quantity' => 5,
        ]);
    }

    /** @test */
    public function cannot_update_quantity_beyond_stock()
    {
        $cartItem = ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/cart/items/{$cartItem->id}", [
                'quantity' => 15, // More than stock
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient stock available',
            ]);
    }

    /** @test */
    public function user_can_remove_item_from_cart()
    {
        $cartItem = ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/cart/items/{$cartItem->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Item removed from cart successfully',
            ]);

        $this->assertDatabaseMissing('shopping_cart', [
            'id' => $cartItem->id,
        ]);
    }

    /** @test */
    public function user_cannot_remove_another_users_cart_item()
    {
        $otherUser = User::factory()->create();
        $cartItem = ShoppingCart::create([
            'user_id' => $otherUser->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/cart/items/{$cartItem->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Cart item not found',
            ]);

        $this->assertDatabaseHas('shopping_cart', [
            'id' => $cartItem->id,
        ]);
    }

    /** @test */
    public function user_can_clear_entire_cart()
    {
        // Add multiple items
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $product2 = Product::factory()->create([
            'brand_id' => $this->product->brand_id,
            'category_id' => $this->product->category_id,
            'current_price' => 150.00,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'price' => 150.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/cart/clear');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cart cleared successfully',
            ]);

        $this->assertDatabaseMissing('shopping_cart', [
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function cart_calculates_subtotal_correctly()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $product2 = Product::factory()->create([
            'brand_id' => $this->product->brand_id,
            'category_id' => $this->product->category_id,
            'current_price' => 150.00,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 3,
            'price' => 150.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => [
                        'subtotal' => 650.00, // (2 * 100) + (3 * 150)
                        'items_count' => 2,
                        'total_quantity' => 5,
                    ],
                ],
            ]);
    }

    /** @test */
    public function cart_shows_loyalty_credits_information()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'loyalty' => [
                        'available_credits',
                        'max_credits_usable',
                        'formatted_available',
                        'formatted_max_usable',
                    ],
                ],
            ]);

        // Subtotal is 200, max 50% = 100
        $this->assertEquals(100.00, $response->json('data.loyalty.max_credits_usable'));
    }

    /** @test */
    public function cart_shows_shipping_options()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'shipping_options' => [
                        'options',
                        'free_shipping_threshold',
                        'is_free_shipping_eligible',
                        'amount_to_free_shipping',
                    ],
                ],
            ]);
    }

    /** @test */
    public function free_shipping_applies_when_threshold_met()
    {
        // Create cart with value above free shipping threshold (2000)
        $expensiveProduct = Product::factory()->create([
            'brand_id' => $this->product->brand_id,
            'category_id' => $this->product->category_id,
            'current_price' => 2500.00,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $expensiveProduct->id,
            'quantity' => 1,
            'price' => 2500.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $response->assertStatus(200);
        
        $shippingOptions = $response->json('data.shipping_options');
        $this->assertTrue($shippingOptions['is_free_shipping_eligible']);
        $this->assertEquals(0, $shippingOptions['amount_to_free_shipping']);
        
        // All shipping options should be free
        foreach ($shippingOptions['options'] as $option) {
            $this->assertEquals(0, $option['cost']);
            $this->assertTrue($option['is_free']);
        }
    }

    /** @test */
    public function platinum_tier_gets_free_shipping_on_all_orders()
    {
        $this->user->update(['loyalty_tier' => 'platinum']);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $response->assertStatus(200);
        
        $shippingOptions = $response->json('data.shipping_options');
        $this->assertTrue($shippingOptions['is_free_shipping_eligible']);
        
        // All shipping options should be free for platinum
        foreach ($shippingOptions['options'] as $option) {
            $this->assertEquals(0, $option['cost']);
        }
    }

    /** @test */
    public function cart_validates_required_fields()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'quantity']);
    }

    /** @test */
    public function cart_validates_quantity_is_positive()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    /** @test */
    public function cart_validates_product_exists()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => 99999,
                'quantity' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    /** @test */
    public function cart_persists_across_sessions()
    {
        // Add item to cart
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        // Simulate new session by creating new token
        $this->user->tokens()->delete();
        $this->user->createToken('test-token');

        // Cart should still have items
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => [
                        'items_count' => 1,
                        'total_quantity' => 2,
                    ],
                ],
            ]);
    }

    /** @test */
    public function cart_updates_price_when_product_price_changes()
    {
        $cartItem = ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // Change product price
        $this->product->update(['current_price' => 120.00]);

        // Update cart item
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/cart/items/{$cartItem->id}", [
                'quantity' => 2,
            ]);

        $response->assertStatus(200);

        // Price should be updated to current price
        $this->assertDatabaseHas('shopping_cart', [
            'id' => $cartItem->id,
            'price' => 120.00,
        ]);
    }
}
