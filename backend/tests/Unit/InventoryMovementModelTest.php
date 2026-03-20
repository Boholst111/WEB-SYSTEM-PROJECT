<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryMovementModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'product_id',
            'movement_type',
            'quantity_change',
            'quantity_before',
            'quantity_after',
            'reference_type',
            'reference_id',
            'reason',
            'created_by',
        ];

        $movement = new InventoryMovement();
        $this->assertEquals($fillable, $movement->getFillable());
    }

    /** @test */
    public function it_belongs_to_product()
    {
        $product = Product::factory()->create();
        $movement = InventoryMovement::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $movement->product);
        $this->assertEquals($product->id, $movement->product->id);
    }

    /** @test */
    public function it_belongs_to_created_by_user()
    {
        $user = User::factory()->create();
        $movement = InventoryMovement::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $movement->createdBy);
        $this->assertEquals($user->id, $movement->createdBy->id);
    }

    /** @test */
    public function it_can_have_null_created_by_user()
    {
        $movement = InventoryMovement::factory()->create(['created_by' => null]);

        $this->assertNull($movement->createdBy);
    }

    /** @test */
    public function it_handles_different_movement_types()
    {
        $types = [
            InventoryMovement::TYPE_PURCHASE,
            InventoryMovement::TYPE_SALE,
            InventoryMovement::TYPE_RETURN,
            InventoryMovement::TYPE_ADJUSTMENT,
            InventoryMovement::TYPE_DAMAGE,
            InventoryMovement::TYPE_RESERVATION,
            InventoryMovement::TYPE_RELEASE,
        ];

        foreach ($types as $type) {
            $movement = InventoryMovement::factory()->create(['movement_type' => $type]);
            $this->assertEquals($type, $movement->movement_type);
        }
    }

    /** @test */
    public function it_tracks_quantity_changes()
    {
        $movement = InventoryMovement::factory()->create([
            'quantity_before' => 100,
            'quantity_change' => -5,
            'quantity_after' => 95,
        ]);

        $this->assertEquals(100, $movement->quantity_before);
        $this->assertEquals(-5, $movement->quantity_change);
        $this->assertEquals(95, $movement->quantity_after);
    }

    /** @test */
    public function it_handles_positive_quantity_changes()
    {
        $movement = InventoryMovement::factory()->create([
            'movement_type' => InventoryMovement::TYPE_PURCHASE,
            'quantity_before' => 50,
            'quantity_change' => 25,
            'quantity_after' => 75,
        ]);

        $this->assertEquals(InventoryMovement::TYPE_PURCHASE, $movement->movement_type);
        $this->assertEquals(50, $movement->quantity_before);
        $this->assertEquals(25, $movement->quantity_change);
        $this->assertEquals(75, $movement->quantity_after);
    }

    /** @test */
    public function it_handles_negative_quantity_changes()
    {
        $movement = InventoryMovement::factory()->create([
            'movement_type' => InventoryMovement::TYPE_SALE,
            'quantity_before' => 100,
            'quantity_change' => -10,
            'quantity_after' => 90,
        ]);

        $this->assertEquals(InventoryMovement::TYPE_SALE, $movement->movement_type);
        $this->assertEquals(100, $movement->quantity_before);
        $this->assertEquals(-10, $movement->quantity_change);
        $this->assertEquals(90, $movement->quantity_after);
    }

    /** @test */
    public function it_stores_reference_information()
    {
        $movement = InventoryMovement::factory()->create([
            'reference_type' => 'order',
            'reference_id' => '12345',
        ]);

        $this->assertEquals('order', $movement->reference_type);
        $this->assertEquals('12345', $movement->reference_id);
    }

    /** @test */
    public function it_can_have_null_reference_information()
    {
        $movement = InventoryMovement::factory()->create([
            'reference_type' => null,
            'reference_id' => null,
        ]);

        $this->assertNull($movement->reference_type);
        $this->assertNull($movement->reference_id);
    }

    /** @test */
    public function it_stores_reason_for_movement()
    {
        $movement = InventoryMovement::factory()->create([
            'reason' => 'Customer purchase - Order #12345',
        ]);

        $this->assertEquals('Customer purchase - Order #12345', $movement->reason);
    }

    /** @test */
    public function it_can_have_null_reason()
    {
        $movement = InventoryMovement::factory()->create(['reason' => null]);

        $this->assertNull($movement->reason);
    }

    /** @test */
    public function it_has_timestamps()
    {
        $movement = InventoryMovement::factory()->create();

        $this->assertNotNull($movement->created_at);
        $this->assertNotNull($movement->updated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $movement->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $movement->updated_at);
    }

    /** @test */
    public function it_tracks_purchase_movements()
    {
        $movement = InventoryMovement::factory()->create([
            'movement_type' => InventoryMovement::TYPE_PURCHASE,
            'quantity_change' => 50,
            'reason' => 'Restocked from supplier',
        ]);

        $this->assertEquals(InventoryMovement::TYPE_PURCHASE, $movement->movement_type);
        $this->assertEquals(50, $movement->quantity_change);
        $this->assertEquals('Restocked from supplier', $movement->reason);
    }

    /** @test */
    public function it_tracks_sale_movements()
    {
        $movement = InventoryMovement::factory()->create([
            'movement_type' => InventoryMovement::TYPE_SALE,
            'quantity_change' => -3,
            'reference_type' => 'order',
            'reference_id' => 'ORD123',
            'reason' => 'Sold to customer',
        ]);

        $this->assertEquals(InventoryMovement::TYPE_SALE, $movement->movement_type);
        $this->assertEquals(-3, $movement->quantity_change);
        $this->assertEquals('order', $movement->reference_type);
        $this->assertEquals('ORD123', $movement->reference_id);
        $this->assertEquals('Sold to customer', $movement->reason);
    }

    /** @test */
    public function it_tracks_return_movements()
    {
        $movement = InventoryMovement::factory()->create([
            'movement_type' => InventoryMovement::TYPE_RETURN,
            'quantity_change' => 2,
            'reason' => 'Customer return - defective item',
        ]);

        $this->assertEquals(InventoryMovement::TYPE_RETURN, $movement->movement_type);
        $this->assertEquals(2, $movement->quantity_change);
        $this->assertEquals('Customer return - defective item', $movement->reason);
    }

    /** @test */
    public function it_tracks_adjustment_movements()
    {
        $movement = InventoryMovement::factory()->create([
            'movement_type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity_change' => -1,
            'reason' => 'Inventory count correction',
        ]);

        $this->assertEquals(InventoryMovement::TYPE_ADJUSTMENT, $movement->movement_type);
        $this->assertEquals(-1, $movement->quantity_change);
        $this->assertEquals('Inventory count correction', $movement->reason);
    }

    /** @test */
    public function it_tracks_damage_movements()
    {
        $movement = InventoryMovement::factory()->create([
            'movement_type' => InventoryMovement::TYPE_DAMAGE,
            'quantity_change' => -2,
            'reason' => 'Damaged during shipping',
        ]);

        $this->assertEquals(InventoryMovement::TYPE_DAMAGE, $movement->movement_type);
        $this->assertEquals(-2, $movement->quantity_change);
        $this->assertEquals('Damaged during shipping', $movement->reason);
    }

    /** @test */
    public function it_tracks_reservation_movements()
    {
        $movement = InventoryMovement::factory()->create([
            'movement_type' => InventoryMovement::TYPE_RESERVATION,
            'quantity_change' => -5,
            'reference_type' => 'cart',
            'reference_id' => 'CART456',
            'reason' => 'Reserved for checkout',
        ]);

        $this->assertEquals(InventoryMovement::TYPE_RESERVATION, $movement->movement_type);
        $this->assertEquals(-5, $movement->quantity_change);
        $this->assertEquals('cart', $movement->reference_type);
        $this->assertEquals('CART456', $movement->reference_id);
        $this->assertEquals('Reserved for checkout', $movement->reason);
    }

    /** @test */
    public function it_tracks_release_movements()
    {
        $movement = InventoryMovement::factory()->create([
            'movement_type' => InventoryMovement::TYPE_RELEASE,
            'quantity_change' => 3,
            'reference_type' => 'cart',
            'reference_id' => 'CART456',
            'reason' => 'Released from abandoned cart',
        ]);

        $this->assertEquals(InventoryMovement::TYPE_RELEASE, $movement->movement_type);
        $this->assertEquals(3, $movement->quantity_change);
        $this->assertEquals('cart', $movement->reference_type);
        $this->assertEquals('CART456', $movement->reference_id);
        $this->assertEquals('Released from abandoned cart', $movement->reason);
    }

    /** @test */
    public function it_maintains_quantity_consistency()
    {
        $movement = InventoryMovement::factory()->create([
            'quantity_before' => 100,
            'quantity_change' => -15,
            'quantity_after' => 85,
        ]);

        // Verify the math is correct
        $this->assertEquals(
            $movement->quantity_before + $movement->quantity_change,
            $movement->quantity_after
        );
    }
}