<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCalculationsTest extends TestCase
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
    public function can_calculate_cart_totals_without_credits_or_shipping()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'subtotal' => 200.00,
                    'credits_applied' => 0,
                    'discount_amount' => 0,
                    'shipping_cost' => 0,
                    'total' => 200.00,
                ],
            ]);
    }

    /** @test */
    public function can_calculate_cart_totals_with_loyalty_credits()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals', [
                'credits_to_use' => 100.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'subtotal' => 200.00,
                    'credits_applied' => 100.00,
                    'discount_amount' => 100.00,
                    'shipping_cost' => 0,
                    'total' => 100.00,
                ],
            ]);
    }

    /** @test */
    public function cannot_use_more_credits_than_available()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // User has 500 credits, try to use 600
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals', [
                'credits_to_use' => 600.00,
            ]);

        $response->assertStatus(200);
        
        // Should only apply 100 (max 50% of 200 subtotal)
        $this->assertEquals(100.00, $response->json('data.credits_applied'));
    }

    /** @test */
    public function cannot_use_more_than_50_percent_of_subtotal()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // Subtotal is 200, max 50% = 100
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals', [
                'credits_to_use' => 150.00,
            ]);

        $response->assertStatus(200);
        $this->assertEquals(100.00, $response->json('data.credits_applied'));
    }

    /** @test */
    public function can_calculate_cart_totals_with_shipping()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals', [
                'shipping_option' => 'lbc:standard',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'subtotal' => 200.00,
                    'credits_applied' => 0,
                    'discount_amount' => 0,
                    'shipping_cost' => 150.00, // LBC standard rate
                    'total' => 350.00,
                ],
            ]);
    }

    /** @test */
    public function can_calculate_cart_totals_with_credits_and_shipping()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals', [
                'credits_to_use' => 50.00,
                'shipping_option' => 'lbc:standard',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'subtotal' => 200.00,
                    'credits_applied' => 50.00,
                    'discount_amount' => 50.00,
                    'shipping_cost' => 150.00,
                    'total' => 300.00, // 200 - 50 + 150
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
            ->postJson('/api/cart/calculate-totals', [
                'shipping_option' => 'lbc:standard',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'subtotal' => 2500.00,
                    'shipping_cost' => 0, // Free shipping
                    'total' => 2500.00,
                ],
            ]);
    }

    /** @test */
    public function platinum_tier_gets_free_shipping()
    {
        $this->user->update(['loyalty_tier' => 'platinum']);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals', [
                'shipping_option' => 'lbc:standard',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'shipping_cost' => 0, // Free for platinum
                ],
            ]);
    }

    /** @test */
    public function cannot_calculate_totals_for_empty_cart()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cart is empty',
            ]);
    }

    /** @test */
    public function can_validate_cart_inventory()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart/validate-inventory');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'errors' => [],
                ],
            ]);
    }

    /** @test */
    public function inventory_validation_detects_insufficient_stock()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 15, // More than available
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart/validate-inventory');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'valid' => false,
                ],
            ]);

        $errors = $response->json('data.errors');
        $this->assertCount(1, $errors);
        $this->assertEquals('Insufficient stock', $errors[0]['error']);
    }

    /** @test */
    public function inventory_validation_detects_unavailable_products()
    {
        $this->product->update(['status' => 'inactive']);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart/validate-inventory');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'valid' => false,
                ],
            ]);

        $errors = $response->json('data.errors');
        $this->assertCount(1, $errors);
        $this->assertEquals('Product is no longer available', $errors[0]['error']);
    }

    /** @test */
    public function calculate_totals_fails_when_inventory_invalid()
    {
        $this->product->update(['status' => 'inactive']);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Some items in your cart are no longer available',
            ]);
    }

    /** @test */
    public function formatted_values_are_correct()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals', [
                'credits_to_use' => 50.00,
                'shipping_option' => 'lbc:standard',
            ]);

        $response->assertStatus(200);

        $formatted = $response->json('data.formatted');
        $this->assertEquals('₱200.00', $formatted['subtotal']);
        $this->assertEquals('₱50.00', $formatted['credits_applied']);
        $this->assertEquals('₱50.00', $formatted['discount_amount']);
        $this->assertEquals('₱150.00', $formatted['shipping_cost']);
        $this->assertEquals('₱300.00', $formatted['total']);
    }

    /** @test */
    public function different_shipping_options_have_different_costs()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // Test LBC standard
        $response1 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals', [
                'shipping_option' => 'lbc:standard',
            ]);

        // Test LBC express
        $response2 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/calculate-totals', [
                'shipping_option' => 'lbc:express',
            ]);

        $this->assertEquals(150.00, $response1->json('data.shipping_cost'));
        $this->assertEquals(250.00, $response2->json('data.shipping_cost'));
    }
}
