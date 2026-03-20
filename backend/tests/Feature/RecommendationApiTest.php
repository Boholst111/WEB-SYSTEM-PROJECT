<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_personalized_recommendations()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/recommendations/personalized?limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function guest_can_get_personalized_recommendations()
    {
        $response = $this->getJson('/api/recommendations/personalized?limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function it_can_get_similar_products()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $response = $this->getJson("/api/recommendations/products/{$product->id}/similar?limit=10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function it_can_get_cross_sell_recommendations()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/recommendations/products/{$product->id}/cross-sell?limit=6");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function it_can_get_upsell_recommendations()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 100.00,
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $response = $this->getJson("/api/recommendations/products/{$product->id}/upsell?limit=6");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function it_can_get_trending_products()
    {
        $response = $this->getJson('/api/recommendations/trending?limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function it_can_get_new_arrivals()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $response = $this->getJson('/api/recommendations/new-arrivals?limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function similar_products_exclude_original_product()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product1 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $product2 = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
            'stock_quantity' => 10,
        ]);

        $response = $this->getJson("/api/recommendations/products/{$product1->id}/similar?limit=10");

        $response->assertStatus(200);
        
        $products = $response->json('data');
        $productIds = collect($products)->pluck('id')->toArray();
        
        $this->assertNotContains($product1->id, $productIds);
    }

    /** @test */
    public function cross_sell_returns_frequently_bought_together()
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

        // Create order with both products
        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
        ]);

        $response = $this->getJson("/api/recommendations/products/{$product1->id}/cross-sell?limit=6");

        $response->assertStatus(200);
        
        $products = $response->json('data');
        $productIds = collect($products)->pluck('id')->toArray();
        
        $this->assertContains($product2->id, $productIds);
    }
}
