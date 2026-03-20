<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Order;
use App\Models\PreOrder;
use App\Models\ShoppingCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * System Integration Test
 * 
 * Comprehensive end-to-end integration tests validating all system components
 * working together including frontend-backend connectivity, database operations,
 * caching, payment processing, and cross-system workflows.
 * 
 * **Feature: diecast-empire, Integration Testing**
 */
class SystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected Product $product;
    protected Category $category;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->category = Category::factory()->create();
        $this->brand = Brand::factory()->create();
        
        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'stock_quantity' => 100,
            'current_price' => 1000.00,
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'loyalty_credits' => 500.00,
            'loyalty_tier' => 'silver',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    /**
     * Test complete user journey from registration to order completion
     * 
     * @test
     */
    public function test_complete_user_journey_from_registration_to_order()
    {
        // Step 1: User Registration
        $registrationResponse = $this->postJson('/api/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $registrationResponse->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);

        // Step 2: User Login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');

        // Step 3: Browse Products
        $productsResponse = $this->withToken($token)
            ->getJson('/api/products');

        $productsResponse->assertStatus(200);
        $productsResponse->assertJsonStructure(['data', 'meta']);

        // Step 4: Add to Cart
        $cartResponse = $this->withToken($token)
            ->postJson('/api/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $cartResponse->assertStatus(201);

        // Step 5: View Cart
        $viewCartResponse = $this->withToken($token)
            ->getJson('/api/cart');

        $viewCartResponse->assertStatus(200);
        $viewCartResponse->assertJsonPath('items.0.product_id', $this->product->id);

        // Step 6: Initialize Checkout
        $checkoutResponse = $this->withToken($token)
            ->postJson('/api/checkout/initialize');

        $checkoutResponse->assertStatus(200);

        // Step 7: Create Order
        $orderResponse = $this->withToken($token)
            ->postJson('/api/checkout/orders', [
                'shipping_address' => [
                    'street' => '123 Main St',
                    'city' => 'Manila',
                    'province' => 'Metro Manila',
                    'postal_code' => '1000',
                    'country' => 'Philippines',
                ],
                'payment_method' => 'gcash',
            ]);

        $orderResponse->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'user_id' => User::where('email', 'newuser@example.com')->first()->id,
        ]);
    }

    /**
     * Test product catalog with filtering and search integration
     * 
     * @test
     */
    public function test_product_catalog_filtering_and_search_integration()
    {
        // Create diverse products
        Product::factory()->count(10)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'scale' => '1:64',
            'material' => 'diecast',
        ]);

        Product::factory()->count(5)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'scale' => '1:43',
            'material' => 'resin',
        ]);

        // Test filtering
        $filterResponse = $this->getJson('/api/products?scale=1:64&material=diecast');
        $filterResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(10, count($filterResponse->json('data')));

        // Test search
        $searchResponse = $this->postJson('/api/search', [
            'query' => $this->product->name,
        ]);
        $searchResponse->assertStatus(200);
        $searchResponse->assertJsonFragment(['name' => $this->product->name]);

        // Test autocomplete
        $autocompleteResponse = $this->getJson('/api/search/autocomplete?q=' . substr($this->product->name, 0, 3));
        $autocompleteResponse->assertStatus(200);
    }

    /**
     * Test pre-order workflow integration
     * 
     * @test
     */
    public function test_preorder_workflow_integration()
    {
        $preorderProduct = Product::factory()->create([
            'is_preorder' => true,
            'preorder_date' => now()->addDays(30),
            'current_price' => 2000.00,
        ]);

        $token = $this->actingAs($this->user)->json('POST', '/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->json('token');

        // Create pre-order
        $preorderResponse = $this->withToken($token)
            ->postJson('/api/preorders', [
                'product_id' => $preorderProduct->id,
                'quantity' => 1,
            ]);

        $preorderResponse->assertStatus(201);
        $preorderId = $preorderResponse->json('data.id');

        // Pay deposit
        $depositResponse = $this->withToken($token)
            ->postJson("/api/preorders/{$preorderId}/deposit", [
                'payment_method' => 'gcash',
            ]);

        $depositResponse->assertStatus(200);

        // Check status
        $statusResponse = $this->withToken($token)
            ->getJson("/api/preorders/{$preorderId}/status");

        $statusResponse->assertStatus(200);
        $statusResponse->assertJsonPath('data.status', 'deposit_paid');
    }

    /**
     * Test loyalty system integration across purchases
     * 
     * @test
     */
    public function test_loyalty_system_integration()
    {
        $token = $this->actingAs($this->user)->json('POST', '/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->json('token');

        // Check initial balance
        $balanceResponse = $this->withToken($token)
            ->getJson('/api/loyalty/balance');

        $balanceResponse->assertStatus(200);
        $initialBalance = $balanceResponse->json('balance');

        // Create order to earn credits
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'total_amount' => 1000.00,
            'payment_status' => 'paid',
        ]);

        // Earn credits
        $earnResponse = $this->withToken($token)
            ->postJson('/api/loyalty/earn-credits', [
                'order_id' => $order->id,
            ]);

        $earnResponse->assertStatus(200);

        // Check updated balance
        $updatedBalanceResponse = $this->withToken($token)
            ->getJson('/api/loyalty/balance');

        $updatedBalanceResponse->assertStatus(200);
        $this->assertGreaterThan($initialBalance, $updatedBalanceResponse->json('balance'));

        // Test redemption
        $redeemResponse = $this->withToken($token)
            ->postJson('/api/loyalty/redeem', [
                'amount' => 50.00,
            ]);

        $redeemResponse->assertStatus(200);
    }

    /**
     * Test payment gateway integration
     * 
     * @test
     */
    public function test_payment_gateway_integration()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'total_amount' => 1500.00,
            'payment_status' => 'pending',
        ]);

        $token = $this->actingAs($this->user)->json('POST', '/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->json('token');

        // Test GCash payment
        $gcashResponse = $this->withToken($token)
            ->postJson('/api/payments/gcash', [
                'order_id' => $order->id,
                'amount' => 1500.00,
            ]);

        $gcashResponse->assertStatus(200);
        $gcashResponse->assertJsonStructure(['payment_id', 'redirect_url']);

        // Test payment status check
        $paymentId = $gcashResponse->json('payment_id');
        $statusResponse = $this->withToken($token)
            ->getJson("/api/payments/{$paymentId}/status");

        $statusResponse->assertStatus(200);
    }

    /**
     * Test admin dashboard integration
     * 
     * @test
     */
    public function test_admin_dashboard_integration()
    {
        $token = $this->actingAs($this->admin)->json('POST', '/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ])->json('token');

        // Test dashboard access
        $dashboardResponse = $this->withToken($token)
            ->getJson('/api/admin/dashboard');

        $dashboardResponse->assertStatus(200);

        // Test analytics
        $analyticsResponse = $this->withToken($token)
            ->getJson('/api/admin/analytics/sales-metrics');

        $analyticsResponse->assertStatus(200);

        // Test order management
        $ordersResponse = $this->withToken($token)
            ->getJson('/api/admin/orders');

        $ordersResponse->assertStatus(200);

        // Test inventory management
        $inventoryResponse = $this->withToken($token)
            ->getJson('/api/admin/inventory');

        $inventoryResponse->assertStatus(200);
    }

    /**
     * Test caching system integration
     * 
     * @test
     */
    public function test_caching_system_integration()
    {
        // Test product caching
        $cacheKey = 'product:' . $this->product->id;
        
        // First request should cache
        $response1 = $this->getJson("/api/products/{$this->product->id}");
        $response1->assertStatus(200);

        // Check if cached
        $this->assertTrue(Cache::has($cacheKey));

        // Second request should use cache
        $response2 = $this->getJson("/api/products/{$this->product->id}");
        $response2->assertStatus(200);

        // Test cache invalidation
        $this->product->update(['name' => 'Updated Product Name']);
        $this->assertFalse(Cache::has($cacheKey));
    }

    /**
     * Test database read replica integration
     * 
     * @test
     */
    public function test_database_read_replica_integration()
    {
        // Read operations should use read connection
        $products = Product::all();
        $this->assertNotEmpty($products);

        // Write operations should use write connection
        $newProduct = Product::factory()->create();
        $this->assertDatabaseHas('products', ['id' => $newProduct->id]);

        // Verify read after write
        $readProduct = Product::find($newProduct->id);
        $this->assertEquals($newProduct->id, $readProduct->id);
    }

    /**
     * Test notification system integration
     * 
     * @test
     */
    public function test_notification_system_integration()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'confirmed',
        ]);

        // Verify notification preferences
        $token = $this->actingAs($this->user)->json('POST', '/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->json('token');

        $preferencesResponse = $this->withToken($token)
            ->getJson('/api/notifications/preferences');

        $preferencesResponse->assertStatus(200);

        // Update preferences
        $updateResponse = $this->withToken($token)
            ->putJson('/api/notifications/preferences', [
                'email_notifications' => true,
                'sms_notifications' => false,
            ]);

        $updateResponse->assertStatus(200);
    }

    /**
     * Test security headers and CORS integration
     * 
     * @test
     */
    public function test_security_headers_integration()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        
        // Verify security headers are present
        $response->assertHeader('X-Frame-Options');
        $response->assertHeader('X-Content-Type-Options');
        $response->assertHeader('X-XSS-Protection');
    }

    /**
     * Test API rate limiting integration
     * 
     * @test
     */
    public function test_rate_limiting_integration()
    {
        $token = $this->actingAs($this->user)->json('POST', '/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->json('token');

        // Make multiple requests to test rate limiting
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->withToken($token)
                ->getJson('/api/products');
        }

        // All requests within limit should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }

    /**
     * Test health check endpoint integration
     * 
     * @test
     */
    public function test_health_check_integration()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'version',
            'environment',
        ]);
        $response->assertJsonPath('status', 'ok');
    }

    /**
     * Helper method to authenticate with token
     */
    public function withToken(string $token, string $type = 'Bearer')
    {
        return $this->withHeaders([
            'Authorization' => $type . ' ' . $token,
            'Accept' => 'application/json',
        ]);
    }
}
