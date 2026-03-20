<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
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

        // Create products with diverse attributes
        Product::factory()->create([
            'name' => 'Hot Wheels Corvette',
            'brand_id' => $this->hotWheels->id,
            'category_id' => $this->carsCategory->id,
            'scale' => '1:64',
            'material' => 'diecast',
            'features' => ['opening_doors', 'detailed_interior'],
            'current_price' => 15.99,
            'base_price' => 19.99,
            'stock_quantity' => 10,
            'is_chase_variant' => false,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Matchbox Fire Truck',
            'brand_id' => $this->matchbox->id,
            'category_id' => $this->trucksCategory->id,
            'scale' => '1:64',
            'material' => 'diecast',
            'features' => ['working_ladder', 'lights'],
            'current_price' => 25.99,
            'base_price' => 25.99,
            'stock_quantity' => 5,
            'is_chase_variant' => true,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Hot Wheels Mustang',
            'brand_id' => $this->hotWheels->id,
            'category_id' => $this->carsCategory->id,
            'scale' => '1:43',
            'material' => 'plastic',
            'features' => ['opening_hood'],
            'current_price' => 12.99,
            'base_price' => 12.99,
            'stock_quantity' => 0,
            'is_preorder' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Matchbox Ambulance',
            'brand_id' => $this->matchbox->id,
            'category_id' => $this->trucksCategory->id,
            'scale' => '1:43',
            'material' => 'plastic',
            'features' => ['opening_doors', 'working_ladder'],
            'current_price' => 18.99,
            'base_price' => 18.99,
            'stock_quantity' => 8,
            'is_chase_variant' => false,
            'is_preorder' => false,
            'status' => 'active',
        ]);
    }

    public function test_can_get_all_filter_options(): void
    {
        $response = $this->getJson('/api/filters');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'scales' => [
                            '*' => [
                                'value',
                                'label',
                                'count',
                            ]
                        ],
                        'materials' => [
                            '*' => [
                                'value',
                                'label',
                                'count',
                            ]
                        ],
                        'features' => [
                            '*' => [
                                'value',
                                'label',
                                'count',
                            ]
                        ],
                        'price_range' => [
                            'min',
                            'max',
                        ],
                        'brands' => [
                            '*' => [
                                'id',
                                'name',
                                'slug',
                                'count',
                            ]
                        ],
                        'categories' => [
                            '*' => [
                                'id',
                                'name',
                                'slug',
                                'parent_id',
                                'count',
                            ]
                        ],
                        'availability' => [
                            '*' => [
                                'value',
                                'label',
                                'count',
                            ]
                        ],
                    ],
                ]);

        $this->assertTrue($response->json('success'));
    }

    public function test_scales_filter_options_are_correct(): void
    {
        $response = $this->getJson('/api/filters');

        $response->assertStatus(200);
        $scales = $response->json('data.scales');

        $this->assertCount(2, $scales);
        
        $scale164 = collect($scales)->firstWhere('value', '1:64');
        $scale143 = collect($scales)->firstWhere('value', '1:43');
        
        $this->assertNotNull($scale164);
        $this->assertNotNull($scale143);
        $this->assertEquals(2, $scale164['count']); // 2 products with 1:64 scale
        $this->assertEquals(2, $scale143['count']); // 2 products with 1:43 scale
    }

    public function test_materials_filter_options_are_correct(): void
    {
        $response = $this->getJson('/api/filters');

        $response->assertStatus(200);
        $materials = $response->json('data.materials');

        $this->assertCount(2, $materials);
        
        $diecast = collect($materials)->firstWhere('value', 'diecast');
        $plastic = collect($materials)->firstWhere('value', 'plastic');
        
        $this->assertNotNull($diecast);
        $this->assertNotNull($plastic);
        $this->assertEquals('Diecast', $diecast['label']);
        $this->assertEquals('Plastic', $plastic['label']);
        $this->assertEquals(2, $diecast['count']);
        $this->assertEquals(2, $plastic['count']);
    }

    public function test_features_filter_options_are_correct(): void
    {
        $response = $this->getJson('/api/filters');

        $response->assertStatus(200);
        $features = $response->json('data.features');

        $expectedFeatures = [
            'opening_doors' => 'Opening Doors',
            'detailed_interior' => 'Detailed Interior',
            'working_ladder' => 'Working Ladder',
            'lights' => 'Lights',
            'opening_hood' => 'Opening Hood',
        ];

        $this->assertCount(5, $features);

        foreach ($features as $feature) {
            $this->assertArrayHasKey($feature['value'], $expectedFeatures);
            $this->assertEquals($expectedFeatures[$feature['value']], $feature['label']);
            $this->assertGreaterThan(0, $feature['count']);
        }
    }

    public function test_price_range_is_correct(): void
    {
        $response = $this->getJson('/api/filters');

        $response->assertStatus(200);
        $priceRange = $response->json('data.price_range');

        $this->assertEquals(12.99, $priceRange['min']);
        $this->assertEquals(25.99, $priceRange['max']);
    }

    public function test_brands_filter_options_are_correct(): void
    {
        $response = $this->getJson('/api/filters');

        $response->assertStatus(200);
        $brands = $response->json('data.brands');

        $this->assertCount(2, $brands);
        
        $hotWheels = collect($brands)->firstWhere('slug', 'hot-wheels');
        $matchbox = collect($brands)->firstWhere('slug', 'matchbox');
        
        $this->assertNotNull($hotWheels);
        $this->assertNotNull($matchbox);
        $this->assertEquals(2, $hotWheels['count']);
        $this->assertEquals(2, $matchbox['count']);
    }

    public function test_categories_filter_options_are_correct(): void
    {
        $response = $this->getJson('/api/filters');

        $response->assertStatus(200);
        $categories = $response->json('data.categories');

        $this->assertCount(2, $categories);
        
        $cars = collect($categories)->firstWhere('slug', 'cars');
        $trucks = collect($categories)->firstWhere('slug', 'trucks');
        
        $this->assertNotNull($cars);
        $this->assertNotNull($trucks);
        $this->assertEquals(2, $cars['count']);
        $this->assertEquals(2, $trucks['count']);
    }

    public function test_availability_options_are_correct(): void
    {
        $response = $this->getJson('/api/filters');

        $response->assertStatus(200);
        $availability = $response->json('data.availability');

        $this->assertCount(4, $availability);
        
        $inStock = collect($availability)->firstWhere('value', 'in_stock');
        $preorder = collect($availability)->firstWhere('value', 'preorder');
        $chaseVariant = collect($availability)->firstWhere('value', 'chase_variant');
        $onSale = collect($availability)->firstWhere('value', 'on_sale');
        
        $this->assertNotNull($inStock);
        $this->assertNotNull($preorder);
        $this->assertNotNull($chaseVariant);
        $this->assertNotNull($onSale);
        
        $this->assertEquals(3, $inStock['count']); // 3 products in stock
        $this->assertEquals(1, $preorder['count']); // 1 preorder product
        $this->assertEquals(1, $chaseVariant['count']); // 1 chase variant
        $this->assertEquals(1, $onSale['count']); // 1 product on sale
    }

    public function test_can_filter_options_by_category(): void
    {
        $response = $this->getJson("/api/filters?category_id={$this->carsCategory->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should only show options for products in Cars category
        $brands = $data['brands'];
        $this->assertCount(1, $brands); // Only Hot Wheels has cars
        $this->assertEquals('hot-wheels', $brands[0]['slug']);

        // Categories should be empty since we're filtering by category
        $this->assertEmpty($data['categories']);
    }

    public function test_can_filter_options_by_brand(): void
    {
        $response = $this->getJson("/api/filters?brand_id={$this->hotWheels->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should only show options for Hot Wheels products
        $categories = $data['categories'];
        $this->assertCount(1, $categories); // Only Cars category has Hot Wheels products
        $this->assertEquals('cars', $categories[0]['slug']);

        // Brands should be empty since we're filtering by brand
        $this->assertEmpty($data['brands']);
    }

    public function test_filter_options_exclude_inactive_products(): void
    {
        // Create inactive product with unique attributes
        Product::factory()->create([
            'brand_id' => $this->hotWheels->id,
            'category_id' => $this->carsCategory->id,
            'scale' => '1:18', // Unique scale
            'material' => 'metal', // Unique material
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/filters');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should not include options from inactive products
        $scales = collect($data['scales'])->pluck('value');
        $materials = collect($data['materials'])->pluck('value');
        
        $this->assertNotContains('1:18', $scales);
        $this->assertNotContains('metal', $materials);
    }

    public function test_filter_options_context_is_returned(): void
    {
        $categoryId = $this->carsCategory->id;
        $brandId = $this->hotWheels->id;
        
        $response = $this->getJson("/api/filters?category_id={$categoryId}&brand_id={$brandId}");

        $response->assertStatus(200);
        $context = $response->json('context');

        $this->assertEquals($categoryId, $context['category_id']);
        $this->assertEquals($brandId, $context['brand_id']);
    }
}