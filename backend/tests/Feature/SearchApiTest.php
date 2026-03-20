<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_search_products()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/search', [
            'query' => 'Ferrari',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'query',
                    'products',
                    'pagination',
                ],
            ]);
    }

    /** @test */
    public function it_validates_search_query()
    {
        $response = $this->postJson('/api/search', [
            'query' => 'a', // Too short
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    /** @test */
    public function it_can_get_autocomplete_suggestions()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/search/autocomplete?query=Ferrari');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function it_can_get_search_suggestions()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/search/suggestions?query=Ferrari');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'suggestions',
                    'products',
                ],
            ]);
    }

    /** @test */
    public function it_can_get_popular_searches()
    {
        $response = $this->getJson('/api/search/popular?limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /** @test */
    public function authenticated_user_can_log_search()
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product = Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/search/log', [
                'query' => 'Ferrari',
                'results_count' => 5,
                'clicked_product_id' => $product->id,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('search_logs', [
            'user_id' => $user->id,
            'query' => 'Ferrari',
            'results_count' => 5,
            'clicked_product_id' => $product->id,
        ]);
    }

    /** @test */
    public function it_can_search_with_filters()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'scale' => '1:64',
            'material' => 'diecast',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/search', [
            'query' => 'Ferrari',
            'filters' => [
                'scale' => '1:64',
                'material' => 'diecast',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.filters_applied.scale', '1:64')
            ->assertJsonPath('data.filters_applied.material', 'diecast');
    }

    /** @test */
    public function it_can_search_with_sorting()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 100.00,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Hot Wheels Lamborghini',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 150.00,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/search', [
            'query' => 'Hot Wheels',
            'sort_by' => 'price',
            'sort_order' => 'asc',
        ]);

        $response->assertStatus(200);
        
        $products = $response->json('data.products');
        $this->assertNotEmpty($products);
    }
}
