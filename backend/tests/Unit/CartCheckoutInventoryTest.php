<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for cart and checkout inventory reservation and release
 * 
 * Requirements: 1.7
 */
class CartCheckoutInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product1;
    private Product $product2;
    private CheckoutService $checkoutService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'loyalty_tier' => 'bronze',
            'loyalty_credits' => 1000.00,
        ]);

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $this->product1 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 100.00,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $this->product2 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 150.00,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $this->checkoutService = app(CheckoutService::class);
    }

    /** @test */
    public function inventory_is_reserved_when_order_is_created()
    {
        // Add items to cart
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 3,
            'price' => 100.00,
        ]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product2->id,
            'quantity' => 2,
            'price' => 150.00,
        ]);

        $address = UserAddress::factory()->create(['user_id' => $this->user->id]);

        // Create order
        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertTrue($result['success']);

        // Verify inventory was reserved
        $this->product1->refresh();
        $this->product2->refresh();
        
        $this->assertEquals(7, $this->product1->stock_quantity); // 10 - 3
        $this->assertEquals(3, $this->product2->stock_quantity); // 5 - 2
    }

    /** @test */
    public function inventory_reservation_is_atomic_across_multiple_products()
    {
        // Add items to cart where one product has insufficient stock
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 3,
            'price' => 100.00,
        ]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product2->id,
            'quantity' => 10, // More than available (5)
            'price' => 150.00,
        ]);

        $address = UserAddress::factory()->create(['user_id' => $this->user->id]);

        // Attempt to create order
        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertFalse($result['success']);

        // Verify NO inventory was reserved for either product (atomic rollback)
        $this->product1->refresh();
        $this->product2->refresh();
        
        $this->assertEquals(10, $this->product1->stock_quantity); // Unchanged
        $this->assertEquals(5, $this->product2->stock_quantity); // Unchanged
    }

    /** @test */
    public function cart_is_not_cleared_when_order_creation_fails()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 15, // More than available
            'price' => 100.00,
        ]);

        $address = UserAddress::factory()->create(['user_id' => $this->user->id]);

        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertFalse($result['success']);

        // Verify cart still has items
        $cartItems = ShoppingCart::where('user_id', $this->user->id)->get();
        $this->assertCount(1, $cartItems);
        $this->assertEquals(15, $cartItems->first()->quantity);
    }

    /** @test */
    public function cart_is_cleared_only_after_successful_order_creation()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $address = UserAddress::factory()->create(['user_id' => $this->user->id]);

        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertTrue($result['success']);

        // Verify cart was cleared
        $cartItems = ShoppingCart::where('user_id', $this->user->id)->get();
        $this->assertCount(0, $cartItems);
    }

    /** @test */
    public function order_items_are_created_with_correct_quantities()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 3,
            'price' => 100.00,
        ]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product2->id,
            'quantity' => 2,
            'price' => 150.00,
        ]);

        $address = UserAddress::factory()->create(['user_id' => $this->user->id]);

        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertTrue($result['success']);

        $order = Order::find($result['data']['order_id']);
        $orderItems = $order->items;

        $this->assertCount(2, $orderItems);
        
        $item1 = $orderItems->where('product_id', $this->product1->id)->first();
        $item2 = $orderItems->where('product_id', $this->product2->id)->first();

        $this->assertEquals(3, $item1->quantity);
        $this->assertEquals(2, $item2->quantity);
        $this->assertEquals(100.00, $item1->unit_price);
        $this->assertEquals(150.00, $item2->unit_price);
    }

    /** @test */
    public function concurrent_orders_cannot_over_reserve_inventory()
    {
        // Simulate two users trying to order the same product
        $user2 = User::factory()->create(['loyalty_credits' => 500.00]);

        // User 1 adds 8 items to cart
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 8,
            'price' => 100.00,
        ]);

        // User 2 adds 5 items to cart (total would be 13, but only 10 available)
        ShoppingCart::create([
            'user_id' => $user2->id,
            'product_id' => $this->product1->id,
            'quantity' => 5,
            'price' => 100.00,
        ]);

        $address1 = UserAddress::factory()->create(['user_id' => $this->user->id]);
        $address2 = UserAddress::factory()->create(['user_id' => $user2->id]);

        // User 1 creates order first
        $result1 = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address1->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertTrue($result1['success']);
        
        // Verify inventory was reserved
        $this->product1->refresh();
        $this->assertEquals(2, $this->product1->stock_quantity); // 10 - 8

        // User 2 tries to create order (should fail due to insufficient stock)
        $result2 = $this->checkoutService->createOrder($user2, [
            'shipping_address_id' => $address2->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertFalse($result2['success']);
        
        // Verify inventory wasn't over-reserved
        $this->product1->refresh();
        $this->assertEquals(2, $this->product1->stock_quantity); // Still 2
    }

    /** @test */
    public function loyalty_credits_are_not_deducted_when_order_creation_fails()
    {
        $initialCredits = $this->user->loyalty_credits;

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 15, // More than available
            'price' => 100.00,
        ]);

        $address = UserAddress::factory()->create(['user_id' => $this->user->id]);

        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
            'credits_to_use' => 100.00,
        ]);

        $this->assertFalse($result['success']);

        // Verify credits were not deducted
        $this->user->refresh();
        $this->assertEquals($initialCredits, $this->user->loyalty_credits);
    }

    /** @test */
    public function loyalty_credits_are_deducted_when_order_is_created()
    {
        $initialCredits = $this->user->loyalty_credits;

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $address = UserAddress::factory()->create(['user_id' => $this->user->id]);

        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
            'credits_to_use' => 100.00,
        ]);

        $this->assertTrue($result['success']);

        // Verify credits were deducted
        $this->user->refresh();
        $this->assertEquals($initialCredits - 100.00, $this->user->loyalty_credits);
    }

    /** @test */
    public function order_totals_are_calculated_correctly_with_all_components()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product2->id,
            'quantity' => 1,
            'price' => 150.00,
        ]);

        $address = UserAddress::factory()->create(['user_id' => $this->user->id]);

        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
            'credits_to_use' => 50.00,
        ]);

        $this->assertTrue($result['success']);

        $order = Order::find($result['data']['order_id']);

        // Subtotal: (2 * 100) + (1 * 150) = 350
        // Credits: 50
        // Shipping: 120 (JNT standard)
        // Total: 350 - 50 + 120 = 420

        $this->assertEquals(350.00, $order->subtotal);
        $this->assertEquals(50.00, $order->credits_used);
        $this->assertEquals(50.00, $order->discount_amount);
        $this->assertEquals(120.00, $order->shipping_fee);
        $this->assertEquals(420.00, $order->total_amount);
    }

    /** @test */
    public function cart_persistence_works_across_sessions()
    {
        // Add items to cart
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // Simulate logout/login by refreshing user
        $this->user->refresh();

        // Verify cart items are still there
        $cartItems = ShoppingCart::where('user_id', $this->user->id)->get();
        $this->assertCount(1, $cartItems);
        $this->assertEquals(2, $cartItems->first()->quantity);
    }

    /** @test */
    public function order_creation_validates_address_belongs_to_user()
    {
        $otherUser = User::factory()->create();
        $otherAddress = UserAddress::factory()->create(['user_id' => $otherUser->id]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // Try to use another user's address
        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $otherAddress->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid shipping address', $result['error']);
    }

    /** @test */
    public function order_stores_shipping_address_snapshot()
    {
        $address = UserAddress::factory()->create([
            'user_id' => $this->user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line_1' => '123 Main St',
            'city' => 'Manila',
        ]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertTrue($result['success']);

        $order = Order::find($result['data']['order_id']);
        $shippingAddress = $order->shipping_address;

        // Verify address was stored as JSON snapshot
        $this->assertIsArray($shippingAddress);
        $this->assertEquals('John', $shippingAddress['first_name']);
        $this->assertEquals('Doe', $shippingAddress['last_name']);
        $this->assertEquals('123 Main St', $shippingAddress['address_line_1']);
        $this->assertEquals('Manila', $shippingAddress['city']);

        // Modify original address
        $address->update(['first_name' => 'Jane']);

        // Verify order still has original address
        $order->refresh();
        $this->assertEquals('John', $order->shipping_address['first_name']);
    }

    /** @test */
    public function multiple_items_of_same_product_reserve_correct_quantity()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'quantity' => 7,
            'price' => 100.00,
        ]);

        $address = UserAddress::factory()->create(['user_id' => $this->user->id]);

        $result = $this->checkoutService->createOrder($this->user, [
            'shipping_address_id' => $address->id,
            'payment_method' => 'gcash',
            'shipping_option' => 'jnt:standard',
        ]);

        $this->assertTrue($result['success']);

        // Verify correct quantity was reserved
        $this->product1->refresh();
        $this->assertEquals(3, $this->product1->stock_quantity); // 10 - 7

        // Verify order item has correct quantity
        $order = Order::find($result['data']['order_id']);
        $orderItem = $order->items->first();
        $this->assertEquals(7, $orderItem->quantity);
    }
}
