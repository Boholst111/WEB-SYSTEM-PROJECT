<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PreOrderDepositCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'current_price' => 1000.00,
            'is_preorder' => true,
        ]);
    }

    /** @test */
    public function it_calculates_deposit_with_default_percentage()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $preorder->calculateAmounts(); // Default 30%

        $this->assertEquals(1000.00, $preorder->total_amount);
        $this->assertEquals(300.00, $preorder->deposit_amount);
        $this->assertEquals(700.00, $preorder->remaining_amount);
    }

    /** @test */
    public function it_calculates_deposit_with_custom_percentages()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $testCases = [
            [0.1, 200.00, 1800.00],   // 10%
            [0.25, 500.00, 1500.00],  // 25%
            [0.5, 1000.00, 1000.00],  // 50%
            [0.75, 1500.00, 500.00],  // 75%
            [1.0, 2000.00, 0.00],     // 100%
        ];

        foreach ($testCases as [$percentage, $expectedDeposit, $expectedRemaining]) {
            $preorder->calculateAmounts($percentage);
            
            $this->assertEquals(2000.00, $preorder->total_amount, "Total amount for {$percentage}");
            $this->assertEquals($expectedDeposit, $preorder->deposit_amount, "Deposit for {$percentage}");
            $this->assertEquals($expectedRemaining, $preorder->remaining_amount, "Remaining for {$percentage}");
        }
    }

    /** @test */
    public function it_handles_fractional_currency_calculations()
    {
        $product = Product::factory()->create(['current_price' => 333.33]);
        $preorder = PreOrder::factory()->create([
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $preorder->calculateAmounts(0.3); // 30%

        $this->assertEquals(999.99, $preorder->total_amount);
        $this->assertEquals(300.00, $preorder->deposit_amount); // Rounded to 2 decimals
        $this->assertEquals(699.99, $preorder->remaining_amount);
    }

    /** @test */
    public function it_handles_minimum_deposit_amounts()
    {
        $cheapProduct = Product::factory()->create(['current_price' => 1.00]);
        $preorder = PreOrder::factory()->create([
            'product_id' => $cheapProduct->id,
            'quantity' => 1,
        ]);

        $preorder->calculateAmounts(0.3);

        $this->assertEquals(1.00, $preorder->total_amount);
        $this->assertEquals(0.30, $preorder->deposit_amount);
        $this->assertEquals(0.70, $preorder->remaining_amount);
    }

    /** @test */
    public function it_handles_large_quantity_calculations()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $preorder->calculateAmounts(0.4); // 40%

        $this->assertEquals(10000.00, $preorder->total_amount);
        $this->assertEquals(4000.00, $preorder->deposit_amount);
        $this->assertEquals(6000.00, $preorder->remaining_amount);
    }

    /** @test */
    public function it_maintains_calculation_precision()
    {
        $product = Product::factory()->create(['current_price' => 999.99]);
        $preorder = PreOrder::factory()->create([
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $preorder->calculateAmounts(0.333); // 33.3%

        $this->assertEquals(999.99, $preorder->total_amount);
        // 999.99 * 0.333 = 332.9967, which rounds to 333.00
        $this->assertEquals(333.00, $preorder->deposit_amount);
        $this->assertEquals(666.99, $preorder->remaining_amount);
        
        // Verify amounts add up correctly
        $this->assertEquals(
            $preorder->total_amount,
            $preorder->deposit_amount + $preorder->remaining_amount
        );
    }

    /** @test */
    public function it_handles_zero_quantity_edge_case()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 0,
        ]);

        $preorder->calculateAmounts();

        $this->assertEquals(0.00, $preorder->total_amount);
        $this->assertEquals(0.00, $preorder->deposit_amount);
        $this->assertEquals(0.00, $preorder->remaining_amount);
    }

    /** @test */
    public function it_handles_zero_price_edge_case()
    {
        $freeProduct = Product::factory()->create(['current_price' => 0.00]);
        $preorder = PreOrder::factory()->create([
            'product_id' => $freeProduct->id,
            'quantity' => 5,
        ]);

        $preorder->calculateAmounts();

        $this->assertEquals(0.00, $preorder->total_amount);
        $this->assertEquals(0.00, $preorder->deposit_amount);
        $this->assertEquals(0.00, $preorder->remaining_amount);
    }

    /** @test */
    public function it_recalculates_amounts_when_product_price_changes()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // Initial calculation
        $preorder->calculateAmounts(0.3);
        $this->assertEquals(600.00, $preorder->deposit_amount);

        // Change product price
        $this->product->update(['current_price' => 1500.00]);
        $preorder->refresh();

        // Recalculate with new price
        $preorder->calculateAmounts(0.3);
        $this->assertEquals(3000.00, $preorder->total_amount);
        $this->assertEquals(900.00, $preorder->deposit_amount);
        $this->assertEquals(2100.00, $preorder->remaining_amount);
    }

    /** @test */
    public function it_validates_deposit_percentage_bounds()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        // Test minimum percentage (0%)
        $preorder->calculateAmounts(0.0);
        $this->assertEquals(0.00, $preorder->deposit_amount);
        $this->assertEquals(1000.00, $preorder->remaining_amount);

        // Test maximum percentage (100%)
        $preorder->calculateAmounts(1.0);
        $this->assertEquals(1000.00, $preorder->deposit_amount);
        $this->assertEquals(0.00, $preorder->remaining_amount);
    }

    /** @test */
    public function it_handles_currency_rounding_consistently()
    {
        $product = Product::factory()->create(['current_price' => 100.01]);
        $preorder = PreOrder::factory()->create([
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $preorder->calculateAmounts(0.333); // Results in fractional cents

        // Verify proper rounding to 2 decimal places
        $this->assertEquals(300.03, $preorder->total_amount);
        $this->assertEquals(99.91, $preorder->deposit_amount);
        $this->assertEquals(200.12, $preorder->remaining_amount);

        // Verify amounts still add up (within rounding tolerance)
        $calculatedTotal = $preorder->deposit_amount + $preorder->remaining_amount;
        $this->assertEquals($preorder->total_amount, $calculatedTotal);
    }

    /** @test */
    public function it_preserves_calculation_integrity_across_multiple_operations()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        // Perform multiple calculations
        $percentages = [0.1, 0.25, 0.5, 0.75, 0.3];
        
        foreach ($percentages as $percentage) {
            $preorder->calculateAmounts($percentage);
            
            // Verify integrity
            $this->assertEquals(1000.00, $preorder->total_amount);
            $this->assertEquals(
                $preorder->total_amount,
                $preorder->deposit_amount + $preorder->remaining_amount,
                "Calculation integrity failed for {$percentage}"
            );
        }
    }
}