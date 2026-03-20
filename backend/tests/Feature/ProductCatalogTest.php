<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
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

        // Create categories
        $this->carsCategory = Category::factory()->create([
            'name' => 'Cars',
            'slug' => 'cars',
            'status' => 'active',
        ]);

        $this->trucksCategory = Category::factory()->create([
            'name' => 'Trucks',
            'slug' => 'trucks',
            'status' => 'active',
        ]);

        // Create products with various attributes
        $this->products = collect([
            Product::factory()->create([
                'name' => 'Hot Wheels Corvette',
                'brand_id' => $this->hotWheels->id,
                'category_id' => $this->carsCategory->id,
                'scale' => '1:64',
                'material' => 'diecast',
                'features' => ['opening_doors', 'detailed_interior'],
                'is_chase_variant' => false,
                'is_preorder' => false,
                'current_price' => 15.99,
                'base_price' => 19.99,
                'stock_quantity' => 10,
                'status' => 'active',
            ]),
            Product::factory()->create([
                'name' => 'Matchbox Fire Truck',
                'brand_id' => $this->matchbox->id,
                'category_id' => $this->trucksCategory->id,
                'scale' => '1:64',
                'material' => 'diecast',
                'features' => ['working_ladder', 'lights'],
                'is_chase_variant' => true,
                'is_preorder' => false,
                'current_price' => 25.99,
                'base_price' => 25.99,
                'stock_quantity' => 5,
                'status' => 'active',
            ]),
            Product::factory()->create([
                'name' => 'Hot Wheels Mustang',
                'brand_id' => $this->hotWheels->id,
                'category_id' => $this->carsCategory->id,
                'scale' => '1:43',
                'material' => 'plastic',
                'features' => ['opening_hood'],
                'is_chase_variant' => false,
                'is_preorder' => true,
                'current_price' => 12.99,
                'base_price' => 12.99,
                'stock_quantity' => 0,
                'status' => 'active',
            ]),
        ]);
    }

    public function test_can_list_products_with_basic_pagination(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'brand',
                            'category',
                            'current_price',
                            'stock_quantity',
                        ]
                    ],
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_products_by_scale(): void
    {
        $response = $this->getJson('/api/products?scale=1:64');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(2, $data);
        foreach ($data as $product) {
            $this->assertEquals('1:64', $product['scale']);
        }
    }

    public function test_can_filter_products_by_material(): void
    {
        $response = $this->getJson('/api/products?material=diecast');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(2, $data);
        foreach ($data as $product) {
            $this->assertEquals('diecast', $product['material']);
        }
    }

    public function test_can_filter_products_by_brand(): void
    {
        $response = $this->getJson('/api/products?brand_id=' . $this->hotWheels->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(2, $data);
        foreach ($data as $product) {
            $this->assertEquals($this->hotWheels->id, $product['brand']['id']);
        }
    }

    public function test_can_filter_products_by_category(): void
    {
        $response = $this->getJson('/api/products?category_id=' . $this->carsCategory->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(2, $data);
        foreach ($data as $product) {
            $this->assertEquals($this->carsCategory->id, $product['category']['id']);
        }
    }

    public function test_can_filter_products_by_features(): void
    {
        $response = $this->getJson('/api/products?features[]=opening_doors');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertContains('opening_doors', $data[0]['features']);
    }

    public function test_can_filter_products_by_price_range(): void
    {
        $response = $this->getJson('/api/products?min_price=15&max_price=20');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $product) {
            $this->assertGreaterThanOrEqual(15, $product['current_price']);
            $this->assertLessThanOrEqual(20, $product['current_price']);
        }
    }

    public function test_can_filter_chase_variants(): void
    {
        $response = $this->getJson('/api/products?is_chase_variant=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_chase_variant']);
    }

    public function test_can_filter_preorder_products(): void
    {
        $response = $this->getJson('/api/products?is_preorder=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_preorder']);
    }

    public function test_can_filter_in_stock_products(): void
    {
        $response = $this->getJson('/api/products?in_stock=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(2, $data);
        foreach ($data as $product) {
            $this->assertGreaterThan(0, $product['stock_quantity']);
        }
    }

    public function test_can_search_products_with_full_text(): void
    {
        $response = $this->postJson('/api/products/search', [
            'query' => 'Corvette',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'query',
                    'data',
                    'pagination',
                ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        $this->assertStringContainsString('Corvette', $data[0]['name']);
    }

    public function test_search_validates_required_query(): void
    {
        $response = $this->postJson('/api/products/search', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['query']);
    }

    public function test_search_validates_query_length(): void
    {
        $response = $this->postJson('/api/products/search', [
            'query' => 'a', // Too short
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['query']);
    }

    public function test_can_combine_search_with_filters(): void
    {
        $response = $this->postJson('/api/products/search', [
            'query' => 'Hot Wheels',
            'filters' => [
                'scale' => '1:64',
                'material' => 'diecast',
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $product) {
            $this->assertEquals('1:64', $product['scale']);
            $this->assertEquals('diecast', $product['material']);
        }
    }

    public function test_can_sort_products_by_price(): void
    {
        $response = $this->getJson('/api/products?sort_by=current_price&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $prices = array_column($data, 'current_price');
        $sortedPrices = $prices;
        sort($sortedPrices);
        
        $this->assertEquals($sortedPrices, $prices);
    }

    public function test_can_sort_products_by_name(): void
    {
        $response = $this->getJson('/api/products?sort_by=name&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $names = array_column($data, 'name');
        $sortedNames = $names;
        sort($sortedNames);
        
        $this->assertEquals($sortedNames, $names);
    }

    public function test_can_get_product_details(): void
    {
        $product = $this->products->first();
        
        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'description',
                        'brand',
                        'category',
                        'scale',
                        'material',
                        'features',
                        'current_price',
                        'base_price',
                        'stock_quantity',
                        'is_available',
                        'is_low_stock',
                        'is_on_sale',
                        'average_rating',
                        'review_count',
                        'formatted_price',
                        'discount_percentage',
                    ],
                ]);

        $data = $response->json('data');
        $this->assertEquals($product->id, $data['id']);
        $this->assertEquals($product->name, $data['name']);
    }

    public function test_product_details_include_computed_attributes(): void
    {
        $product = $this->products->first(); // This product is on sale
        
        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertTrue($data['is_available']);
        $this->assertTrue($data['is_on_sale']);
        $this->assertNotNull($data['discount_percentage']);
        $this->assertStringStartsWith('₱', $data['formatted_price']);
    }

    public function test_pagination_respects_per_page_limit(): void
    {
        // Create more products to test pagination
        Product::factory()->count(50)->create([
            'brand_id' => $this->hotWheels->id,
            'category_id' => $this->carsCategory->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/products?per_page=10');

        $response->assertStatus(200);
        $data = $response->json('data');
        $pagination = $response->json('pagination');
        
        $this->assertCount(10, $data);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertGreaterThan(1, $pagination['last_page']);
    }

    public function test_pagination_enforces_maximum_per_page(): void
    {
        $response = $this->getJson('/api/products?per_page=200');

        $response->assertStatus(200);
        $pagination = $response->json('pagination');
        
        $this->assertLessThanOrEqual(100, $pagination['per_page']);
    }
}