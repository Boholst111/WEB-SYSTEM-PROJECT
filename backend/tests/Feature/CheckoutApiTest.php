<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\UserAddress;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class CheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'loyalty_credits' => 500.00,
        ]);
    }

    /** @test */
    public function it_initializes_checkout_successfully()
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create([
            'current_price' => 100.00,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        ShoppingCart::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        UserAddress::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/checkout/initialize');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'cart_summary',
                    'addresses',
                    'payment_methods',
                    'totals',
                ],
            ]);
    }

    /** @test */
    public function it_fails_to_initialize_checkout_with_empty_cart()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/checkout/initialize');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cart is empty',
            ]);
    }

    /** @test */
    public function it_calculates_checkout_totals()
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create([
            'current_price' => 100.00,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        ShoppingCart::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->postJson('/api/checkout/calculate-totals', [
            'credits_to_use' => 50.00,
            'shipping_option' => 'jnt:standard',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'subtotal',
                    'credits_applied',
                    'shipping_cost',
                    'total',
                ],
            ]);
    }

    /** @test */
    public function it_gets_user_addresses()
    {
        Sanctum::actingAs($this->user);

        UserAddress::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/checkout/addresses');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_creates_new_address()
    {
        Sanctum::actingAs($this->user);

        $addressData = [
            'type' => 'shipping',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line_1' => '123 Main St',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
            'country' => 'Philippines',
            'phone' => '+639123456789',
            'is_default' => true,
        ];

        $response = $this->postJson('/api/checkout/addresses', $addressData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Address created successfully',
            ]);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $this->user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_default' => true,
        ]);
    }

    /** @test */
    public function it_updates_address()
    {
        Sanctum::actingAs($this->user);

        $address = UserAddress::factory()->create([
            'user_id' => $this->user->id,
            'first_name' => 'John',
        ]);

        $response = $this->putJson("/api/checkout/addresses/{$address->id}", [
            'type' => 'shipping',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'postal_code' => '1100',
            'country' => 'Philippines',
            'phone' => '+639123456789',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Address updated successfully',
            ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $address->id,
            'first_name' => 'Jane',
        ]);
    }

    /** @test */
    public function it_deletes_address()
    {
        Sanctum::actingAs($this->user);

        $address = UserAddress::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/checkout/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Address deleted successfully',
            ]);

        $this->assertDatabaseMissing('user_addresses', [
            'id' => $address->id,
        ]);
    }

    /** @test */
    public function it_creates_order_successfully()
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create([
            'current_price' => 100.00,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        ShoppingCart::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $address = UserAddress::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/checkout/orders', [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
            'credits_to_use' => 50.00,
            'notes' => 'Test order',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Order created successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'order_id',
                    'order_number',
                    'total_amount',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING,
        ]);
    }

    /** @test */
    public function it_validates_order_creation_data()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/checkout/orders', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonValidationErrors([
                'shipping_address_id',
                'payment_method',
                'shipping_option',
            ]);
    }

    /** @test */
    public function it_gets_order_details()
    {
        Sanctum::actingAs($this->user);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/checkout/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'order',
                    'items',
                    'summary',
                ],
            ]);
    }

    /** @test */
    public function it_prevents_accessing_other_users_orders()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/checkout/orders/{$order->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Order not found',
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_checkout()
    {
        $response = $this->postJson('/api/checkout/initialize');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_sets_default_address_correctly()
    {
        Sanctum::actingAs($this->user);

        // Create first address as default
        $address1 = UserAddress::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);

        // Create second address as default
        $addressData = [
            'type' => 'shipping',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'postal_code' => '1100',
            'country' => 'Philippines',
            'phone' => '+639123456789',
            'is_default' => true,
        ];

        $response = $this->postJson('/api/checkout/addresses', $addressData);

        $response->assertStatus(201);

        // Verify first address is no longer default
        $address1->refresh();
        $this->assertFalse($address1->is_default);

        // Verify new address is default
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $this->user->id,
            'first_name' => 'Jane',
            'is_default' => true,
        ]);
    }
}
