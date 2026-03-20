<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\PreOrder;
use App\Models\Payment;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class PreOrderManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create test product with pre-order capability
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        
        $this->product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_preorder' => true,
            'preorder_date' => now()->addMonth(),
            'current_price' => 1000.00,
        ]);
    }

    /** @test */
    public function user_can_create_preorder()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/preorders', [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'deposit_percentage' => 0.4, // 40% deposit
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'preorder_number',
                        'user_id',
                        'product_id',
                        'quantity',
                        'deposit_amount',
                        'remaining_amount',
                        'status',
                        'product' => [
                            'id',
                            'name',
                            'brand',
                            'category'
                        ]
                    ]
                ]);

        $this->assertDatabaseHas('preorders', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'status' => 'deposit_pending',
        ]);

        // Check deposit calculation (40% of 2000 = 800)
        $preorder = PreOrder::where('user_id', $this->user->id)->first();
        $this->assertEquals(800.00, $preorder->deposit_amount);
        $this->assertEquals(1200.00, $preorder->remaining_amount);
    }

    /** @test */
    public function user_cannot_create_preorder_for_non_preorder_product()
    {
        Sanctum::actingAs($this->user);

        $regularProduct = Product::factory()->create([
            'is_preorder' => false,
        ]);

        $response = $this->postJson('/api/preorders', [
            'product_id' => $regularProduct->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'This product is not available for pre-order'
                ]);
    }

    /** @test */
    public function user_cannot_create_duplicate_preorder()
    {
        Sanctum::actingAs($this->user);

        // Create first pre-order
        PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'status' => 'deposit_pending',
        ]);

        // Try to create another pre-order for the same product
        $response = $this->postJson('/api/preorders', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'You already have an active pre-order for this product'
                ]);
    }

    /** @test */
    public function user_can_pay_deposit()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'status' => 'deposit_pending',
            'deposit_amount' => 300.00,
            'remaining_amount' => 700.00,
        ]);

        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'gcash',
            'gateway_data' => ['phone' => '09123456789'],
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'preorder',
                        'payment'
                    ]
                ]);

        // Check pre-order status updated
        $preorder->refresh();
        $this->assertEquals('deposit_paid', $preorder->status);
        $this->assertNotNull($preorder->deposit_paid_at);

        // Check payment record created
        $this->assertDatabaseHas('payments', [
            'preorder_id' => $preorder->id,
            'payment_method' => 'gcash',
            'amount' => 300.00,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function user_can_complete_final_payment()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'status' => 'ready_for_payment',
            'deposit_amount' => 300.00,
            'remaining_amount' => 700.00,
            'deposit_paid_at' => now()->subWeek(),
            'actual_arrival_date' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/preorders/{$preorder->id}/complete-payment", [
            'payment_method' => 'maya',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'preorder',
                        'payment'
                    ]
                ]);

        // Check pre-order status updated
        $preorder->refresh();
        $this->assertEquals('payment_completed', $preorder->status);

        // Check payment record created
        $this->assertDatabaseHas('payments', [
            'preorder_id' => $preorder->id,
            'payment_method' => 'maya',
            'amount' => 700.00,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function user_can_view_preorder_list()
    {
        Sanctum::actingAs($this->user);

        // Create multiple pre-orders
        $preorders = PreOrder::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/preorders');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'preorder_number',
                            'status',
                            'product' => [
                                'id',
                                'name',
                                'brand',
                                'category'
                            ]
                        ]
                    ],
                    'pagination'
                ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function user_can_filter_preorders_by_status()
    {
        Sanctum::actingAs($this->user);

        PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'deposit_pending',
        ]);

        PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'payment_completed',
        ]);

        $response = $this->getJson('/api/preorders?status=deposit_pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('deposit_pending', $response->json('data.0.status'));
    }

    /** @test */
    public function user_can_view_preorder_details()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->getJson("/api/preorders/{$preorder->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'preorder_number',
                        'status',
                        'deposit_amount',
                        'remaining_amount',
                        'product',
                        'loyalty_transactions'
                    ]
                ]);
    }

    /** @test */
    public function user_cannot_access_other_users_preorder()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $preorder = PreOrder::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/preorders/{$preorder->id}");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized access to pre-order'
                ]);
    }

    /** @test */
    public function user_can_get_preorder_status()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'ready_for_payment',
            'deposit_amount' => 300.00,
            'remaining_amount' => 700.00,
            'full_payment_due_date' => now()->addWeek(),
        ]);

        $response = $this->getJson("/api/preorders/{$preorder->id}/status");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'status',
                        'status_label',
                        'deposit_paid',
                        'payment_completed',
                        'payment_overdue',
                        'can_be_cancelled',
                        'days_until_due',
                        'amounts' => [
                            'deposit',
                            'remaining',
                            'total'
                        ],
                        'dates'
                    ]
                ]);
    }

    /** @test */
    public function user_can_get_preorder_notifications()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'deposit_pending',
        ]);

        $response = $this->getJson("/api/preorders/{$preorder->id}/notifications");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'type',
                            'title',
                            'message',
                            'created_at',
                            'read'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function validation_errors_are_returned_for_invalid_preorder_creation()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/preorders', [
            'product_id' => 999999, // Non-existent product
            'quantity' => 0, // Invalid quantity
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors' => [
                        'product_id',
                        'quantity'
                    ]
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_preorder_endpoints()
    {
        $response = $this->getJson('/api/preorders');
        $response->assertStatus(401);

        $response = $this->postJson('/api/preorders', []);
        $response->assertStatus(401);
    }
}