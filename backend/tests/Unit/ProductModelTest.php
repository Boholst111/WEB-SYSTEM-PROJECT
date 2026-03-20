<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\PreOrder;
use App\Models\ShoppingCart;
use App\Models\Wishlist;
use App\Models\ProductReview;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'sku',
            'name',
            'description',
            'brand_id',
            'category_id',
            'scale',
            'material',
            'features',
            'is_chase_variant',
            'base_price',
            'current_price',
            'stock_quantity',
            'is_preorder',
            'preorder_date',
            'estimated_arrival_date',
            'status',
            'images',
            'specifications',
            'weight',
            'dimensions',
            'minimum_age',
        ];

        $product = new Product();
        $this->assertEquals($fillable, $product->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $product = Product::factory()->create([
            'features' => ['opening_doors', 'detailed_interior'],
            'images' => ['image1.jpg', 'image2.jpg'],
            'specifications' => ['length' => '10cm', 'width' => '5cm'],
            'dimensions' => ['length' => 10, 'width' => 5, 'height' => 3],
            'is_chase_variant' => true,
            'is_preorder' => false,
            'base_price' => '99.99',
            'current_price' => '89.99',
            'weight' => '0.25',
            'preorder_date' => '2024-06-01',
            'estimated_arrival_date' => '2024-07-01',
        ]);

        $this->assertIsArray($product->features);
        $this->assertIsArray($product->images);
        $this->assertIsArray($product->specifications);
        $this->assertIsArray($product->dimensions);
        $this->assertTrue($product->is_chase_variant);
        $this->assertFalse($product->is_preorder);
        $this->assertEquals(99.99, $product->base_price);
        $this->assertEquals(89.99, $product->current_price);
        $this->assertEquals(0.25, $product->weight);
        $this->assertInstanceOf(\Carbon\Carbon::class, $product->preorder_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $product->estimated_arrival_date);
    }

    /** @test */
    public function it_belongs_to_brand()
    {
        $brand = Brand::factory()->create();
        $product = Product::factory()->create(['brand_id' => $brand->id]);

        $this->assertInstanceOf(Brand::class, $product->brand);
        $this->assertEquals($brand->id, $product->brand->id);
    }

    /** @test */
    public function it_belongs_to_category()
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertEquals($category->id, $product->category->id);
    }

    /** @test */
    public function it_has_order_items_relationship()
    {
        $product = Product::factory()->create();
        $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $product->orderItems);
        $this->assertTrue($product->orderItems->contains($orderItem));
    }

    /** @test */
    public function it_has_preorders_relationship()
    {
        $product = Product::factory()->create();
        $preorder = PreOrder::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $product->preorders);
        $this->assertTrue($product->preorders->contains($preorder));
    }

    /** @test */
    public function it_has_cart_items_relationship()
    {
        $product = Product::factory()->create();
        $cartItem = ShoppingCart::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $product->cartItems);
        $this->assertTrue($product->cartItems->contains($cartItem));
    }

    /** @test */
    public function it_has_wishlist_items_relationship()
    {
        $product = Product::factory()->create();
        $wishlistItem = Wishlist::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $product->wishlistItems);
        $this->assertTrue($product->wishlistItems->contains($wishlistItem));
    }

    /** @test */
    public function it_has_reviews_relationship()
    {
        $product = Product::factory()->create();
        $review = ProductReview::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $product->reviews);
        $this->assertTrue($product->reviews->contains($review));
    }

    /** @test */
    public function it_has_inventory_movements_relationship()
    {
        $product = Product::factory()->create();
        $movement = InventoryMovement::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $product->inventoryMovements);
        $this->assertTrue($product->inventoryMovements->contains($movement));
    }

    /** @test */
    public function it_scopes_active_products()
    {
        $activeProduct = Product::factory()->create(['status' => 'active']);
        $inactiveProduct = Product::factory()->create(['status' => 'inactive']);

        $activeProducts = Product::active()->get();

        $this->assertTrue($activeProducts->contains($activeProduct));
        $this->assertFalse($activeProducts->contains($inactiveProduct));
    }

    /** @test */
    public function it_scopes_in_stock_products()
    {
        $inStockProduct = Product::factory()->create(['stock_quantity' => 10]);
        $outOfStockProduct = Product::factory()->create(['stock_quantity' => 0]);

        $inStockProducts = Product::inStock()->get();

        $this->assertTrue($inStockProducts->contains($inStockProduct));
        $this->assertFalse($inStockProducts->contains($outOfStockProduct));
    }

    /** @test */
    public function it_scopes_preorder_products()
    {
        $preorderProduct = Product::factory()->create(['is_preorder' => true]);
        $regularProduct = Product::factory()->create(['is_preorder' => false]);

        $preorderProducts = Product::preOrder()->get();

        $this->assertTrue($preorderProducts->contains($preorderProduct));
        $this->assertFalse($preorderProducts->contains($regularProduct));
    }

    /** @test */
    public function it_scopes_chase_variant_products()
    {
        $chaseProduct = Product::factory()->create(['is_chase_variant' => true]);
        $regularProduct = Product::factory()->create(['is_chase_variant' => false]);

        $chaseProducts = Product::chaseVariant()->get();

        $this->assertTrue($chaseProducts->contains($chaseProduct));
        $this->assertFalse($chaseProducts->contains($regularProduct));
    }

    /** @test */
    public function it_scopes_by_scale()
    {
        $product164 = Product::factory()->create(['scale' => '1:64']);
        $product143 = Product::factory()->create(['scale' => '1:43']);

        $products164 = Product::byScale('1:64')->get();

        $this->assertTrue($products164->contains($product164));
        $this->assertFalse($products164->contains($product143));
    }

    /** @test */
    public function it_scopes_by_material()
    {
        $diecastProduct = Product::factory()->create(['material' => 'diecast']);
        $plasticProduct = Product::factory()->create(['material' => 'plastic']);

        $diecastProducts = Product::byMaterial('diecast')->get();

        $this->assertTrue($diecastProducts->contains($diecastProduct));
        $this->assertFalse($diecastProducts->contains($plasticProduct));
    }

    /** @test */
    public function it_scopes_by_brand()
    {
        $brand1 = Brand::factory()->create();
        $brand2 = Brand::factory()->create();
        $product1 = Product::factory()->create(['brand_id' => $brand1->id]);
        $product2 = Product::factory()->create(['brand_id' => $brand2->id]);

        $brand1Products = Product::byBrand($brand1->id)->get();

        $this->assertTrue($brand1Products->contains($product1));
        $this->assertFalse($brand1Products->contains($product2));
    }

    /** @test */
    public function it_scopes_by_category()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();
        $product1 = Product::factory()->create(['category_id' => $category1->id]);
        $product2 = Product::factory()->create(['category_id' => $category2->id]);

        $category1Products = Product::byCategory($category1->id)->get();

        $this->assertTrue($category1Products->contains($product1));
        $this->assertFalse($category1Products->contains($product2));
    }

    /** @test */
    public function it_scopes_by_features()
    {
        $product1 = Product::factory()->create(['features' => ['opening_doors', 'detailed_interior']]);
        $product2 = Product::factory()->create(['features' => ['rubber_tires']]);

        $productsWithOpeningDoors = Product::byFeatures(['opening_doors'])->get();

        $this->assertTrue($productsWithOpeningDoors->contains($product1));
        $this->assertFalse($productsWithOpeningDoors->contains($product2));
    }

    /** @test */
    public function it_scopes_by_price_range()
    {
        $cheapProduct = Product::factory()->create(['current_price' => 50]);
        $expensiveProduct = Product::factory()->create(['current_price' => 150]);

        $midRangeProducts = Product::byPriceRange(75, 125)->get();

        $this->assertFalse($midRangeProducts->contains($cheapProduct));
        $this->assertFalse($midRangeProducts->contains($expensiveProduct));

        $allProducts = Product::byPriceRange(25, 200)->get();
        $this->assertTrue($allProducts->contains($cheapProduct));
        $this->assertTrue($allProducts->contains($expensiveProduct));
    }

    /** @test */
    public function it_filters_products_with_complex_criteria()
    {
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        
        $matchingProduct = Product::factory()->create([
            'scale' => '1:64',
            'material' => 'diecast',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'features' => ['opening_doors'],
            'current_price' => 100,
            'is_chase_variant' => true,
            'is_preorder' => false,
            'stock_quantity' => 5,
        ]);

        $nonMatchingProduct = Product::factory()->create([
            'scale' => '1:43',
            'material' => 'plastic',
        ]);

        $filters = [
            'scale' => '1:64',
            'material' => 'diecast',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'features' => ['opening_doors'],
            'min_price' => 50,
            'max_price' => 150,
            'is_chase_variant' => true,
            'in_stock' => true,
        ];

        $filteredProducts = Product::filter($filters)->get();

        $this->assertTrue($filteredProducts->contains($matchingProduct));
        $this->assertFalse($filteredProducts->contains($nonMatchingProduct));
    }

    /** @test */
    public function it_checks_if_product_is_available()
    {
        // Active product with stock
        $availableProduct = Product::factory()->create([
            'status' => 'active',
            'stock_quantity' => 5,
            'is_preorder' => false,
        ]);
        $this->assertTrue($availableProduct->isAvailable());

        // Active preorder product
        $preorderProduct = Product::factory()->create([
            'status' => 'active',
            'stock_quantity' => 0,
            'is_preorder' => true,
        ]);
        $this->assertTrue($preorderProduct->isAvailable());

        // Inactive product
        $inactiveProduct = Product::factory()->create([
            'status' => 'inactive',
            'stock_quantity' => 5,
        ]);
        $this->assertFalse($inactiveProduct->isAvailable());

        // Out of stock non-preorder product
        $outOfStockProduct = Product::factory()->create([
            'status' => 'active',
            'stock_quantity' => 0,
            'is_preorder' => false,
        ]);
        $this->assertFalse($outOfStockProduct->isAvailable());
    }

    /** @test */
    public function it_checks_if_product_is_low_stock()
    {
        $lowStockProduct = Product::factory()->create([
            'stock_quantity' => 3,
            'is_preorder' => false,
        ]);
        $this->assertTrue($lowStockProduct->isLowStock());

        $normalStockProduct = Product::factory()->create([
            'stock_quantity' => 10,
            'is_preorder' => false,
        ]);
        $this->assertFalse($normalStockProduct->isLowStock());

        $preorderProduct = Product::factory()->create([
            'stock_quantity' => 2,
            'is_preorder' => true,
        ]);
        $this->assertFalse($preorderProduct->isLowStock());

        $outOfStockProduct = Product::factory()->create([
            'stock_quantity' => 0,
            'is_preorder' => false,
        ]);
        $this->assertFalse($outOfStockProduct->isLowStock());
    }

    /** @test */
    public function it_gets_main_image_attribute()
    {
        $product = Product::factory()->create([
            'images' => ['main.jpg', 'secondary.jpg'],
        ]);
        $this->assertEquals('main.jpg', $product->getMainImageAttribute());

        $productWithoutImages = Product::factory()->create(['images' => []]);
        $this->assertNull($productWithoutImages->getMainImageAttribute());
    }

    /** @test */
    public function it_gets_formatted_price_attribute()
    {
        $product = Product::factory()->create(['current_price' => 123.45]);
        $this->assertEquals('₱123.45', $product->getFormattedPriceAttribute());
    }

    /** @test */
    public function it_calculates_discount_percentage()
    {
        $product = Product::factory()->create([
            'base_price' => 100,
            'current_price' => 80,
        ]);
        $this->assertEquals(20.0, $product->getDiscountPercentageAttribute());

        $productWithoutDiscount = Product::factory()->create([
            'base_price' => 100,
            'current_price' => 100,
        ]);
        $this->assertNull($productWithoutDiscount->getDiscountPercentageAttribute());
    }

    /** @test */
    public function it_checks_if_product_is_on_sale()
    {
        $saleProduct = Product::factory()->create([
            'base_price' => 100,
            'current_price' => 80,
        ]);
        $this->assertTrue($saleProduct->isOnSale());

        $regularProduct = Product::factory()->create([
            'base_price' => 100,
            'current_price' => 100,
        ]);
        $this->assertFalse($regularProduct->isOnSale());
    }

    /** @test */
    public function it_gets_average_rating_from_reviews()
    {
        $product = Product::factory()->create();
        
        ProductReview::factory()->create(['product_id' => $product->id, 'rating' => 5]);
        ProductReview::factory()->create(['product_id' => $product->id, 'rating' => 4]);
        ProductReview::factory()->create(['product_id' => $product->id, 'rating' => 3]);

        $this->assertEquals(4.0, $product->getAverageRatingAttribute());
    }

    /** @test */
    public function it_gets_review_count()
    {
        $product = Product::factory()->create();
        
        ProductReview::factory()->count(3)->create(['product_id' => $product->id]);

        $this->assertEquals(3, $product->getReviewCountAttribute());
    }

    /** @test */
    public function it_updates_stock_for_sale()
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $result = $product->updateStock(3, 'sale');
        
        $this->assertTrue($result);
        $this->assertEquals(7, $product->stock_quantity);
    }

    /** @test */
    public function it_fails_to_update_stock_for_sale_with_insufficient_stock()
    {
        $product = Product::factory()->create(['stock_quantity' => 2]);

        $result = $product->updateStock(5, 'sale');
        
        $this->assertFalse($result);
        $this->assertEquals(2, $product->stock_quantity);
    }

    /** @test */
    public function it_updates_stock_for_restock()
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $result = $product->updateStock(10, 'restock');
        
        $this->assertTrue($result);
        $this->assertEquals(15, $product->stock_quantity);
    }

    /** @test */
    public function it_updates_stock_for_return()
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $result = $product->updateStock(2, 'return');
        
        $this->assertTrue($result);
        $this->assertEquals(7, $product->stock_quantity);
    }

    /** @test */
    public function it_reserves_stock_for_regular_product()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
            'is_preorder' => false,
        ]);

        $result = $product->reserveStock(3);
        
        $this->assertTrue($result);
        $this->assertEquals(7, $product->stock_quantity);
    }

    /** @test */
    public function it_reserves_stock_for_preorder_product()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 0,
            'is_preorder' => true,
        ]);

        $result = $product->reserveStock(3);
        
        $this->assertTrue($result);
        $this->assertEquals(0, $product->stock_quantity); // Preorders don't affect stock
    }

    /** @test */
    public function it_fails_to_reserve_insufficient_stock()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 2,
            'is_preorder' => false,
        ]);

        $result = $product->reserveStock(5);
        
        $this->assertFalse($result);
        $this->assertEquals(2, $product->stock_quantity);
    }

    /** @test */
    public function it_releases_reserved_stock()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 5,
            'is_preorder' => false,
        ]);

        $result = $product->releaseStock(3);
        
        $this->assertTrue($result);
        $this->assertEquals(8, $product->stock_quantity);
    }

    /** @test */
    public function it_releases_stock_for_preorder_product()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 0,
            'is_preorder' => true,
        ]);

        $result = $product->releaseStock(3);
        
        $this->assertTrue($result);
        $this->assertEquals(0, $product->stock_quantity); // Preorders don't affect stock
    }
}