<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Admin\InventoryController;
use App\Services\NotificationService;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\PreOrder;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;

/**
 * Test inventory tracking and alert functionality.
 * Validates Requirements 1.5, 1.10
 */
class AdminInventoryTrackingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected InventoryController $inventoryController;
    protected $notificationServiceMock;
    protected User $user;
    protected Product $product;
    protected Brand $brand;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock
        $this->notificationServiceMock = Mockery::mock(NotificationService::class);

        // Create controller
        $this->inventoryController = new InventoryController($this->notificationServiceMock);

        // Create test data
        $this->user = User::factory()->create();
        $this->brand = Brand::factory()->create();
        $this->category = Category::factory()->create();
        
        $this->product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'current_price' => 1000.00,
            'stock_quantity' => 50,
            'status' => 'active',
            'is_preorder' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function inventory_index_provides_comprehensive_stock_overview()
    {
        // Create products with different stock levels
        $inStockProduct = Product::factory()->create([
            'name' => 'In Stock Product',
            'stock_quantity' => 25,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        $lowStockProduct = Product::factory()->create([
            'name' => 'Low Stock Product',
            'stock_quantity' => 3,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        $outOfStockProduct = Product::factory()->create([
            'name' => 'Out of Stock Product',
            'stock_quantity' => 0,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        $preorderProduct = Product::factory()->create([
            'name' => 'Preorder Product',
            'stock_quantity' => 0,
            'is_preorder' => true,
            'status' => 'active',
        ]);

        $chaseVariant = Product::factory()->create([
            'name' => 'Chase Variant',
            'stock_quantity' => 2,
            'is_chase_variant' => true,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        $request = new Request(['per_page' => 20]);

        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        
        // Verify summary counts
        $summary = $data['summary'];
        $this->assertEquals(6, $summary['total_products']); // Including original product
        $this->assertEquals(3, $summary['in_stock']); // product, inStockProduct, chaseVariant
        $this->assertEquals(2, $summary['low_stock']); // lowStockProduct, chaseVariant (assuming threshold 5)
        $this->assertEquals(1, $summary['out_of_stock']); // outOfStockProduct
        $this->assertEquals(1, $summary['preorders']); // preorderProduct
        $this->assertEquals(1, $summary['chase_variants']); // chaseVariant
    }

    /** @test */
    public function stock_status_filters_work_correctly()
    {
        // Create products for each status
        Product::factory()->create([
            'name' => 'Normal Stock',
            'stock_quantity' => 20,
            'is_preorder' => false,
        ]);

        Product::factory()->create([
            'name' => 'Low Stock',
            'stock_quantity' => 2,
            'is_preorder' => false,
        ]);

        Product::factory()->create([
            'name' => 'Out of Stock',
            'stock_quantity' => 0,
            'is_preorder' => false,
        ]);

        Product::factory()->create([
            'name' => 'Preorder Item',
            'stock_quantity' => 0,
            'is_preorder' => true,
        ]);

        // Test low stock filter
        $request = new Request([
            'stock_status' => 'low_stock',
            'low_stock_threshold' => 5,
        ]);

        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $products = $data['data']['data'];
        $this->assertCount(1, $products);
        $this->assertEquals('Low Stock', $products[0]['name']);

        // Test out of stock filter
        $request = new Request(['stock_status' => 'out_of_stock']);
        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $products = $data['data']['data'];
        $this->assertCount(1, $products);
        $this->assertEquals('Out of Stock', $products[0]['name']);

        // Test preorder filter
        $request = new Request(['stock_status' => 'preorder']);
        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $products = $data['data']['data'];
        $this->assertCount(1, $products);
        $this->assertEquals('Preorder Item', $products[0]['name']);
    }

    /** @test */
    public function low_stock_alert_identifies_critical_products()
    {
        // Create products with various stock levels
        Product::factory()->create([
            'name' => 'Critical Stock 1',
            'stock_quantity' => 1,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Critical Stock 2',
            'stock_quantity' => 4,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Normal Stock',
            'stock_quantity' => 20,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Inactive Product',
            'stock_quantity' => 2,
            'is_preorder' => false,
            'status' => 'inactive', // Should be excluded
        ]);

        Product::factory()->create([
            'name' => 'Preorder Product',
            'stock_quantity' => 3,
            'is_preorder' => true, // Should be excluded
            'status' => 'active',
        ]);

        $request = new Request(['threshold' => 5]);

        $response = $this->inventoryController->lowStock($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['count']);
        $this->assertEquals(5, $data['threshold']);

        $products = $data['data'];
        $this->assertCount(2, $products);

        // Should be ordered by stock quantity (lowest first)
        $this->assertEquals('Critical Stock 1', $products[0]['name']);
        $this->assertEquals(1, $products[0]['stock_quantity']);
        
        $this->assertEquals('Critical Stock 2', $products[1]['name']);
        $this->assertEquals(4, $products[1]['stock_quantity']);
    }

    /** @test */
    public function stock_update_creates_accurate_inventory_movements()
    {
        $initialStock = $this->product->stock_quantity; // 50

        // Test restock operation
        $request = new Request([
            'quantity' => 25,
            'type' => 'restock',
            'reason' => 'New shipment from supplier',
        ]);

        $response = $this->inventoryController->updateStock($request, $this->product);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        
        // Verify stock was updated
        $this->product->refresh();
        $this->assertEquals(75, $this->product->stock_quantity); // 50 + 25

        // Verify inventory movement was created
        $movement = InventoryMovement::where('product_id', $this->product->id)
            ->where('movement_type', 'restock')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(25, $movement->quantity_change);
        $this->assertEquals(50, $movement->quantity_before);
        $this->assertEquals(75, $movement->quantity_after);
        $this->assertEquals('New shipment from supplier', $movement->reason);
        $this->assertEquals('manual_adjustment', $movement->reference_type);
    }

    /** @test */
    public function stock_update_handles_different_movement_types()
    {
        $this->product->update(['stock_quantity' => 100]);

        // Test damage reduction
        $request = new Request([
            'quantity' => 8,
            'type' => 'damage',
            'reason' => 'Damaged during transport',
        ]);

        $response = $this->inventoryController->updateStock($request, $this->product);
        $this->assertTrue($response->getData(true)['success']);
        $this->assertEquals(92, $this->product->fresh()->stock_quantity); // 100 - 8

        // Test return addition
        $request = new Request([
            'quantity' => 3,
            'type' => 'return',
            'reason' => 'Customer return - unopened',
        ]);

        $response = $this->inventoryController->updateStock($request, $this->product);
        $this->assertTrue($response->getData(true)['success']);
        $this->assertEquals(95, $this->product->fresh()->stock_quantity); // 92 + 3

        // Test direct adjustment
        $request = new Request([
            'quantity' => 80,
            'type' => 'adjustment',
            'reason' => 'Physical inventory count correction',
        ]);

        $response = $this->inventoryController->updateStock($request, $this->product);
        $this->assertTrue($response->getData(true)['success']);
        $this->assertEquals(80, $this->product->fresh()->stock_quantity); // Direct set to 80

        // Verify all movements were logged
        $movements = InventoryMovement::where('product_id', $this->product->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(3, $movements);
        $this->assertEquals('damage', $movements[0]->movement_type);
        $this->assertEquals('return', $movements[1]->movement_type);
        $this->assertEquals('adjustment', $movements[2]->movement_type);
    }

    /** @test */
    public function low_stock_alert_triggers_notification()
    {
        $this->product->update(['stock_quantity' => 10]);

        $this->notificationServiceMock
            ->shouldReceive('sendLowStockAlert')
            ->once()
            ->with(Mockery::on(function ($product) {
                return $product->id === $this->product->id && $product->stock_quantity <= 5;
            }));

        // Update stock to trigger low stock alert
        $request = new Request([
            'quantity' => 6,
            'type' => 'damage',
            'reason' => 'Damaged items removed',
        ]);

        $response = $this->inventoryController->updateStock($request, $this->product);
        $this->assertTrue($response->getData(true)['success']);
        $this->assertEquals(4, $this->product->fresh()->stock_quantity); // 10 - 6
    }

    /** @test */
    public function inventory_reports_provide_comprehensive_analytics()
    {
        // Create inventory movements for testing
        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'movement_type' => 'sale',
            'quantity_change' => -5,
            'created_at' => now()->subDays(5),
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'movement_type' => 'sale',
            'quantity_change' => -3,
            'created_at' => now()->subDays(3),
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'movement_type' => 'restock',
            'quantity_change' => 20,
            'created_at' => now()->subDays(2),
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $this->product->id,
            'movement_type' => 'damage',
            'quantity_change' => -2,
            'created_at' => now()->subDays(1),
        ]);

        // Create additional products for slow moving analysis
        $slowProduct = Product::factory()->create([
            'name' => 'Slow Moving Product',
            'stock_quantity' => 15,
            'is_preorder' => false,
            'status' => 'active',
        ]);

        $request = new Request([
            'date_from' => now()->subWeek()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        $response = $this->inventoryController->reports($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $reportData = $data['data'];

        // Verify structure
        $this->assertArrayHasKey('movements', $reportData);
        $this->assertArrayHasKey('slow_moving', $reportData);
        $this->assertArrayHasKey('stock_value', $reportData);
        $this->assertArrayHasKey('summary', $reportData);

        // Verify movement summary
        $movements = $reportData['movements'];
        $this->assertCount(3, $movements); // sale, restock, damage

        $saleMovement = $movements->firstWhere('movement_type', 'sale');
        $this->assertEquals(2, $saleMovement['count']); // 2 sale movements
        $this->assertEquals(8, $saleMovement['total_quantity']); // 5 + 3

        $restockMovement = $movements->firstWhere('movement_type', 'restock');
        $this->assertEquals(1, $restockMovement['count']);
        $this->assertEquals(20, $restockMovement['total_quantity']);

        $damageMovement = $movements->firstWhere('movement_type', 'damage');
        $this->assertEquals(1, $damageMovement['count']);
        $this->assertEquals(2, $damageMovement['total_quantity']);

        // Verify slow moving products
        $slowMoving = $reportData['slow_moving'];
        $this->assertCount(1, $slowMoving); // slowProduct has no sales
        $this->assertEquals('Slow Moving Product', $slowMoving[0]['name']);

        // Verify summary
        $summary = $reportData['summary'];
        $this->assertEquals(4, $summary['total_movements']); // 2 + 1 + 1
        $this->assertEquals(30, $summary['total_quantity_moved']); // 8 + 20 + 2
    }

    /** @test */
    public function preorder_arrival_tracking_manages_status_correctly()
    {
        // Create preorders with different statuses
        $pendingPreorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->addDays(10),
            'actual_arrival_date' => null,
        ]);

        $arrivedPreorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->subDays(5),
            'actual_arrival_date' => now()->subDays(2),
        ]);

        $overduePreorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->subDays(7),
            'actual_arrival_date' => null,
        ]);

        // Test pending arrivals filter
        $request = new Request(['arrival_status' => 'pending']);
        $response = $this->inventoryController->preOrderArrivals($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $preorders = $data['data']['data'];
        $this->assertCount(1, $preorders);
        $this->assertEquals($pendingPreorder->id, $preorders[0]['id']);

        // Test arrived filter
        $request = new Request(['arrival_status' => 'arrived']);
        $response = $this->inventoryController->preOrderArrivals($request);
        $data = $response->getData(true);

        $preorders = $data['data']['data'];
        $this->assertCount(1, $preorders);
        $this->assertEquals($arrivedPreorder->id, $preorders[0]['id']);

        // Test overdue filter
        $request = new Request(['arrival_status' => 'overdue']);
        $response = $this->inventoryController->preOrderArrivals($request);
        $data = $response->getData(true);

        $preorders = $data['data']['data'];
        $this->assertCount(1, $preorders);
        $this->assertEquals($overduePreorder->id, $preorders[0]['id']);
    }

    /** @test */
    public function preorder_arrival_update_triggers_notifications()
    {
        $preorder = PreOrder::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'status' => 'deposit_paid',
            'estimated_arrival_date' => now()->addDays(5),
            'actual_arrival_date' => null,
        ]);

        $this->notificationServiceMock
            ->shouldReceive('sendPreOrderArrivalNotification')
            ->once()
            ->with(Mockery::on(function ($preorderArg) use ($preorder) {
                return $preorderArg->id === $preorder->id;
            }));

        $request = new Request([
            'actual_arrival_date' => now()->format('Y-m-d'),
            'notes' => 'Items arrived in excellent condition',
        ]);

        $response = $this->inventoryController->updatePreOrderArrival($request, $preorder);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        
        $preorder->refresh();
        $this->assertNotNull($preorder->actual_arrival_date);
        $this->assertEquals('Items arrived in excellent condition', $preorder->admin_notes);
        $this->assertEquals('ready_for_payment', $preorder->status);
    }

    /** @test */
    public function chase_variants_management_provides_special_handling()
    {
        // Create chase variants with different characteristics
        $availableChase = Product::factory()->create([
            'name' => 'Available Chase Variant',
            'is_chase_variant' => true,
            'stock_quantity' => 3,
            'current_price' => 2500.00,
            'status' => 'active',
        ]);

        $reservedChase = Product::factory()->create([
            'name' => 'Reserved Chase Variant',
            'is_chase_variant' => true,
            'stock_quantity' => 2,
            'current_price' => 3000.00,
            'status' => 'active',
        ]);

        $soldOutChase = Product::factory()->create([
            'name' => 'Sold Out Chase Variant',
            'is_chase_variant' => true,
            'stock_quantity' => 0,
            'current_price' => 3500.00,
            'status' => 'active',
        ]);

        // Create order for reserved chase variant
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $reservedChase->id,
            'quantity' => 1,
        ]);

        // Test available filter
        $request = new Request(['availability' => 'available']);
        $response = $this->inventoryController->chaseVariants($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $chaseVariants = $data['data']['data'];
        $this->assertCount(1, $chaseVariants);
        $this->assertEquals('Available Chase Variant', $chaseVariants[0]['name']);

        // Test sold out filter
        $request = new Request(['availability' => 'sold_out']);
        $response = $this->inventoryController->chaseVariants($request);
        $data = $response->getData(true);

        $chaseVariants = $data['data']['data'];
        $this->assertCount(1, $chaseVariants);
        $this->assertEquals('Sold Out Chase Variant', $chaseVariants[0]['name']);

        // Test summary calculations
        $request = new Request();
        $response = $this->inventoryController->chaseVariants($request);
        $data = $response->getData(true);

        $summary = $data['summary'];
        $this->assertEquals(3, $summary['total_chase_variants']);
        $this->assertEquals(2, $summary['available']); // availableChase + reservedChase
        $this->assertEquals(1, $summary['sold_out']); // soldOutChase
        $this->assertEquals(3000.00, $summary['average_price']); // (2500 + 3000 + 3500) / 3
    }

    /** @test */
    public function purchase_order_creation_manages_supplier_workflow()
    {
        $product2 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => 'Product 2',
            'current_price' => 800.00,
        ]);

        $request = new Request([
            'supplier_name' => 'Premium Collectibles Ltd',
            'supplier_email' => 'orders@premiumcollectibles.com',
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20,
                    'unit_cost' => 600.00,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 15,
                    'unit_cost' => 450.00,
                ],
            ],
            'expected_delivery_date' => now()->addWeeks(3)->format('Y-m-d'),
            'notes' => 'Rush order for upcoming product launch',
        ]);

        $response = $this->inventoryController->createPurchaseOrder($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $poData = $data['data'];

        // Verify purchase order details
        $this->assertArrayHasKey('purchase_order_number', $poData);
        $this->assertStringStartsWith('PO-', $poData['purchase_order_number']);
        $this->assertEquals('Premium Collectibles Ltd', $poData['supplier_name']);
        $this->assertEquals(18750.00, $poData['total_amount']); // (20 * 600) + (15 * 450)
        $this->assertEquals(2, $poData['items_count']);

        // Verify inventory movement was created
        $movement = InventoryMovement::where('movement_type', 'purchase_order')
            ->where('reference_id', $poData['purchase_order_number'])
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals($this->product->id, $movement->product_id);

        // Verify purchase order data is stored in reason field
        $reasonData = json_decode($movement->reason, true);
        $this->assertEquals($poData['purchase_order_number'], $reasonData['purchase_order_number']);
        $this->assertEquals('Premium Collectibles Ltd', $reasonData['supplier_name']);
        $this->assertEquals('orders@premiumcollectibles.com', $reasonData['supplier_email']);
        $this->assertEquals(18750.00, $reasonData['total_amount']);
        $this->assertEquals('pending', $reasonData['status']);
        $this->assertCount(2, $reasonData['items']);
    }

    /** @test */
    public function inventory_search_and_filtering_works_accurately()
    {
        // Create products for search testing
        Product::factory()->create([
            'name' => 'Hot Wheels Batmobile',
            'sku' => 'HW-BAT-001',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
        ]);

        Product::factory()->create([
            'name' => 'Matchbox Fire Truck',
            'sku' => 'MB-FIRE-002',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
        ]);

        $category2 = Category::factory()->create(['name' => 'Category 2']);
        Product::factory()->create([
            'name' => 'Tomica Police Car',
            'sku' => 'TOM-POL-003',
            'brand_id' => $this->brand->id,
            'category_id' => $category2->id,
        ]);

        // Test search by name
        $request = new Request(['search' => 'Batmobile']);
        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $products = $data['data']['data'];
        $this->assertCount(1, $products);
        $this->assertStringContains('Batmobile', $products[0]['name']);

        // Test search by SKU
        $request = new Request(['search' => 'MB-FIRE']);
        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $products = $data['data']['data'];
        $this->assertCount(1, $products);
        $this->assertEquals('MB-FIRE-002', $products[0]['sku']);

        // Test category filter
        $request = new Request(['category_id' => $category2->id]);
        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $products = $data['data']['data'];
        $this->assertCount(1, $products);
        $this->assertEquals('Tomica Police Car', $products[0]['name']);

        // Test brand filter
        $request = new Request(['brand_id' => $this->brand->id]);
        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $products = $data['data']['data'];
        $this->assertGreaterThanOrEqual(4, count($products)); // At least the 4 we created
    }

    /** @test */
    public function inventory_sorting_orders_products_correctly()
    {
        // Create products with different attributes for sorting
        Product::factory()->create([
            'name' => 'Alpha Product',
            'stock_quantity' => 5,
            'current_price' => 1500.00,
            'created_at' => now()->subDays(10),
        ]);

        Product::factory()->create([
            'name' => 'Beta Product',
            'stock_quantity' => 15,
            'current_price' => 800.00,
            'created_at' => now()->subDays(5),
        ]);

        Product::factory()->create([
            'name' => 'Gamma Product',
            'stock_quantity' => 25,
            'current_price' => 1200.00,
            'created_at' => now()->subDays(2),
        ]);

        // Test sort by name ascending
        $request = new Request([
            'sort_by' => 'name',
            'sort_order' => 'asc',
        ]);

        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $products = $data['data']['data'];
        $this->assertEquals('Alpha Product', $products[0]['name']);
        $this->assertEquals('Beta Product', $products[1]['name']);

        // Test sort by stock quantity descending
        $request = new Request([
            'sort_by' => 'stock_quantity',
            'sort_order' => 'desc',
        ]);

        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $products = $data['data']['data'];
        $this->assertGreaterThanOrEqual(25, $products[0]['stock_quantity']); // Highest stock first
        
        // Test sort by price ascending
        $request = new Request([
            'sort_by' => 'current_price',
            'sort_order' => 'asc',
        ]);

        $response = $this->inventoryController->index($request);
        $data = $response->getData(true);

        $products = $data['data']['data'];
        $this->assertLessThanOrEqual(1000.00, $products[0]['current_price']); // Lowest price first
    }
}