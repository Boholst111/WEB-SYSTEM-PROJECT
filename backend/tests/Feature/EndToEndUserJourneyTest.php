<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * End-to-End User Journey Test
 * 
 * Tests complete user workflows from discovery to purchase completion,
 * validating all system components work together seamlessly.
 * 
 * **Feature: diecast-empire, End-to-End Testing**
 */
class EndToEndUserJourneyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: New user discovers product, registers, and completes purchase
     * 
     * @test
     */
    public function test_new_user_complete_purchase_journey()
    {
        // Setup: Create test products
        $category = Category::factory()->create(['name' => 'Hot Wheels']);
        $brand = Brand::factory()->create(['name' => 'Mattel']);
        
        $product = Product::factory()->create([
            'name' => 'Hot Wheels Porsche 911 GT3',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'scale' => '1:64',
            'material' => 'diecast',
            'current_price' => 250.00,
            'stock_quantity' => 50,
            'status' => 'active',
        ]);

        // Step 1: Browse products (unauthenticated)
        $browseResponse = $this->getJson('/api/products');
        $browseResponse->assertStatus(200);
        $browseResponse->assertJsonFragment(['name' => 'Hot Wheels Porsche 911 GT3']);

        // Step 2: Search for specific product
        $searchResponse = $this->postJson('/api/search', [
            'query' => 'Porsche 911',
        ]);
        $searchResponse->assertStatus(200);
        $searchResponse->assertJsonFragment(['name' => 'Hot Wheels Porsche 911 GT3']);

        // Step 3: View product details
        $detailResponse = $this->getJson("/api/products/{$product->id}");
        $detailResponse->assertStatus(200);
        $detailResponse->assertJsonPath('data.name', 'Hot Wheels Porsche 911 GT3');

        // Step 4: User registers
        $registerResponse = $this->postJson('/api/auth/register', [
            'email' => 'collector@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'first_name' => 'John',
            'last_name' => 'Collector',
            'phone' => '+639171234567',
        ]);
        $registerResponse->assertStatus(201);
        $token = $registerResponse->json('token');

        // Step 5: Add product to cart
        $addToCartResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $addToCartResponse->assertStatus(201);

        // Step 6: View cart
        $viewCartResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/cart');
        $viewCartResponse->assertStatus(200);
        $viewCartResponse->assertJsonPath('items.0.quantity', 2);
        $viewCartResponse->assertJsonPath('items.0.product.name', 'Hot Wheels Porsche 911 GT3');

        // Step 7: Calculate totals
        $totalsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/cart/calculate-totals');
        $totalsResponse->assertStatus(200);
        $this->assertEquals(500.00, $totalsResponse->json('subtotal')); // 2 x 250

        // Step 8: Initialize checkout
        $checkoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/checkout/initialize');
        $checkoutResponse->assertStatus(200);

        // Step 9: Create shipping address
        $addressResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/checkout/addresses', [
            'street' => '123 Collector Street',
            'barangay' => 'Barangay 1',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'postal_code' => '1100',
            'country' => 'Philippines',
            'is_default' => true,
        ]);
        $addressResponse->assertStatus(201);
        $addressId = $addressResponse->json('data.id');

        // Step 10: Create order
        $orderResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/checkout/orders', [
            'address_id' => $addressId,
            'payment_method' => 'gcash',
            'notes' => 'Please pack carefully',
        ]);
        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');

        // Step 11: Process payment
        $paymentResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/checkout/orders/{$orderId}/payment", [
            'payment_method' => 'gcash',
            'amount' => 500.00,
        ]);
        $paymentResponse->assertStatus(200);

        // Step 12: Verify order created
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'total_amount' => 500.00,
        ]);

        // Step 13: Verify inventory updated
        $product->refresh();
        $this->assertEquals(48, $product->stock_quantity); // 50 - 2

        // Step 14: Verify loyalty credits earned
        $user = User::where('email', 'collector@example.com')->first();
        $this->assertGreaterThan(0, $user->loyalty_credits);
    }

    /**
     * Test: Existing user with loyalty credits makes purchase
     * 
     * @test
     */
    public function test_existing_user_with_loyalty_credits_purchase()
    {
        // Setup
        $user = User::factory()->create([
            'loyalty_credits' => 100.00,
            'loyalty_tier' => 'silver',
        ]);

        $product = Product::factory()->create([
            'current_price' => 500.00,
            'stock_quantity' => 20,
        ]);

        // Login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');

        // Check loyalty balance
        $balanceResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/loyalty/balance');
        $balanceResponse->assertStatus(200);
        $balanceResponse->assertJsonPath('balance', 100.00);

        // Add to cart
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // Create order with loyalty credits
        $orderResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/checkout/orders', [
            'shipping_address' => [
                'street' => '456 Main St',
                'city' => 'Manila',
                'province' => 'Metro Manila',
                'postal_code' => '1000',
                'country' => 'Philippines',
            ],
            'payment_method' => 'gcash',
            'use_loyalty_credits' => true,
            'loyalty_credits_amount' => 50.00,
        ]);
        $orderResponse->assertStatus(201);

        // Verify order total reduced by credits
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'credits_used' => 50.00,
            'total_amount' => 450.00, // 500 - 50
        ]);

        // Verify loyalty credits deducted
        $user->refresh();
        $this->assertEquals(50.00, $user->loyalty_credits); // 100 - 50
    }

    /**
     * Test: User pre-orders future release with deposit
     * 
     * @test
     */
    public function test_user_preorder_with_deposit_journey()
    {
        // Setup
        $user = User::factory()->create();
        
        $preorderProduct = Product::factory()->create([
            'name' => 'Limited Edition Ferrari F40',
            'is_preorder' => true,
            'preorder_date' => now()->addDays(60),
            'current_price' => 3000.00,
            'stock_quantity' => 0,
        ]);

        // Login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('token');

        // Browse pre-orders
        $preordersResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/preorders');
        $preordersResponse->assertStatus(200);

        // Create pre-order
        $createPreorderResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/preorders', [
            'product_id' => $preorderProduct->id,
            'quantity' => 1,
        ]);
        $createPreorderResponse->assertStatus(201);
        $preorderId = $createPreorderResponse->json('data.id');

        // Pay deposit (30% of total)
        $depositResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/preorders/{$preorderId}/deposit", [
            'payment_method' => 'gcash',
        ]);
        $depositResponse->assertStatus(200);

        // Verify pre-order status
        $statusResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/preorders/{$preorderId}/status");
        $statusResponse->assertStatus(200);
        $statusResponse->assertJsonPath('data.status', 'deposit_paid');
        $statusResponse->assertJsonPath('data.deposit_amount', 900.00); // 30% of 3000
        $statusResponse->assertJsonPath('data.remaining_amount', 2100.00);

        // Verify database
        $this->assertDatabaseHas('preorders', [
            'id' => $preorderId,
            'user_id' => $user->id,
            'product_id' => $preorderProduct->id,
            'status' => 'deposit_paid',
        ]);
    }

    /**
     * Test: User searches, filters, and discovers products
     * 
     * @test
     */
    public function test_user_product_discovery_journey()
    {
        // Setup diverse products
        $category1 = Category::factory()->create(['name' => 'Hot Wheels']);
        $category2 = Category::factory()->create(['name' => 'Tomica']);
        
        $brand1 = Brand::factory()->create(['name' => 'Mattel']);
        $brand2 = Brand::factory()->create(['name' => 'Takara Tomy']);

        Product::factory()->count(5)->create([
            'category_id' => $category1->id,
            'brand_id' => $brand1->id,
            'scale' => '1:64',
            'material' => 'diecast',
        ]);

        Product::factory()->count(3)->create([
            'category_id' => $category2->id,
            'brand_id' => $brand2->id,
            'scale' => '1:64',
            'material' => 'diecast',
        ]);

        Product::factory()->count(2)->create([
            'category_id' => $category1->id,
            'brand_id' => $brand1->id,
            'scale' => '1:43',
            'material' => 'resin',
        ]);

        // Browse all products
        $allProductsResponse = $this->getJson('/api/products');
        $allProductsResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(10, count($allProductsResponse->json('data')));

        // Filter by scale
        $scaleFilterResponse = $this->getJson('/api/products?scale=1:64');
        $scaleFilterResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(8, count($scaleFilterResponse->json('data')));

        // Filter by category
        $categoryFilterResponse = $this->getJson("/api/products?category_id={$category1->id}");
        $categoryFilterResponse->assertStatus(200);

        // Filter by brand
        $brandFilterResponse = $this->getJson("/api/products?brand_id={$brand1->id}");
        $brandFilterResponse->assertStatus(200);

        // Multiple filters
        $multiFilterResponse = $this->getJson("/api/products?scale=1:64&material=diecast&category_id={$category1->id}");
        $multiFilterResponse->assertStatus(200);

        // Get filter options
        $filtersResponse = $this->getJson('/api/filters');
        $filtersResponse->assertStatus(200);
        $filtersResponse->assertJsonStructure([
            'scales',
            'materials',
            'categories',
            'brands',
        ]);

        // Search with autocomplete
        $autocompleteResponse = $this->getJson('/api/search/autocomplete?q=Hot');
        $autocompleteResponse->assertStatus(200);

        // Get recommendations
        $recommendationsResponse = $this->getJson('/api/recommendations/trending');
        $recommendationsResponse->assertStatus(200);
    }

    /**
     * Test: Admin manages orders and inventory
     * 
     * @test
     */
    public function test_admin_order_and_inventory_management_journey()
    {
        // Setup
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        
        $product = Product::factory()->create([
            'stock_quantity' => 10,
            'current_price' => 500.00,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => 500.00,
        ]);

        // Admin login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('token');

        // View dashboard
        $dashboardResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/dashboard');
        $dashboardResponse->assertStatus(200);

        // View all orders
        $ordersResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/orders');
        $ordersResponse->assertStatus(200);

        // Update order status
        $updateOrderResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);
        $updateOrderResponse->assertStatus(200);

        // View inventory
        $inventoryResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/inventory');
        $inventoryResponse->assertStatus(200);

        // Update stock
        $updateStockResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/admin/inventory/{$product->id}/stock", [
            'quantity' => 20,
            'operation' => 'add',
        ]);
        $updateStockResponse->assertStatus(200);

        // Verify stock updated
        $product->refresh();
        $this->assertEquals(30, $product->stock_quantity); // 10 + 20

        // View analytics
        $analyticsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/analytics/sales-metrics');
        $analyticsResponse->assertStatus(200);
    }
}
