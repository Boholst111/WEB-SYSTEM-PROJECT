<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = new CartService();

        $this->user = User::factory()->create([
            'loyalty_tier' => 'bronze',
            'loyalty_credits' => 500.00,
        ]);

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'current_price' => 100.00,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function calculates_max_credits_usable_correctly()
    {
        $maxCredits = $this->cartService->calculateMaxCreditsUsable(1000.00);
        
        // 50% of 1000 = 500
        $this->assertEquals(500.00, $maxCredits);
    }

    /** @test */
    public function returns_zero_when_max_credits_below_minimum()
    {
        // Minimum redemption is 100, 50% of 150 = 75 (below minimum)
        $maxCredits = $this->cartService->calculateMaxCreditsUsable(150.00);
        
        $this->assertEquals(0, $maxCredits);
    }

    /** @test */
    public function calculates_shipping_cost_for_standard_courier()
    {
        $cost = $this->cartService->calculateShippingCost(500.00, $this->user, 'lbc:standard');
        
        $this->assertEquals(150.00, $cost);
    }

    /** @test */
    public function calculates_shipping_cost_for_express_courier()
    {
        $cost = $this->cartService->calculateShippingCost(500.00, $this->user, 'lbc:express');
        
        $this->assertEquals(250.00, $cost);
    }

    /** @test */
    public function returns_zero_shipping_when_free_threshold_met()
    {
        $cost = $this->cartService->calculateShippingCost(2500.00, $this->user, 'lbc:standard');
        
        $this->assertEquals(0, $cost);
    }

    /** @test */
    public function platinum_tier_gets_free_shipping()
    {
        $this->user->update(['loyalty_tier' => 'platinum']);
        
        $cost = $this->cartService->calculateShippingCost(100.00, $this->user, 'lbc:standard');
        
        $this->assertEquals(0, $cost);
    }

    /** @test */
    public function gold_tier_gets_free_shipping_above_3000()
    {
        $this->user->update(['loyalty_tier' => 'gold']);
        
        // Below gold threshold (1500) but above general threshold (2000)
        // Gold tier threshold is 3000, so should use the lower of the two
        $cost1 = $this->cartService->calculateShippingCost(1500.00, $this->user, 'lbc:standard');
        $this->assertEquals(150.00, $cost1);
        
        // Above gold threshold
        $cost2 = $this->cartService->calculateShippingCost(3500.00, $this->user, 'lbc:standard');
        $this->assertEquals(0, $cost2);
    }

    /** @test */
    public function gets_shipping_options_with_correct_structure()
    {
        $options = $this->cartService->getShippingOptions(500.00, $this->user);
        
        $this->assertIsArray($options);
        $this->assertArrayHasKey('options', $options);
        $this->assertArrayHasKey('free_shipping_threshold', $options);
        $this->assertArrayHasKey('is_free_shipping_eligible', $options);
        $this->assertArrayHasKey('amount_to_free_shipping', $options);
        
        $this->assertFalse($options['is_free_shipping_eligible']);
        $this->assertEquals(1500.00, $options['amount_to_free_shipping']);
    }

    /** @test */
    public function shipping_options_include_all_enabled_couriers()
    {
        $options = $this->cartService->getShippingOptions(500.00, $this->user);
        
        $this->assertNotEmpty($options['options']);
        
        // Check that options have required fields
        foreach ($options['options'] as $option) {
            $this->assertArrayHasKey('id', $option);
            $this->assertArrayHasKey('courier', $option);
            $this->assertArrayHasKey('service', $option);
            $this->assertArrayHasKey('name', $option);
            $this->assertArrayHasKey('cost', $option);
            $this->assertArrayHasKey('estimated_days', $option);
        }
    }

    /** @test */
    public function validates_cart_inventory_successfully()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price' => 100.00,
        ]);

        $validation = $this->cartService->validateCartInventory($this->user);
        
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }

    /** @test */
    public function detects_insufficient_stock_in_validation()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 15, // More than stock
            'price' => 100.00,
        ]);

        $validation = $this->cartService->validateCartInventory($this->user);
        
        $this->assertFalse($validation['valid']);
        $this->assertCount(1, $validation['errors']);
        $this->assertEquals('Insufficient stock', $validation['errors'][0]['error']);
    }

    /** @test */
    public function detects_unavailable_products_in_validation()
    {
        $this->product->update(['status' => 'inactive']);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $validation = $this->cartService->validateCartInventory($this->user);
        
        $this->assertFalse($validation['valid']);
        $this->assertCount(1, $validation['errors']);
        $this->assertEquals('Product is no longer available', $validation['errors'][0]['error']);
    }

    /** @test */
    public function gets_cart_summary_correctly()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $product2 = Product::factory()->create([
            'brand_id' => $this->product->brand_id,
            'category_id' => $this->product->category_id,
            'current_price' => 150.00,
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 3,
            'price' => 150.00,
        ]);

        $summary = $this->cartService->getCartSummary($this->user);
        
        $this->assertEquals(2, $summary['items_count']);
        $this->assertEquals(5, $summary['total_quantity']);
        $this->assertEquals(650.00, $summary['subtotal']);
    }

    /** @test */
    public function calculates_cart_totals_with_all_components()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $totals = $this->cartService->calculateCartTotals(
            $this->user,
            50.00, // credits to use
            'lbc:standard' // shipping option
        );
        
        $this->assertEquals(200.00, $totals['subtotal']);
        $this->assertEquals(50.00, $totals['credits_applied']);
        $this->assertEquals(50.00, $totals['discount_amount']);
        $this->assertEquals(150.00, $totals['shipping_cost']);
        $this->assertEquals(300.00, $totals['total']); // 200 - 50 + 150
    }

    /** @test */
    public function limits_credits_to_maximum_usable()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // Try to use 150 credits, but max is 100 (50% of 200)
        $totals = $this->cartService->calculateCartTotals($this->user, 150.00);
        
        $this->assertEquals(100.00, $totals['credits_applied']);
    }

    /** @test */
    public function limits_credits_to_available_balance()
    {
        $this->user->update(['loyalty_credits' => 50.00]);

        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // Try to use 100 credits, but user only has 50
        $totals = $this->cartService->calculateCartTotals($this->user, 100.00);
        
        $this->assertEquals(50.00, $totals['credits_applied']);
    }

    /** @test */
    public function formatted_values_include_currency_symbol()
    {
        ShoppingCart::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $totals = $this->cartService->calculateCartTotals($this->user, 50.00, 'lbc:standard');
        
        $this->assertStringStartsWith('₱', $totals['formatted']['subtotal']);
        $this->assertStringStartsWith('₱', $totals['formatted']['credits_applied']);
        $this->assertStringStartsWith('₱', $totals['formatted']['total']);
    }
}
