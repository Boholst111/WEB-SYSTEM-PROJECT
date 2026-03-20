<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\RecommendationService;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Wishlist;
use App\Models\ShoppingCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class RecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RecommendationService $recommendationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recommendationService = new RecommendationService();
        Cache::flush();
    }

    /** @test */
    public function it_can_get_similar_products()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product1 = Product::factory()->create([
            'name' => 'Ferrari F40',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'scale' => '1:64',
            'material' => 'diecast',
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Ferrari 458',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'scale' => '1:64',
            'material' => 'diecast',
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $product3 = Product::factory()->create([
            'name' => 'Lamborghini Aventador',
            'brand_id' => Brand::factory()->create(['name' => 'Matchbox']),
            'category_id' => $category->id,
            'scale' => '1:43',
            'material' => 'plastic',
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $similar = $this->recommendationService->getSimilarProducts($product1->id, 10);

        $this->assertNotEmpty($similar);
        $this->assertTrue($similar->contains('id', $product2->id));
        // Product 2 should rank higher due to more matching attributes
    }

    /** @test */
    public function it_can_get_cross_sell_recommendations()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);
        $user = User::factory()->create();

        $product1 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $product2 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        // Create orders with products bought together
        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
        ]);

        $crossSell = $this->recommendationService->getCrossSellRecommendations($product1->id, 6);

        $this->assertNotEmpty($crossSell);
        $this->assertTrue($crossSell->contains('id', $product2->id));
    }

    /** @test */
    public function it_can_get_upsell_recommendations()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product1 = Product::factory()->create([
            'name' => 'Basic Model',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 100.00,
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Premium Model',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 130.00,
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $product3 = Product::factory()->create([
            'name' => 'Too Expensive Model',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 200.00,
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $upsell = $this->recommendationService->getUpsellRecommendations($product1->id, 6);

        $this->assertNotEmpty($upsell);
        $this->assertTrue($upsell->contains('id', $product2->id));
        $this->assertFalse($upsell->contains('id', $product3->id)); // Too expensive
    }

    /** @test */
    public function it_can_get_trending_products()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);
        $user = User::factory()->create();

        $product1 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $product2 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        // Create recent orders
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'created_at' => now()->subDays(3),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 5,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 2,
        ]);

        $trending = $this->recommendationService->getTrendingProducts(10);

        $this->assertNotEmpty($trending);
        // Product 1 should be first due to higher quantity sold
        $this->assertEquals($product1->id, $trending->first()->id);
    }

    /** @test */
    public function it_can_get_new_arrivals()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $oldProduct = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'stock_quantity' => 10,
            'created_at' => now()->subDays(30),
        ]);

        $newProduct = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'stock_quantity' => 10,
            'created_at' => now()->subDays(1),
        ]);

        $newArrivals = $this->recommendationService->getNewArrivals(10);

        $this->assertNotEmpty($newArrivals);
        $this->assertEquals($newProduct->id, $newArrivals->first()->id);
    }

    /** @test */
    public function it_can_get_personalized_recommendations_for_user()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);
        $user = User::factory()->create();

        $product1 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'scale' => '1:64',
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $product2 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'scale' => '1:64',
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        // Add product to wishlist
        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product1->id,
        ]);

        $recommendations = $this->recommendationService->getPersonalizedRecommendations($user->id, 10);

        $this->assertNotEmpty($recommendations);
    }

    /** @test */
    public function it_returns_popular_products_for_guest_users()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $recommendations = $this->recommendationService->getPersonalizedRecommendations(null, 10);

        $this->assertNotEmpty($recommendations);
    }

    /** @test */
    public function it_caches_recommendations()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        // First call - should hit database
        $recommendations1 = $this->recommendationService->getSimilarProducts($product->id, 10);
        
        // Second call - should hit cache
        $recommendations2 = $this->recommendationService->getSimilarProducts($product->id, 10);

        $this->assertEquals($recommendations1, $recommendations2);
    }
}
