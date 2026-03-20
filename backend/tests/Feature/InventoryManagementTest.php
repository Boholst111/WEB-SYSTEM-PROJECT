<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PreOrder;
use App\Models\User;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $product;
    protected $brand;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test admin user with proper role
        $this->admin = User::factory()->create([
            'email' => 'admin@diecastempire.com',
            'preferences' => ['role' => 'admin']
        ]);

        // Create test brand and category
        $this->brand = Brand::factory()->create();
        $this->category = Category::factory()->create();

        // Create test product
        $this->product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'stock_quantity' => 10,
            'is_chase_variant' => false,
            'is_preorder' => false,
        ]);
    }

    /** @test */
    public function admin_can_view_inventory_overview()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/inventory');

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'sku',
                            'name',
                            'stock_quantity',
                            'current_price',
                            'is_chase_variant',
                            'is_preorder',
                            'brand',
                            'category'
                        ]
                    ],
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ],
                'summary' => [
                    'total_products',
                    'in_stock',
                    'low_stock',
                    'out_of_stock',
                    'preorders',
                    'chase_variants'
                ]
            ]);
    }

    /** @test */
    public function admin_can_filter_inventory_by_stock_status()
    {
        // Create products with different stock levels
        Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'stock_quantity' => 0, // Out of stock
            'is_preorder' => false,
        ]);

        Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'stock_quantity' => 2, // Low stock
            'is_preorder' => false,
        ]);

        Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'is_preorder' => true, // Pre-order
        ]);

        // Test filtering by low stock
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/inventory?stock_status=low_stock&low_stock_threshold=5');

        $response->assertStatus(200);
        $products = $response->json('data.data');
        
        foreach ($products as $product) {
            $this->assertTrue($product['stock_quantity'] > 0 && $product['stock_quantity'] <= 5);
            $this->assertFalse($product['is_preorder']);
        }
    }

    /** @test */
    public function admin_can_update_product_stock()
    {
        $initialStock = $this->product->stock_quantity;
        $quantityToAdd = 5;

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/inventory/{$this->product->id}/stock", [
                'quantity' => $quantityToAdd,
                'type' => 'restock',
                'reason' => 'New shipment received'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'quantity_before' => $initialStock,
                    'quantity_after' => $initialStock + $quantityToAdd,
                    'quantity_change' => $quantityToAdd
                ]
            ]);

        // Verify product stock was updated
        $this->product->refresh();
        $this->assertEquals($initialStock + $quantityToAdd, $this->product->stock_quantity);

        // Verify inventory movement was recorded
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'restock',
            'quantity_change' => $quantityToAdd,
            'quantity_before' => $initialStock,
            'quantity_after' => $initialStock + $quantityToAdd,
            'reason' => 'New shipment received'
        ]);
    }

    /** @test */
    public function admin_can_view_low_stock_products()
    {
        // Create products with low stock
        $lowStockProduct = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'stock_quantity' => 3,
            'is_preorder' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/inventory/low-stock?threshold=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sku',
                        'stock_quantity',
                        'brand',
                        'category'
                    ]
                ],
                'threshold',
                'count'
            ]);

        $products = $response->json('data');
        foreach ($products as $product) {
            $this->assertTrue($product['stock_quantity'] > 0 && $product['stock_quantity'] <= 5);
        }
    }

    /** @test */
    public function admin_can_view_chase_variants()
    {
        // Create chase variant product
        $chaseVariant = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'is_chase_variant' => true,
            'stock_quantity' => 2,
            'current_price' => 2500.00
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/inventory/chase-variants');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'sku',
                            'stock_quantity',
                            'current_price',
                            'is_chase_variant',
                            'brand',
                            'category'
                        ]
                    ]
                ],
                'summary' => [
                    'total_chase_variants',
                    'available',
                    'sold_out',
                    'average_price'
                ]
            ]);

        $products = $response->json('data.data');
        foreach ($products as $product) {
            $this->assertTrue($product['is_chase_variant']);
        }
    }

    /** @test */
    public function admin_can_view_preorder_arrivals()
    {
        // Create user and pre-order
        $user = User::factory()->create();
        $preorderProduct = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'is_preorder' => true,
        ]);

        $preorder = PreOrder::factory()->create([
            'product_id' => $preorderProduct->id,
            'user_id' => $user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/inventory/preorder-arrivals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'preorder_number',
                            'quantity',
                            'estimated_arrival_date',
                            'actual_arrival_date',
                            'status',
                            'product',
                            'user'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function admin_can_update_preorder_arrival()
    {
        // Create user and pre-order
        $user = User::factory()->create();
        $preorderProduct = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'is_preorder' => true,
        ]);

        $preorder = PreOrder::factory()->create([
            'product_id' => $preorderProduct->id,
            'user_id' => $user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->addDays(7),
        ]);

        $arrivalDate = now()->format('Y-m-d');
        $notes = 'Product arrived in good condition';

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/inventory/preorder-arrivals/{$preorder->id}", [
                'actual_arrival_date' => $arrivalDate,
                'notes' => $notes
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Pre-order arrival updated successfully'
            ]);

        // Verify pre-order was updated
        $preorder->refresh();
        $this->assertEquals($arrivalDate, $preorder->actual_arrival_date->format('Y-m-d'));
        $this->assertEquals($notes, $preorder->admin_notes);
        $this->assertEquals('ready_for_payment', $preorder->status);
    }

    /** @test */
    public function admin_can_create_purchase_order()
    {
        $purchaseOrderData = [
            'supplier_name' => 'Test Supplier Co.',
            'supplier_email' => 'supplier@test.com',
            'expected_delivery_date' => now()->addDays(14)->format('Y-m-d'),
            'notes' => 'Urgent restock needed',
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20,
                    'unit_cost' => 150.00
                ]
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/inventory/purchase-orders', $purchaseOrderData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'purchase_order_number',
                    'supplier_name',
                    'total_amount',
                    'items_count',
                    'expected_delivery_date'
                ]
            ]);

        // Verify purchase order was recorded in inventory movements
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'purchase_order',
            'quantity_change' => 0, // No immediate stock change
        ]);
    }

    /** @test */
    public function stock_update_validation_works()
    {
        // Test missing required fields
        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/inventory/{$this->product->id}/stock", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity', 'type', 'reason']);

        // Test invalid quantity
        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/inventory/{$this->product->id}/stock", [
                'quantity' => -5,
                'type' => 'restock',
                'reason' => 'Test'
            ]);

        $response->assertStatus(422);

        // Test invalid type
        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/inventory/{$this->product->id}/stock", [
                'quantity' => 5,
                'type' => 'invalid_type',
                'reason' => 'Test'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /** @test */
    public function inventory_reports_work()
    {
        // Create some inventory movements with valid movement types
        InventoryMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => 'sale',
            'quantity_change' => -2,
            'quantity_before' => 10,
            'quantity_after' => 8,
            'reason' => 'Product sold',
            'created_by' => $this->admin->id,
        ]);

        InventoryMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => 'restock',
            'quantity_change' => 5,
            'quantity_before' => 8,
            'quantity_after' => 13,
            'reason' => 'New stock received',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/inventory/reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period' => [
                        'from',
                        'to'
                    ],
                    'movements',
                    'top_selling',
                    'slow_moving',
                    'stock_value',
                    'summary' => [
                        'total_movements',
                        'total_quantity_moved',
                        'stock_value',
                        'preorder_value',
                        'low_stock_alerts',
                        'out_of_stock_items'
                    ]
                ]
            ]);
    }
}