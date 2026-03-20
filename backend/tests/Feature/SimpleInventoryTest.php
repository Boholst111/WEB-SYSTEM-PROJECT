<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleInventoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function inventory_management_interface_is_implemented()
    {
        // Create test data
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'stock_quantity' => 10,
            'is_chase_variant' => false,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        // Create admin user
        $admin = User::factory()->create([
            'email' => 'admin@diecastempire.com',
            'preferences' => ['role' => 'admin']
        ]);

        // Test basic inventory functionality
        $this->assertTrue($product->isAvailable());
        $this->assertFalse($product->isLowStock(5));
        $this->assertTrue($product->isLowStock(15));

        // Test stock update
        $result = $product->updateStock(3, 'sale');
        $this->assertTrue($result);
        $this->assertEquals(7, $product->stock_quantity);

        // Test chase variant identification
        $chaseVariant = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_chase_variant' => true,
            'current_price' => 2500.00,
            'status' => 'active',
        ]);

        $chaseVariants = Product::where('is_chase_variant', true)->get();
        $this->assertCount(1, $chaseVariants);
        $this->assertTrue($chaseVariants->first()->is_chase_variant);

        $this->assertTrue(true); // Test passes if we reach here
    }
}