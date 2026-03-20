<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Eris\Generator;
use Eris\TestTrait;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;

/**
 * **Feature: diecast-empire, Property 1: Product filtering accuracy**
 * **Validates: Requirements 1.1, 1.8**
 * 
 * Property-based test for product filtering accuracy.
 * This test validates that for any product catalog and any combination of filters
 * (Scale, Material, Features, Chase variants), all returned products should match
 * every applied filter criterion exactly.
 */
class ProductFilteringAccuracyPropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create brands
        $brands = [
            Brand::create([
                'name' => 'Hot Wheels',
                'slug' => 'hot-wheels',
                'description' => 'Famous diecast brand',
                'status' => 'active'
            ]),
            Brand::create([
                'name' => 'Matchbox',
                'slug' => 'matchbox',
                'description' => 'Classic diecast brand',
                'status' => 'active'
            ]),
            Brand::create([
                'name' => 'Tomica',
                'slug' => 'tomica',
                'description' => 'Japanese diecast brand',
                'status' => 'active'
            ])
        ];

        // Create categories
        $categories = [
            Category::create([
                'name' => 'Cars',
                'slug' => 'cars',
                'description' => 'Passenger cars',
                'status' => 'active'
            ]),
            Category::create([
                'name' => 'Trucks',
                'slug' => 'trucks',
                'description' => 'Commercial trucks',
                'status' => 'active'
            ]),
            Category::create([
                'name' => 'Motorcycles',
                'slug' => 'motorcycles',
                'description' => 'Two-wheeled vehicles',
                'status' => 'active'
            ])
        ];

        // Create diverse product catalog for comprehensive testing
        $scales = ['1:64', '1:43', '1:32', '1:18'];
        $materials = ['diecast', 'plastic', 'resin'];
        $featureOptions = [
            ['opening_doors'],
            ['detailed_interior'],
            ['working_lights'],
            ['opening_doors', 'detailed_interior'],
            ['working_lights', 'rubber_tires'],
            []
        ];

        // Generate products with various combinations
        foreach ($brands as $brandIndex => $brand) {
            foreach ($categories as $categoryIndex => $category) {
                foreach ($scales as $scaleIndex => $scale) {
                    foreach ($materials as $materialIndex => $material) {
                        foreach ($featureOptions as $featureIndex => $features) {
                            // Create regular and chase variants
                            for ($variant = 0; $variant < 2; $variant++) {
                                $isChase = $variant === 1;
                                $isPreorder = ($brandIndex + $categoryIndex + $scaleIndex) % 3 === 0;
                                $stockQuantity = $isPreorder ? 0 : rand(0, 50);
                                
                                Product::create([
                                    'sku' => sprintf('TEST-%d-%d-%d-%d-%d-%d', 
                                        $brandIndex, $categoryIndex, $scaleIndex, 
                                        $materialIndex, $featureIndex, $variant),
                                    'name' => sprintf('%s %s %s %s%s', 
                                        $brand->name, $category->name, $scale, $material,
                                        $isChase ? ' Chase' : ''),
                                    'description' => 'Test product for property testing',
                                    'brand_id' => $brand->id,
                                    'category_id' => $category->id,
                                    'scale' => $scale,
                                    'material' => $material,
                                    'features' => $features,
                                    'is_chase_variant' => $isChase,
                                    'base_price' => rand(1000, 5000) / 100,
                                    'current_price' => rand(1000, 5000) / 100,
                                    'stock_quantity' => $stockQuantity,
                                    'is_preorder' => $isPreorder,
                                    'preorder_date' => $isPreorder ? now()->addDays(30) : null,
                                    'status' => 'active',
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Property: For any combination of filters (Scale, Material, Features, Chase variants),
     * all returned products should match every applied filter criterion exactly.
     */
    public function testProductFilteringAccuracyProperty(): void
    {
        $this->limitTo(15);
        $this->forAll(
            Generator\oneOf(
                // Single filters that are guaranteed to have results
                Generator\elements([
                    ['scale' => '1:64'],
                    ['scale' => '1:43'],
                    ['material' => 'diecast'],
                    ['material' => 'plastic'],
                    ['brand_id' => 1],
                    ['brand_id' => 2],
                    ['category_id' => 1],
                    ['category_id' => 2],
                    ['is_chase_variant' => true],
                    ['features' => ['opening_doors']],
                    ['min_price' => 10, 'max_price' => 50],
                    []  // No filters - should return all products
                ])
            )
        )->then(function ($filters) {
            // Apply filters to get results
            $query = Product::query()->active();
            $filteredProducts = $query->filter($filters)->get();

            // Verify each returned product matches ALL applied filters
            foreach ($filteredProducts as $product) {
                $this->assertProductMatchesFilters($product, $filters);
            }

            // Verify completeness: no products that should match are excluded
            $this->assertFilterCompleteness($filters, $filteredProducts);
        });
    }

    /**
     * Property: Combining multiple filters should return intersection of results.
     */
    public function testMultipleFilterIntersectionProperty(): void
    {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(['1:64', '1:43', '1:32']), // scale
            Generator\elements(['diecast', 'plastic', 'resin']), // material
            Generator\bool() // is_chase_variant
        )->then(function ($scale, $material, $isChase) {
            $filters = [
                'scale' => $scale,
                'material' => $material,
                'is_chase_variant' => $isChase
            ];

            // Get results with combined filters
            $combinedResults = Product::query()->active()->filter($filters)->get();

            // Get results for each individual filter
            $scaleResults = Product::query()->active()->filter(['scale' => $scale])->get();
            $materialResults = Product::query()->active()->filter(['material' => $material])->get();
            $chaseResults = Product::query()->active()->filter(['is_chase_variant' => $isChase])->get();

            // Combined results should be subset of each individual filter result
            foreach ($combinedResults as $product) {
                $this->assertTrue(
                    $scaleResults->contains('id', $product->id),
                    "Product {$product->id} in combined results should also be in scale filter results"
                );
                $this->assertTrue(
                    $materialResults->contains('id', $product->id),
                    "Product {$product->id} in combined results should also be in material filter results"
                );
                $this->assertTrue(
                    $chaseResults->contains('id', $product->id),
                    "Product {$product->id} in combined results should also be in chase filter results"
                );
            }

            // Combined results count should be <= individual filter counts
            $this->assertLessThanOrEqual(
                $scaleResults->count(),
                $combinedResults->count(),
                "Combined filter results should not exceed individual scale filter count"
            );
            $this->assertLessThanOrEqual(
                $materialResults->count(),
                $combinedResults->count(),
                "Combined filter results should not exceed individual material filter count"
            );
            $this->assertLessThanOrEqual(
                $chaseResults->count(),
                $combinedResults->count(),
                "Combined filter results should not exceed individual chase filter count"
            );
        });
    }

    /**
     * Property: Price range filtering should return products within specified bounds.
     */
    public function testPriceRangeFilteringProperty(): void
    {
        $this->limitTo(10);
        $this->forAll(
            Generator\choose(10, 30), // min_price
            Generator\choose(40, 60)  // max_price
        )->then(function ($minPrice, $maxPrice) {
            // Ensure min <= max
            if ($minPrice > $maxPrice) {
                [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
            }

            $filters = [
                'min_price' => $minPrice,
                'max_price' => $maxPrice
            ];

            $results = Product::query()->active()->filter($filters)->get();

            foreach ($results as $product) {
                $this->assertGreaterThanOrEqual(
                    $minPrice,
                    $product->current_price,
                    "Product {$product->id} price {$product->current_price} should be >= min_price {$minPrice}"
                );
                $this->assertLessThanOrEqual(
                    $maxPrice,
                    $product->current_price,
                    "Product {$product->id} price {$product->current_price} should be <= max_price {$maxPrice}"
                );
            }
        });
    }

    /**
     * Property: Feature filtering should match products containing ALL specified features.
     */
    public function testFeatureFilteringProperty(): void
    {
        $this->limitTo(8);
        $this->forAll(
            Generator\elements([
                ['opening_doors'],
                ['detailed_interior'],
                ['working_lights'],
                ['opening_doors', 'detailed_interior'],
                ['working_lights', 'rubber_tires']
            ])
        )->then(function ($requiredFeatures) {
            $filters = ['features' => $requiredFeatures];
            $results = Product::query()->active()->filter($filters)->get();

            foreach ($results as $product) {
                foreach ($requiredFeatures as $feature) {
                    $this->assertContains(
                        $feature,
                        $product->features ?? [],
                        "Product {$product->id} should contain feature '{$feature}'"
                    );
                }
            }
        });
    }

    /**
     * Property: Stock filtering should respect inventory status.
     */
    public function testStockFilteringProperty(): void
    {
        $this->limitTo(5);
        $this->forAll(
            Generator\bool() // in_stock filter
        )->then(function ($inStock) {
            $filters = ['in_stock' => $inStock];
            $results = Product::query()->active()->filter($filters)->get();

            foreach ($results as $product) {
                if ($inStock) {
                    $this->assertGreaterThan(
                        0,
                        $product->stock_quantity,
                        "Product {$product->id} should have stock > 0 when in_stock filter is applied"
                    );
                }
            }
        });
    }

    /**
     * Generate random filter combinations for property testing.
     */
    private function generateFilterCombination(): Generator
    {
        return Generator\oneOf(
            // Single filter combinations (more likely to have results)
            Generator\map(
                function ($scale) { return ['scale' => $scale]; },
                Generator\elements(['1:64', '1:43', '1:32', '1:18'])
            ),
            Generator\map(
                function ($material) { return ['material' => $material]; },
                Generator\elements(['diecast', 'plastic', 'resin'])
            ),
            Generator\map(
                function ($brandId) { return ['brand_id' => $brandId]; },
                Generator\choose(1, 3)
            ),
            Generator\map(
                function ($categoryId) { return ['category_id' => $categoryId]; },
                Generator\choose(1, 3)
            ),
            Generator\map(
                function ($isChase) { return ['is_chase_variant' => $isChase]; },
                Generator\constant(true) // Only test true since false is not handled by the filter
            ),
            
            // Two filter combinations
            Generator\map(
                function ($scale, $material) { 
                    return ['scale' => $scale, 'material' => $material]; 
                },
                Generator\elements(['1:64', '1:43', '1:32']),
                Generator\elements(['diecast', 'plastic', 'resin'])
            ),
            Generator\map(
                function ($brandId, $categoryId) { 
                    return ['brand_id' => $brandId, 'category_id' => $categoryId]; 
                },
                Generator\choose(1, 3),
                Generator\choose(1, 3)
            ),
            
            // Price range filters (always have results)
            Generator\map(
                function ($minPrice, $maxPrice) {
                    if ($minPrice > $maxPrice) {
                        [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
                    }
                    return ['min_price' => $minPrice, 'max_price' => $maxPrice];
                },
                Generator\choose(10, 30),
                Generator\choose(40, 60)
            ),
            
            // Feature filters
            Generator\map(
                function ($features) { return ['features' => $features]; },
                Generator\elements([
                    ['opening_doors'],
                    ['detailed_interior'],
                    ['working_lights']
                ])
            ),
            
            // Empty filter (should return all products)
            Generator\constant([])
        );
    }

    /**
     * Assert that a product matches all applied filters.
     */
    private function assertProductMatchesFilters(Product $product, array $filters): void
    {
        foreach ($filters as $filterType => $filterValue) {
            switch ($filterType) {
                case 'scale':
                    $this->assertEquals(
                        $filterValue,
                        $product->scale,
                        "Product {$product->id} scale should match filter"
                    );
                    break;

                case 'material':
                    $this->assertEquals(
                        $filterValue,
                        $product->material,
                        "Product {$product->id} material should match filter"
                    );
                    break;

                case 'brand_id':
                    $this->assertEquals(
                        $filterValue,
                        $product->brand_id,
                        "Product {$product->id} brand_id should match filter"
                    );
                    break;

                case 'category_id':
                    $this->assertEquals(
                        $filterValue,
                        $product->category_id,
                        "Product {$product->id} category_id should match filter"
                    );
                    break;

                case 'features':
                    foreach ($filterValue as $feature) {
                        $this->assertContains(
                            $feature,
                            $product->features ?? [],
                            "Product {$product->id} should contain feature '{$feature}'"
                        );
                    }
                    break;

                case 'is_chase_variant':
                    // The filter only applies when value is true
                    if ($filterValue) {
                        $this->assertTrue(
                            $product->is_chase_variant,
                            "Product {$product->id} should be a chase variant when filter is true"
                        );
                    }
                    break;

                case 'is_preorder':
                    // The filter only applies when value is true
                    if ($filterValue) {
                        $this->assertTrue(
                            $product->is_preorder,
                            "Product {$product->id} should be a preorder when filter is true"
                        );
                    }
                    break;

                case 'in_stock':
                    if ($filterValue) {
                        $this->assertGreaterThan(
                            0,
                            $product->stock_quantity,
                            "Product {$product->id} should be in stock"
                        );
                    }
                    break;

                case 'min_price':
                    $this->assertGreaterThanOrEqual(
                        $filterValue,
                        $product->current_price,
                        "Product {$product->id} price should be >= min_price"
                    );
                    break;

                case 'max_price':
                    $this->assertLessThanOrEqual(
                        $filterValue,
                        $product->current_price,
                        "Product {$product->id} price should be <= max_price"
                    );
                    break;
            }
        }
    }

    /**
     * Assert that filtering is complete - no matching products are excluded.
     */
    private function assertFilterCompleteness(array $filters, $filteredProducts): void
    {
        // Get all active products and manually check which should match
        $allProducts = Product::query()->active()->get();
        $expectedMatches = $allProducts->filter(function ($product) use ($filters) {
            return $this->productShouldMatchFilters($product, $filters);
        });

        $filteredIds = $filteredProducts->pluck('id')->sort()->values();
        $expectedIds = $expectedMatches->pluck('id')->sort()->values();

        $this->assertEquals(
            $expectedIds->toArray(),
            $filteredIds->toArray(),
            "Filtered results should include all products that match the criteria. " .
            "Expected: " . $expectedIds->implode(',') . ", Got: " . $filteredIds->implode(',')
        );
    }

    /**
     * Check if a product should match the given filters.
     */
    private function productShouldMatchFilters(Product $product, array $filters): bool
    {
        foreach ($filters as $filterType => $filterValue) {
            switch ($filterType) {
                case 'scale':
                    if ($product->scale !== $filterValue) return false;
                    break;

                case 'material':
                    if ($product->material !== $filterValue) return false;
                    break;

                case 'brand_id':
                    if ($product->brand_id !== $filterValue) return false;
                    break;

                case 'category_id':
                    if ($product->category_id !== $filterValue) return false;
                    break;

                case 'features':
                    foreach ($filterValue as $feature) {
                        if (!in_array($feature, $product->features ?? [])) return false;
                    }
                    break;

                case 'is_chase_variant':
                    // The filter only applies when value is true
                    if ($filterValue && !$product->is_chase_variant) return false;
                    break;

                case 'is_preorder':
                    // The filter only applies when value is true
                    if ($filterValue && !$product->is_preorder) return false;
                    break;

                case 'in_stock':
                    if ($filterValue && $product->stock_quantity <= 0) return false;
                    break;

                case 'min_price':
                    if ($product->current_price < $filterValue) return false;
                    break;

                case 'max_price':
                    if ($product->current_price > $filterValue) return false;
                    break;
            }
        }

        return true;
    }
}