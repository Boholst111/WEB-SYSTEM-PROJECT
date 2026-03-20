<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\SearchService;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\SearchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchService = new SearchService();
        Cache::flush();
    }

    /** @test */
    public function it_can_search_products_by_name()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product1 = Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Hot Wheels Lamborghini',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $results = $this->searchService->search('Ferrari');

        $this->assertNotEmpty($results['products']);
        $this->assertEquals(1, count($results['products']));
        $this->assertEquals($product1->id, $results['products'][0]->id);
    }

    /** @test */
    public function it_can_search_products_with_filters()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        $product1 = Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'scale' => '1:64',
            'material' => 'diecast',
            'status' => 'active',
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Hot Wheels Lamborghini',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'scale' => '1:43',
            'material' => 'diecast',
            'status' => 'active',
        ]);

        $results = $this->searchService->search('Hot Wheels', ['scale' => '1:64']);

        $this->assertNotEmpty($results['products']);
        $this->assertEquals(1, count($results['products']));
        $this->assertEquals($product1->id, $results['products'][0]->id);
    }

    /** @test */
    public function it_can_get_autocomplete_suggestions()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari F40',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari 458',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $suggestions = $this->searchService->getAutocompleteSuggestions('Ferrari');

        $this->assertNotEmpty($suggestions);
        $this->assertContains('Hot Wheels Ferrari F40', $suggestions);
        $this->assertContains('Hot Wheels Ferrari 458', $suggestions);
    }

    /** @test */
    public function it_can_get_search_suggestions_with_products()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $suggestions = $this->searchService->getSuggestions('Ferrari');

        $this->assertArrayHasKey('suggestions', $suggestions);
        $this->assertArrayHasKey('products', $suggestions);
        $this->assertNotEmpty($suggestions['products']);
    }

    /** @test */
    public function it_can_log_search_queries()
    {
        $this->searchService->logSearch('Ferrari', 5, null, null);

        $this->assertDatabaseHas('search_logs', [
            'query' => 'Ferrari',
            'results_count' => 5,
        ]);
    }

    /** @test */
    public function it_can_get_popular_searches()
    {
        SearchLog::create([
            'query' => 'Ferrari',
            'results_count' => 10,
            'searched_at' => now(),
        ]);

        SearchLog::create([
            'query' => 'Ferrari',
            'results_count' => 8,
            'searched_at' => now(),
        ]);

        SearchLog::create([
            'query' => 'Lamborghini',
            'results_count' => 5,
            'searched_at' => now(),
        ]);

        $popularSearches = $this->searchService->getPopularSearches(10);

        $this->assertArrayHasKey('Ferrari', $popularSearches);
        $this->assertEquals(2, $popularSearches['Ferrari']);
    }

    /** @test */
    public function it_caches_search_results()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        // First search - should hit database
        $results1 = $this->searchService->search('Ferrari');
        
        // Second search - should hit cache
        $results2 = $this->searchService->search('Ferrari');

        $this->assertEquals($results1, $results2);
    }

    /** @test */
    public function it_only_returns_active_products()
    {
        $brand = Brand::factory()->create(['name' => 'Hot Wheels']);
        $category = Category::factory()->create(['name' => 'Cars']);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari Active',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Hot Wheels Ferrari Inactive',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'inactive',
        ]);

        $results = $this->searchService->search('Ferrari');

        $this->assertEquals(1, count($results['products']));
        $this->assertStringContainsString('Active', $results['products'][0]->name);
    }
}
