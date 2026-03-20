<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'name',
            'slug',
            'description',
            'parent_id',
            'image_url',
            'sort_order',
            'status',
        ];

        $category = new Category();
        $this->assertEquals($fillable, $category->getFillable());
    }

    /** @test */
    public function it_has_products_relationship()
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $category->products);
        $this->assertTrue($category->products->contains($product));
    }

    /** @test */
    public function it_has_parent_relationship()
    {
        $parentCategory = Category::factory()->create();
        $childCategory = Category::factory()->create(['parent_id' => $parentCategory->id]);

        $this->assertInstanceOf(Category::class, $childCategory->parent);
        $this->assertEquals($parentCategory->id, $childCategory->parent->id);
    }

    /** @test */
    public function it_has_children_relationship()
    {
        $parentCategory = Category::factory()->create();
        $childCategory = Category::factory()->create(['parent_id' => $parentCategory->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $parentCategory->children);
        $this->assertTrue($parentCategory->children->contains($childCategory));
    }

    /** @test */
    public function it_scopes_active_categories()
    {
        $activeCategory = Category::factory()->create(['status' => 'active']);
        $inactiveCategory = Category::factory()->create(['status' => 'inactive']);

        $activeCategories = Category::active()->get();

        $this->assertTrue($activeCategories->contains($activeCategory));
        $this->assertFalse($activeCategories->contains($inactiveCategory));
    }

    /** @test */
    public function it_scopes_root_categories()
    {
        $rootCategory = Category::factory()->create(['parent_id' => null]);
        $childCategory = Category::factory()->create(['parent_id' => $rootCategory->id]);

        $rootCategories = Category::root()->get();

        $this->assertTrue($rootCategories->contains($rootCategory));
        $this->assertFalse($rootCategories->contains($childCategory));
    }

    /** @test */
    public function it_scopes_categories_with_products()
    {
        $categoryWithProducts = Category::factory()->create();
        $categoryWithoutProducts = Category::factory()->create();
        
        Product::factory()->create(['category_id' => $categoryWithProducts->id]);

        $categoriesWithProducts = Category::withProducts()->get();

        $this->assertTrue($categoriesWithProducts->contains($categoryWithProducts));
        $this->assertFalse($categoriesWithProducts->contains($categoryWithoutProducts));
    }

    /** @test */
    public function it_gets_products_count()
    {
        $category = Category::factory()->create();
        
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $this->assertEquals(3, $category->getProductsCountAttribute());
    }

    /** @test */
    public function it_gets_active_products_count()
    {
        $category = Category::factory()->create();
        
        Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'status' => 'active',
        ]);
        
        Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'inactive',
        ]);

        $this->assertEquals(2, $category->getActiveProductsCountAttribute());
    }

    /** @test */
    public function it_checks_if_category_has_children()
    {
        $parentCategory = Category::factory()->create();
        $childlessCategory = Category::factory()->create();
        
        Category::factory()->create(['parent_id' => $parentCategory->id]);

        $this->assertTrue($parentCategory->hasChildren());
        $this->assertFalse($childlessCategory->hasChildren());
    }

    /** @test */
    public function it_gets_all_descendant_categories()
    {
        $grandparent = Category::factory()->create();
        $parent = Category::factory()->create(['parent_id' => $grandparent->id]);
        $child1 = Category::factory()->create(['parent_id' => $parent->id]);
        $child2 = Category::factory()->create(['parent_id' => $parent->id]);
        $grandchild = Category::factory()->create(['parent_id' => $child1->id]);

        // Load the relationships to avoid lazy loading violations
        $grandparent->load('children.children.children');

        $descendants = $grandparent->descendants();
        $descendantIds = collect($descendants)->pluck('id')->toArray();

        $this->assertCount(4, $descendants);
        $this->assertContains($parent->id, $descendantIds);
        $this->assertContains($child1->id, $descendantIds);
        $this->assertContains($child2->id, $descendantIds);
        $this->assertContains($grandchild->id, $descendantIds);
    }

    /** @test */
    public function it_returns_empty_array_for_categories_without_descendants()
    {
        $category = Category::factory()->create();

        $descendants = $category->descendants();

        $this->assertIsArray($descendants);
        $this->assertEmpty($descendants);
    }

    /** @test */
    public function it_gets_breadcrumb_path()
    {
        $grandparent = Category::factory()->create([
            'name' => 'Vehicles',
            'slug' => 'vehicles',
        ]);
        
        $parent = Category::factory()->create([
            'name' => 'Cars',
            'slug' => 'cars',
            'parent_id' => $grandparent->id,
        ]);
        
        $child = Category::factory()->create([
            'name' => 'Sports Cars',
            'slug' => 'sports-cars',
            'parent_id' => $parent->id,
        ]);

        $breadcrumb = $child->getBreadcrumbAttribute();

        $this->assertCount(3, $breadcrumb);
        
        $this->assertEquals([
            'id' => $grandparent->id,
            'name' => 'Vehicles',
            'slug' => 'vehicles',
        ], $breadcrumb[0]);
        
        $this->assertEquals([
            'id' => $parent->id,
            'name' => 'Cars',
            'slug' => 'cars',
        ], $breadcrumb[1]);
        
        $this->assertEquals([
            'id' => $child->id,
            'name' => 'Sports Cars',
            'slug' => 'sports-cars',
        ], $breadcrumb[2]);
    }

    /** @test */
    public function it_gets_breadcrumb_for_root_category()
    {
        $rootCategory = Category::factory()->create([
            'name' => 'Root Category',
            'slug' => 'root-category',
        ]);

        $breadcrumb = $rootCategory->getBreadcrumbAttribute();

        $this->assertCount(1, $breadcrumb);
        $this->assertEquals([
            'id' => $rootCategory->id,
            'name' => 'Root Category',
            'slug' => 'root-category',
        ], $breadcrumb[0]);
    }

    /** @test */
    public function it_returns_zero_for_products_count_when_no_products()
    {
        $category = Category::factory()->create();

        $this->assertEquals(0, $category->getProductsCountAttribute());
        $this->assertEquals(0, $category->getActiveProductsCountAttribute());
    }
}