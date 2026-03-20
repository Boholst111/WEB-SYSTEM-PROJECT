<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryBrowsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create brand
        $this->brand = Brand::factory()->create(['status' => 'active']);

        // Create parent categories
        $this->vehiclesCategory = Category::factory()->create([
            'name' => 'Vehicles',
            'slug' => 'vehicles',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->collectiblesCategory = Category::factory()->create([
            'name' => 'Collectibles',
            'slug' => 'collectibles',
            'status' => 'active',
            'sort_order' => 2,
        ]);

        // Create child categories
        $this->carsCategory = Category::factory()->create([
            'name' => 'Cars',
            'slug' => 'cars',
            'parent_id' => $this->vehiclesCategory->id,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->trucksCategory = Category::factory()->create([
            'name' => 'Trucks',
            'slug' => 'trucks',
            'parent_id' => $this->vehiclesCategory->id,
            'status' => 'active',
            'sort_order' => 2,
        ]);

        // Create products
        Product::factory()->count(3)->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->carsCategory->id,
            'status' => 'active',
        ]);

        Product::factory()->count(2)->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->trucksCategory->id,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->collectiblesCategory->id,
            'status' => 'active',
        ]);
    }

    public function test_can_list_categories_hierarchically(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'description',
                            'products_count',
                            'children' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'slug',
                                    'products_count',
                                    'children',
                                ]
                            ],
                        ]
                    ],
                ]);

        $data = $response->json('data');
        $this->assertCount(2, $data); // Two root categories

        // Check hierarchical structure
        $vehiclesCategory = collect($data)->firstWhere('slug', 'vehicles');
        $this->assertNotNull($vehiclesCategory);
        $this->assertCount(2, $vehiclesCategory['children']); // Cars and Trucks
    }

    public function test_categories_are_sorted_by_sort_order(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Check root categories are sorted
        $this->assertEquals('vehicles', $data[0]['slug']);
        $this->assertEquals('collectibles', $data[1]['slug']);

        // Check child categories are sorted
        $vehiclesCategory = $data[0];
        $this->assertEquals('cars', $vehiclesCategory['children'][0]['slug']);
        $this->assertEquals('trucks', $vehiclesCategory['children'][1]['slug']);
    }

    public function test_can_filter_categories_with_products_only(): void
    {
        // Create empty category
        Category::factory()->create([
            'name' => 'Empty Category',
            'slug' => 'empty',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/categories?only_with_products=1');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should only return categories that have products
        foreach ($data as $category) {
            $this->assertGreaterThan(0, $category['products_count']);
        }
    }

    public function test_can_include_products_in_category_listing(): void
    {
        $response = $this->getJson('/api/categories?include_products=1');

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
                                    'brand',
                                ]
                            ],
                        ]
                    ],
                ]);
    }

    public function test_can_get_specific_category_details(): void
    {
        $response = $this->getJson("/api/categories/{$this->carsCategory->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'parent',
                        'children',
                        'breadcrumb',
                        'products_count',
                        'products' => [
                            'data',
                            'pagination',
                        ],
                    ],
                ]);

        $data = $response->json('data');
        $this->assertEquals($this->carsCategory->id, $data['id']);
        $this->assertEquals('cars', $data['slug']);
        $this->assertCount(3, $data['products']['data']);
    }

    public function test_category_details_include_breadcrumb(): void
    {
        $response = $this->getJson("/api/categories/{$this->carsCategory->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $breadcrumb = $data['breadcrumb'];
        $this->assertCount(2, $breadcrumb); // Vehicles > Cars
        $this->assertEquals('Vehicles', $breadcrumb[0]['name']);
        $this->assertEquals('Cars', $breadcrumb[1]['name']);
    }

    public function test_can_get_category_without_products(): void
    {
        $response = $this->getJson("/api/categories/{$this->carsCategory->id}?include_products=0");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayNotHasKey('products', $data);
        $this->assertArrayHasKey('products_count', $data);
    }

    public function test_category_products_are_paginated(): void
    {
        // Create more products for pagination test
        Product::factory()->count(25)->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->carsCategory->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/categories/{$this->carsCategory->id}?per_page=10");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(10, $data['products']['data']);
        $this->assertGreaterThan(1, $data['products']['pagination']['last_page']);
    }

    public function test_inactive_categories_are_excluded(): void
    {
        // Create inactive category
        Category::factory()->create([
            'name' => 'Inactive Category',
            'slug' => 'inactive',
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $data = $response->json('data');

        $inactiveCategory = collect($data)->firstWhere('slug', 'inactive');
        $this->assertNull($inactiveCategory);
    }

    public function test_products_count_only_includes_active_products(): void
    {
        // Create inactive product
        Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->carsCategory->id,
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $data = $response->json('data');

        $vehiclesCategory = collect($data)->firstWhere('slug', 'vehicles');
        $carsCategory = collect($vehiclesCategory['children'])->firstWhere('slug', 'cars');
        
        // Should still be 3 (not 4) because inactive product is excluded
        $this->assertEquals(3, $carsCategory['products_count']);
    }
}