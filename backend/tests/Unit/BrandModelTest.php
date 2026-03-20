<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BrandModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'name',
            'slug',
            'description',
            'logo_url',
            'website_url',
            'country_of_origin',
            'status',
        ];

        $brand = new Brand();
        $this->assertEquals($fillable, $brand->getFillable());
    }

    /** @test */
    public function it_has_products_relationship()
    {
        $brand = Brand::factory()->create();
        $product = Product::factory()->create(['brand_id' => $brand->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $brand->products);
        $this->assertTrue($brand->products->contains($product));
    }

    /** @test */
    public function it_scopes_active_brands()
    {
        $activeBrand = Brand::factory()->create(['status' => 'active']);
        $inactiveBrand = Brand::factory()->create(['status' => 'inactive']);

        $activeBrands = Brand::active()->get();

        $this->assertTrue($activeBrands->contains($activeBrand));
        $this->assertFalse($activeBrands->contains($inactiveBrand));
    }

    /** @test */
    public function it_scopes_brands_with_products()
    {
        $brandWithProducts = Brand::factory()->create();
        $brandWithoutProducts = Brand::factory()->create();
        
        Product::factory()->create(['brand_id' => $brandWithProducts->id]);

        $brandsWithProducts = Brand::withProducts()->get();

        $this->assertTrue($brandsWithProducts->contains($brandWithProducts));
        $this->assertFalse($brandsWithProducts->contains($brandWithoutProducts));
    }

    /** @test */
    public function it_gets_products_count()
    {
        $brand = Brand::factory()->create();
        
        Product::factory()->count(3)->create(['brand_id' => $brand->id]);

        $this->assertEquals(3, $brand->getProductsCountAttribute());
    }

    /** @test */
    public function it_gets_active_products_count()
    {
        $brand = Brand::factory()->create();
        
        Product::factory()->count(2)->create([
            'brand_id' => $brand->id,
            'status' => 'active',
        ]);
        
        Product::factory()->create([
            'brand_id' => $brand->id,
            'status' => 'inactive',
        ]);

        $this->assertEquals(2, $brand->getActiveProductsCountAttribute());
    }

    /** @test */
    public function it_returns_zero_for_products_count_when_no_products()
    {
        $brand = Brand::factory()->create();

        $this->assertEquals(0, $brand->getProductsCountAttribute());
        $this->assertEquals(0, $brand->getActiveProductsCountAttribute());
    }
}