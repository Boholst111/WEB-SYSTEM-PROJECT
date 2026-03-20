<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ShippingServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected ShippingService $shippingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shippingService = new ShippingService();
    }

    /** @test */
    public function it_can_generate_shipping_label_for_single_order()
    {
        $order = $this->createOrderWithItems();

        $result = $this->shippingService->generateShippingLabel($order, 'lbc', 'standard');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('tracking_number', $result);
        $this->assertArrayHasKey('label_url', $result);
        $this->assertStringStartsWith('LBC', $result['tracking_number']);
    }

    /** @test */
    public function it_can_generate_bulk_shipping_labels()
    {
        $orders = collect();
        for ($i = 0; $i < 3; $i++) {
            $orders->push($this->createOrderWithItems([
                'status' => Order::STATUS_PROCESSING,
                'payment_status' => Order::PAYMENT_PAID
            ]));
        }

        $orderIds = $orders->pluck('id')->toArray();

        $result = $this->shippingService->generateBulkLabels($orderIds, 'lbc', 'standard');

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertEquals(3, $result['summary']['total']);
        $this->assertEquals(3, $result['summary']['successful']);
        $this->assertEquals(0, $result['summary']['failed']);

        // Verify orders were updated with tracking information
        foreach ($orders as $order) {
            $order->refresh();
            $this->assertNotNull($order->tracking_number);
            $this->assertEquals('lbc', $order->courier_service);
            $this->assertEquals(Order::STATUS_PROCESSING, $order->status);
        }
    }

    /** @test */
    public function it_returns_error_for_unsupported_courier()
    {
        $order = $this->createOrderWithItems();

        $result = $this->shippingService->generateShippingLabel($order, 'unsupported_courier');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unsupported courier service', $result['message']);
    }

    /** @test */
    public function it_can_get_tracking_information()
    {
        $trackingNumber = 'LBC123456789';

        $trackingInfo = $this->shippingService->getTrackingInfo($trackingNumber, 'lbc');

        $this->assertIsArray($trackingInfo);
        $this->assertEquals($trackingNumber, $trackingInfo['tracking_number']);
        $this->assertArrayHasKey('status', $trackingInfo);
        $this->assertArrayHasKey('events', $trackingInfo);
        $this->assertIsArray($trackingInfo['events']);
    }

    /** @test */
    public function it_can_detect_courier_from_tracking_number()
    {
        $testCases = [
            'LBC123456789' => 'lbc',
            'JT987654321' => 'jnt',
            'NV555666777' => 'ninjavan',
            '2GO111222333' => '2go'
        ];

        foreach ($testCases as $trackingNumber => $expectedCourier) {
            $trackingInfo = $this->shippingService->getTrackingInfo($trackingNumber);
            
            $this->assertIsArray($trackingInfo);
            $this->assertEquals($trackingNumber, $trackingInfo['tracking_number']);
        }
    }

    /** @test */
    public function it_returns_null_for_invalid_tracking_number()
    {
        $trackingInfo = $this->shippingService->getTrackingInfo('INVALID123');

        $this->assertNull($trackingInfo);
    }

    /** @test */
    public function it_can_calculate_shipping_cost()
    {
        $order = $this->createOrderWithItems();

        $cost = $this->shippingService->calculateShippingCost($order, 'lbc', 'standard');

        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
    }

    /** @test */
    public function it_calculates_different_costs_for_different_services()
    {
        $order = $this->createOrderWithItems();

        $standardCost = $this->shippingService->calculateShippingCost($order, 'lbc', 'standard');
        $expressCost = $this->shippingService->calculateShippingCost($order, 'lbc', 'express');

        $this->assertGreaterThan($standardCost, $expressCost);
    }

    /** @test */
    public function it_can_get_available_shipping_options()
    {
        $order = $this->createOrderWithItems();

        $options = $this->shippingService->getShippingOptions($order);

        $this->assertIsArray($options);
        $this->assertGreaterThan(0, count($options));

        foreach ($options as $option) {
            $this->assertArrayHasKey('courier', $option);
            $this->assertArrayHasKey('service', $option);
            $this->assertArrayHasKey('name', $option);
            $this->assertArrayHasKey('cost', $option);
            $this->assertArrayHasKey('estimated_delivery_days', $option);
            $this->assertArrayHasKey('estimated_delivery_date', $option);
        }

        // Verify options are sorted by cost
        $costs = array_column($options, 'cost');
        $sortedCosts = $costs;
        sort($sortedCosts);
        $this->assertEquals($sortedCosts, $costs);
    }

    /** @test */
    public function it_can_create_shipment_and_update_order()
    {
        $order = $this->createOrderWithItems([
            'status' => Order::STATUS_PROCESSING
        ]);

        $result = $this->shippingService->createShipment($order, [
            'courier_service' => 'lbc',
            'service_type' => 'standard'
        ]);

        $this->assertTrue($result['success']);
        
        $order->refresh();
        $this->assertEquals(Order::STATUS_SHIPPED, $order->status);
        $this->assertNotNull($order->tracking_number);
        $this->assertEquals('lbc', $order->courier_service);
        $this->assertNotNull($order->shipped_at);
    }

    /** @test */
    public function it_calculates_order_weight_correctly()
    {
        // Create order with products that have specific weights
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        // Product 1: 0.5kg, quantity 2 = 1kg
        $product1 = Product::factory()->create(['weight' => 0.5]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 2
        ]);

        // Product 2: 1.2kg, quantity 1 = 1.2kg
        $product2 = Product::factory()->create(['weight' => 1.2]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 1
        ]);

        // Total expected weight: 1kg + 1.2kg = 2.2kg
        $cost = $this->shippingService->calculateShippingCost($order, 'lbc', 'standard');
        
        // Cost should be base rate + weight rate for additional 1.2kg (2.2 - 1.0)
        $expectedBaseCost = 150.0; // LBC standard rate
        $expectedWeightCost = 1.2 * 20.0; // Additional weight * weight rate
        $expectedMinimumCost = $expectedBaseCost + $expectedWeightCost;
        
        $this->assertGreaterThanOrEqual($expectedMinimumCost, $cost);
    }

    /** @test */
    public function it_handles_orders_with_no_product_weights()
    {
        // Create order with products that have no weight specified
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $product = Product::factory()->create(['weight' => null]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3
        ]);

        $cost = $this->shippingService->calculateShippingCost($order, 'lbc', 'standard');
        
        // Should use default weight (0.5kg per item) and minimum 1kg
        $this->assertGreaterThan(0, $cost);
    }

    /** @test */
    public function it_generates_different_tracking_numbers_for_different_couriers()
    {
        $order = $this->createOrderWithItems();

        $lbcResult = $this->shippingService->generateShippingLabel($order, 'lbc');
        $jntResult = $this->shippingService->generateShippingLabel($order, 'jnt');
        $ninjaResult = $this->shippingService->generateShippingLabel($order, 'ninjavan');
        $twoGoResult = $this->shippingService->generateShippingLabel($order, '2go');

        $this->assertStringStartsWith('LBC', $lbcResult['tracking_number']);
        $this->assertStringStartsWith('JT', $jntResult['tracking_number']);
        $this->assertStringStartsWith('NV', $ninjaResult['tracking_number']);
        $this->assertStringStartsWith('2GO', $twoGoResult['tracking_number']);
    }

    /** @test */
    public function it_provides_different_tracking_statuses_for_different_couriers()
    {
        $lbcTracking = $this->shippingService->getTrackingInfo('LBC123456789', 'lbc');
        $jntTracking = $this->shippingService->getTrackingInfo('JT123456789', 'jnt');
        $ninjaTracking = $this->shippingService->getTrackingInfo('NV123456789', 'ninjavan');

        $this->assertEquals('in_transit', $lbcTracking['status']);
        $this->assertEquals('out_for_delivery', $jntTracking['status']);
        $this->assertEquals('delivered', $ninjaTracking['status']);
    }

    /**
     * Helper method to create an order with items.
     */
    private function createOrderWithItems(array $orderAttributes = []): Order
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(array_merge([
            'user_id' => $user->id,
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_line_1' => '123 Test St',
                'city' => 'Manila',
                'province' => 'Metro Manila',
                'postal_code' => '1000',
                'phone' => '+63 9123456789'
            ]
        ], $orderAttributes));

        // Create order items
        $products = Product::factory()->count(2)->create([
            'weight' => 0.5,
            'current_price' => 500.00
        ]);

        foreach ($products as $product) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->current_price
            ]);
        }

        // Load relationships to avoid lazy loading issues
        $order->load('items.product');

        return $order;
    }
}