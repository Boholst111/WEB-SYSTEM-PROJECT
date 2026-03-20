<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandBrowsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create category
        $this->category = Category::factory()->create(['status' => 'active']);

        // Create brands
        $this->hotWheels = Brand::factory()->create([
            'name' => 'Hot Wheels',
            'slug' => 'hot-wheels',
            'status' => 'active',
        ]);

        $this->matchbox = Brand::factory()->create([
            'name' => 'Matchbox',
            'slug' => 'matchbox',
            'status' => 'active',
        ]);

        $this->tomica = Brand::factory()->create([
            'name' => 'Tomica',
            'slug' => 'tomica',
            'status' => 'active',
        ]);

        // Create products
        Product::factory()->count(5)->active()->create([
            'brand_id' => $this->hotWheels->id,
            'category_id' => $this->category->id,
        ]);

        Product::factory()->count(3)->active()->create([
            'brand_id' => $this->matchbox->id,
            'category_id' => $this->category->id,
        ]);

        Product::factory()->count(2)->active()->create([
            'brand_id' => $this->tomica->id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_can_list_brands_with_pagination(): void
    {
        $response = $this->getJson('/api/brands');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'description',
                            'logo_url',
                            'website_url',
                            'country_of_origin',
                            'products_count',
                        ]
                    ],
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_brands_are_sorted_alphabetically_by_default(): void
    {
        $response = $this->getJson('/api/brands');

        $response->assertStatus(200);
        $data = $response->json('data');

        $names = array_column($data, 'name');
        $sortedNames = $names;
        sort($sortedNames);
        
        $this->assertEquals($sortedNames, $names);
    }

    public function test_can_search_brands_by_name(): void
    {
        $response = $this->getJson('/api/brands?search=Hot');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Hot Wheels', $data[0]['name']);
    }

    public function test_can_filter_brands_with_products_only(): void
    {
        // Create brand without products
        Brand::factory()->create([
            'name' => 'Empty Brand',
            'slug' => 'empty-brand',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/brands?only_with_products=1');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should only return brands that have products
        $this->assertCount(3, $data); // Only our 3 brands with products
        foreach ($data as $brand) {
            $this->assertGreaterThan(0, $brand['products_count']);
        }
    }

    public function test_can_include_products_in_brand_listing(): void
    {
        $response = $this->getJson('/api/brands?include_products=1');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'products' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'category',
                                ]
                            ],
                        ]
                    ],
                ]);

        $data = $response->json('data');
        $hotWheelsBrand = collect($data)->firstWhere('slug', 'hot-wheels');
        $this->assertCount(5, $hotWheelsBrand['products']);
    }

    public function test_can_sort_brands_by_products_count(): void
    {
        $response = $this->getJson('/api/brands?sort_by=products_count&sort_order=desc');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Hot Wheels should be first (5 products), then Matchbox (3), then Tomica (2)
        $this->assertEquals('Hot Wheels', $data[0]['name']);
        $this->assertEquals('Matchbox', $data[1]['name']);
        $this->assertEquals('Tomica', $data[2]['name']);
    }

    public function test_can_get_specific_brand_details(): void
    {
        $response = $this->getJson("/api/brands/{$this->hotWheels->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'logo_url',
                        'website_url',
                        'country_of_origin',
                        'products_count',
                        'products' => [
                            'data',
                            'pagination',
                        ],
                    ],
                ]);

        $data = $response->json('data');
        $this->assertEquals($this->hotWheels->id, $data['id']);
        $this->assertEquals('Hot Wheels', $data['name']);
        $this->assertEquals(5, $data['products_count']);
        $this->assertCount(5, $data['products']['data']);
    }

    public function test_can_get_brand_without_products(): void
    {
        $response = $this->getJson("/api/brands/{$this->hotWheels->id}?include_products=0");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayNotHasKey('products', $data);
        $this->assertArrayHasKey('products_count', $data);
    }

    public function test_brand_products_are_paginated(): void
    {
        // Create more products for pagination test
        Product::factory()->count(25)->create([
            'brand_id' => $this->hotWheels->id,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/brands/{$this->hotWheels->id}?per_page=10");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(10, $data['products']['data']);
        $this->assertGreaterThan(1, $data['products']['pagination']['last_page']);
    }

    public function test_inactive_brands_are_excluded(): void
    {
        // Create inactive brand
        Brand::factory()->create([
            'name' => 'Inactive Brand',
            'slug' => 'inactive',
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/brands');

        $response->assertStatus(200);
        $data = $response->json('data');

        $inactiveBrand = collect($data)->firstWhere('slug', 'inactive');
        $this->assertNull($inactiveBrand);
    }

    public function test_products_count_only_includes_active_products(): void
    {
        // Create inactive product
        Product::factory()->create([
            'brand_id' => $this->hotWheels->id,
            'category_id' => $this->category->id,
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/brands');

        $response->assertStatus(200);
        $data = $response->json('data');

        $hotWheelsBrand = collect($data)->firstWhere('slug', 'hot-wheels');
        
        // Should still be 5 (not 6) because inactive product is excluded
        $this->assertEquals(5, $hotWheelsBrand['products_count']);
    }

    public function test_brand_pagination_respects_per_page_limit(): void
    {
        // Create more brands to test pagination
        Brand::factory()->count(50)->create(['status' => 'active']);

        $response = $this->getJson('/api/brands?per_page=10');

        $response->assertStatus(200);
        $data = $response->json('data');
        $pagination = $response->json('pagination');
        
        $this->assertCount(10, $data);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertGreaterThan(1, $pagination['last_page']);
    }

    public function test_brand_pagination_enforces_maximum_per_page(): void
    {
        $response = $this->getJson('/api/brands?per_page=200');

        $response->assertStatus(200);
        $pagination = $response->json('pagination');
        
        $this->assertLessThanOrEqual(100, $pagination['per_page']);
    }
}