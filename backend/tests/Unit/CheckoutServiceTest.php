<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\UserAddress;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\CartService;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CheckoutService $checkoutService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->checkoutService = app(CheckoutService::class);
        $this->user = User::factory()->create([
            'loyalty_credits' => 500.00,
        ]);
    }

    /** @test */
    public function it_initializes_checkout_with_valid_cart()
    {
        // Create products and add to cart
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

        // Create address
        UserAddress::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);

        $result = $this->checkoutService->initializeCheckout($this->user);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('cart_summary', $result['data']);
        $this->assertArrayHasKey('addresses', $result['data']);
        $this->assertArrayHasKey('payment_methods', $result['data']);
        $this->assertEquals(1, $result['data']['cart_summary']['items_count']);
    }

    /** @test */
    public function it_fails_to_initialize_checkout_with_empty_cart()
    {
        $result = $this->checkoutService->initializeCheckout($this->user);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart is empty', $result['error']);
    }

    /** @test */
    public function it_fails_to_initialize_checkout_with_insufficient_inventory()
    {
        $product = Product::factory()->create([
            'current_price' => 100.00,
            'stock_quantity' => 1,
            'status' => 'active',
        ]);

        ShoppingCart::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 5, // More than available
            'price' => 100.00,
        ]);

        $result = $this->checkoutService->initializeCheckout($this->user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no longer available', $result['error']);
    }

    /** @test */
    public function it_calculates_checkout_totals_correctly()
    {
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

        $result = $this->checkoutService->calculateCheckoutTotals($this->user, [
            'credits_to_use' => 50.00,
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(200.00, $result['data']['subtotal']);
        $this->assertEquals(50.00, $result['data']['credits_applied']);
    }

    /** @test */
    public function it_creates_order_successfully()
    {
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

        $orderData = [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
            'credits_to_use' => 50.00,
            'notes' => 'Test order',
        ];

        $result = $this->checkoutService->createOrder($this->user, $orderData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('order', $result['data']);
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING,
        ]);

        // Verify inventory was reserved
        $product->refresh();
        $this->assertEquals(8, $product->stock_quantity);

        // Verify cart was cleared
        $this->assertEquals(0, ShoppingCart::where('user_id', $this->user->id)->count());

        // Verify loyalty credits were deducted
        $this->user->refresh();
        $this->assertEquals(450.00, $this->user->loyalty_credits);
    }

    /** @test */
    public function it_fails_to_create_order_with_invalid_address()
    {
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

        $orderData = [
            'shipping_address_id' => 999, // Non-existent address
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ];

        $result = $this->checkoutService->createOrder($this->user, $orderData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid shipping address', $result['error']);
    }

    /** @test */
    public function it_fails_to_create_order_with_insufficient_credits()
    {
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

        $orderData = [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
            'credits_to_use' => 1000.00, // More than available
        ];

        $result = $this->checkoutService->createOrder($this->user, $orderData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Insufficient loyalty credits', $result['error']);
    }

    /** @test */
    public function it_reserves_inventory_during_order_creation()
    {
        $product1 = Product::factory()->create([
            'current_price' => 100.00,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $product2 = Product::factory()->create([
            'current_price' => 50.00,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        ShoppingCart::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product1->id,
            'quantity' => 3,
            'price' => 100.00,
        ]);

        ShoppingCart::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 2,
            'price' => 50.00,
        ]);

        $address = UserAddress::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $orderData = [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ];

        $result = $this->checkoutService->createOrder($this->user, $orderData);

        $this->assertTrue($result['success']);

        // Verify inventory was reserved for both products
        $product1->refresh();
        $product2->refresh();
        $this->assertEquals(7, $product1->stock_quantity);
        $this->assertEquals(3, $product2->stock_quantity);
    }

    /** @test */
    public function it_rolls_back_order_creation_on_inventory_failure()
    {
        $product = Product::factory()->create([
            'current_price' => 100.00,
            'stock_quantity' => 1,
            'status' => 'active',
        ]);

        ShoppingCart::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 5, // More than available
            'price' => 100.00,
        ]);

        $address = UserAddress::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $orderData = [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ];

        $result = $this->checkoutService->createOrder($this->user, $orderData);

        $this->assertFalse($result['success']);

        // Verify no order was created
        $this->assertEquals(0, Order::where('user_id', $this->user->id)->count());

        // Verify cart was not cleared
        $this->assertEquals(1, ShoppingCart::where('user_id', $this->user->id)->count());

        // Verify inventory was not changed
        $product->refresh();
        $this->assertEquals(1, $product->stock_quantity);
    }
}
