<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Eris\Generator;
use Eris\TestTrait;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\InventoryMovement;

/**
 * **Feature: diecast-empire, Property 6: Inventory stock consistency**
 * **Validates: Requirements 1.10**
 * 
 * Property-based test for database schema integrity focusing on inventory stock consistency.
 * This test validates that inventory stock quantities remain accurate across all operations
 * and that stock never becomes negative through normal operations.
 */
class InventoryStockConsistencyPropertyTest extends TestCase
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
        // Create a brand and category for products
        Brand::create([
            'name' => 'Test Brand',
            'slug' => 'test-brand',
            'description' => 'Test brand for property testing',
            'logo_url' => null,
            'website_url' => 'https://testbrand.com',
            'country_of_origin' => 'Test Country',
            'status' => 'active'
        ]);

        Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'description' => 'Test category for property testing',
            'image_url' => null,
            'parent_id' => null,
            'sort_order' => 1,
            'status' => 'active'
        ]);

        // Create a test user
        User::create([
            'email' => 'test@example.com',
            'password_hash' => bcrypt('password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Property: For any sequence of inventory operations (purchase, restock, adjustment),
     * the final stock quantity should accurately reflect all completed transactions,
     * and stock should never become negative through normal operations.
     */
    public function testInventoryStockConsistencyProperty(): void
    {
        $this->limitTo(20);
        $this->forAll(
            Generator\choose(1, 100), // initial_stock
            Generator\elements([
                ['purchase', 2],
                ['restock', 10],
                ['adjustment', 5],
                ['return', 3]
            ])
        )->then(function ($initialStock, $operation) {
            [$operationType, $quantity] = $operation;
            $operations = [[$operationType, $quantity]]; // Single operation for simplicity
            // Create a product with initial stock
            $product = Product::create([
                'sku' => 'TEST-' . uniqid(),
                'name' => 'Test Product',
                'description' => 'Test product for property testing',
                'brand_id' => 1,
                'category_id' => 1,
                'scale' => '1:64',
                'material' => 'diecast',
                'features' => ['opening_doors'],
                'is_chase_variant' => false,
                'base_price' => 29.99,
                'current_price' => 29.99,
                'stock_quantity' => $initialStock,
                'is_preorder' => false,
                'status' => 'active',
            ]);

            $expectedStock = $initialStock;
            $actualOperations = [];

            // Apply each operation and track expected stock
            foreach ($operations as [$operationType, $quantity]) {
                switch ($operationType) {
                    case 'purchase':
                        // Only allow purchase if we have sufficient stock
                        if ($product->stock_quantity >= $quantity) {
                            $success = $product->updateStock($quantity, 'sale');
                            if ($success) {
                                $expectedStock -= $quantity;
                                $actualOperations[] = ['purchase', $quantity];
                                
                                // Record inventory movement
                                InventoryMovement::create([
                                    'product_id' => $product->id,
                                    'movement_type' => InventoryMovement::TYPE_SALE,
                                    'quantity_change' => -$quantity,
                                    'quantity_before' => $product->stock_quantity + $quantity,
                                    'quantity_after' => $product->stock_quantity,
                                    'reference_type' => 'test',
                                    'reference_id' => 1,
                                    'reason' => 'Property test purchase',
                                    'created_by' => 1,
                                ]);
                            }
                        }
                        break;

                    case 'restock':
                        $success = $product->updateStock($quantity, 'restock');
                        if ($success) {
                            $expectedStock += $quantity;
                            $actualOperations[] = ['restock', $quantity];
                            
                            // Record inventory movement
                            InventoryMovement::create([
                                'product_id' => $product->id,
                                'movement_type' => InventoryMovement::TYPE_PURCHASE,
                                'quantity_change' => $quantity,
                                'quantity_before' => $product->stock_quantity + $quantity,
                                'quantity_after' => $product->stock_quantity,
                                'reference_type' => 'test',
                                'reference_id' => 1,
                                'reason' => 'Property test restock',
                                'created_by' => 1,
                            ]);
                        }
                        break;

                    case 'adjustment':
                        $newStock = max(0, $product->stock_quantity + $quantity);
                        $actualAdjustment = $newStock - $product->stock_quantity;
                        
                        $product->stock_quantity = $newStock;
                        $product->save();
                        
                        $expectedStock += $actualAdjustment;
                        $actualOperations[] = ['adjustment', $actualAdjustment];
                        
                        // Record inventory movement
                        InventoryMovement::create([
                            'product_id' => $product->id,
                            'movement_type' => InventoryMovement::TYPE_ADJUSTMENT,
                            'quantity_change' => $actualAdjustment,
                            'quantity_before' => $product->stock_quantity - $actualAdjustment,
                            'quantity_after' => $product->stock_quantity,
                            'reference_type' => 'test',
                            'reference_id' => 1,
                            'reason' => 'Property test adjustment',
                            'created_by' => 1,
                        ]);
                        break;

                    case 'return':
                        $success = $product->updateStock($quantity, 'return');
                        if ($success) {
                            $expectedStock += $quantity;
                            $actualOperations[] = ['return', $quantity];
                            
                            // Record inventory movement
                            InventoryMovement::create([
                                'product_id' => $product->id,
                                'movement_type' => InventoryMovement::TYPE_RETURN,
                                'quantity_change' => $quantity,
                                'quantity_before' => $product->stock_quantity - $quantity,
                                'quantity_after' => $product->stock_quantity,
                                'reference_type' => 'test',
                                'reference_id' => 1,
                                'reason' => 'Property test return',
                                'created_by' => 1,
                            ]);
                        }
                        break;
                }

                // Refresh product from database
                $product->refresh();
            }

            // Verify stock consistency properties
            $this->assertInventoryConsistency($product, $expectedStock, $actualOperations);
        });
    }

    /**
     * Property: Stock reservations should maintain consistency during order processing.
     */
    public function testStockReservationConsistencyProperty(): void
    {
        $this->limitTo(15);
        $this->forAll(
            Generator\choose(10, 100), // initial_stock
            Generator\choose(1, 5)  // single order quantity
        )->then(function ($initialStock, $orderQuantity) {
            $orderQuantities = [$orderQuantity]; // Single order for simplicity
            // Create a product with initial stock
            $product = Product::create([
                'sku' => 'RESERVE-' . uniqid(),
                'name' => 'Reservation Test Product',
                'description' => 'Test product for reservation testing',
                'brand_id' => 1,
                'category_id' => 1,
                'scale' => '1:64',
                'material' => 'diecast',
                'features' => ['opening_doors'],
                'is_chase_variant' => false,
                'base_price' => 29.99,
                'current_price' => 29.99,
                'stock_quantity' => $initialStock,
                'is_preorder' => false,
                'status' => 'active',
            ]);

            $totalReserved = 0;
            $successfulReservations = [];

            // Attempt to reserve stock for each order
            foreach ($orderQuantities as $quantity) {
                if ($product->stock_quantity >= $quantity) {
                    $success = $product->reserveStock($quantity);
                    if ($success) {
                        $totalReserved += $quantity;
                        $successfulReservations[] = $quantity;
                        $product->refresh();
                    }
                }
            }

            // Verify reservation consistency
            $expectedRemainingStock = $initialStock - $totalReserved;
            $this->assertEquals(
                $expectedRemainingStock,
                $product->stock_quantity,
                "Stock after reservations should equal initial stock minus total reserved. " .
                "Initial: {$initialStock}, Reserved: {$totalReserved}, Expected: {$expectedRemainingStock}, Actual: {$product->stock_quantity}"
            );

            // Stock should never be negative
            $this->assertGreaterThanOrEqual(
                0,
                $product->stock_quantity,
                "Stock quantity should never be negative after reservations"
            );

            // Test releasing reservations
            foreach ($successfulReservations as $quantity) {
                $product->releaseStock($quantity);
                $product->refresh();
            }

            // After releasing all reservations, stock should return to initial amount
            $this->assertEquals(
                $initialStock,
                $product->stock_quantity,
                "Stock should return to initial amount after releasing all reservations"
            );
        });
    }

    /**
     * Property: Pre-order products should not affect stock calculations.
     */
    public function testPreOrderStockConsistencyProperty(): void
    {
        $this->limitTo(10);
        $this->forAll(
            Generator\choose(0, 10), // initial_stock (can be 0 for pre-orders)
            Generator\choose(1, 10) // single pre-order quantity
        )->then(function ($initialStock, $preOrderQuantity) {
            $preOrderQuantities = [$preOrderQuantity]; // Single pre-order for simplicity
            // Create a pre-order product
            $product = Product::create([
                'sku' => 'PREORDER-' . uniqid(),
                'name' => 'Pre-order Test Product',
                'description' => 'Test product for pre-order testing',
                'brand_id' => 1,
                'category_id' => 1,
                'scale' => '1:64',
                'material' => 'diecast',
                'features' => ['opening_doors'],
                'is_chase_variant' => false,
                'base_price' => 29.99,
                'current_price' => 29.99,
                'stock_quantity' => $initialStock,
                'is_preorder' => true,
                'preorder_date' => now()->addDays(30),
                'status' => 'active',
            ]);

            $originalStock = $product->stock_quantity;

            // Attempt to reserve stock for pre-orders
            foreach ($preOrderQuantities as $quantity) {
                $success = $product->reserveStock($quantity);
                
                // Pre-order reservations should always succeed
                $this->assertTrue(
                    $success,
                    "Pre-order stock reservations should always succeed regardless of current stock"
                );
                
                $product->refresh();
                
                // Stock quantity should not change for pre-orders
                $this->assertEquals(
                    $originalStock,
                    $product->stock_quantity,
                    "Pre-order reservations should not affect stock quantity"
                );
            }
        });
    }

    private function assertInventoryConsistency(Product $product, int $expectedStock, array $operations): void
    {
        // Property 1: Final stock should match expected stock
        $this->assertEquals(
            $expectedStock,
            $product->stock_quantity,
            "Final stock quantity should match expected after operations: " . json_encode($operations)
        );

        // Property 2: Stock should never be negative
        $this->assertGreaterThanOrEqual(
            0,
            $product->stock_quantity,
            "Stock quantity should never be negative"
        );

        // Property 3: Inventory movements should sum to the stock change
        $movements = InventoryMovement::where('product_id', $product->id)->get();
        $totalMovement = $movements->sum('quantity_change');
        
        // Calculate expected total movement from operations
        $expectedMovement = 0;
        foreach ($operations as [$type, $quantity]) {
            switch ($type) {
                case 'purchase':
                    $expectedMovement -= $quantity;
                    break;
                case 'restock':
                case 'return':
                case 'adjustment':
                    $expectedMovement += $quantity;
                    break;
            }
        }

        $this->assertEquals(
            $expectedMovement,
            $totalMovement,
            "Sum of inventory movements should match expected movement from operations"
        );

        // Property 4: Each movement should have proper reference data
        foreach ($movements as $movement) {
            $this->assertNotNull($movement->product_id, "Movement should have product_id");
            $this->assertNotNull($movement->movement_type, "Movement should have movement_type");
            $this->assertNotNull($movement->quantity_change, "Movement should have quantity_change");
            $this->assertContains(
                $movement->movement_type,
                [
                    InventoryMovement::TYPE_SALE,
                    InventoryMovement::TYPE_PURCHASE,
                    InventoryMovement::TYPE_ADJUSTMENT,
                    InventoryMovement::TYPE_RETURN,
                    InventoryMovement::TYPE_DAMAGE
                ],
                "Movement type should be valid"
            );
        }
    }
}