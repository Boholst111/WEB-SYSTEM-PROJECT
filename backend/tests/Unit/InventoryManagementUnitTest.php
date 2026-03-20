<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\PreOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementUnitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function product_can_update_stock_quantity()
    {
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'stock_quantity' => 10
        ]);

        $result = $product->updateStock(5, 'sale');
        
        $this->assertTrue($result);
        $this->assertEquals(5, $product->stock_quantity);
    }

    /** @test */
    public function product_can_check_low_stock_status()
    {
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'stock_quantity' => 3,
            'is_preorder' => false
        ]);

        $this->assertTrue($product->isLowStock(5));
        $this->assertFalse($product->isLowStock(2));
    }

    /** @test */
    public function preorder_can_be_marked_as_arrived()
    {
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        $user = User::factory()->create();
        
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_preorder' => true
        ]);

        $preorder = PreOrder::factory()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'status' => 'deposit_paid'
        ]);

        $result = $preorder->markReadyForPayment();
        
        $this->assertTrue($result);
        $this->assertEquals('ready_for_payment', $preorder->status);
        $this->assertNotNull($preorder->full_payment_due_date);
    }

    /** @test */
    public function inventory_movement_can_be_created()
    {
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        
        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'stock_quantity' => 10
        ]);

        $movement = InventoryMovement::create([
            'product_id' => $product->id,
            'movement_type' => 'restock',
            'quantity_change' => 5,
            'quantity_before' => 10,
            'quantity_after' => 15,
            'reason' => 'New shipment received'
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'movement_type' => 'restock',
            'quantity_change' => 5,
            'reason' => 'New shipment received'
        ]);
    }

    /** @test */
    public function chase_variant_products_can_be_identified()
    {
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        
        $chaseVariant = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_chase_variant' => true,
            'current_price' => 2500.00
        ]);

        $regularProduct = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_chase_variant' => false,
            'current_price' => 500.00
        ]);

        $chaseVariants = Product::where('is_chase_variant', true)->get();
        
        $this->assertCount(1, $chaseVariants);
        $this->assertTrue($chaseVariants->first()->is_chase_variant);
        $this->assertEquals(2500.00, $chaseVariants->first()->current_price);
    }
}