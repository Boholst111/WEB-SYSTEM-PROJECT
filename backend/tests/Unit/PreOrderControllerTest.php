<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\PreOrder;
use App\Models\Payment;
use App\Models\Brand;
use App\Models\Category;
use App\Http\Controllers\PreOrderController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Mockery;

class PreOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected PreOrderController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        
        $this->product = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_preorder' => true,
            'current_price' => 1000.00,
        ]);

        $this->controller = new PreOrderController();
    }

    /** @test */
    public function it_validates_preorder_creation_request()
    {
        Sanctum::actingAs($this->user);

        // Test missing required fields
        $response = $this->postJson('/api/preorders', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['product_id', 'quantity']);
    }

    /** @test */
    public function it_validates_product_is_available_for_preorder()
    {
        Sanctum::actingAs($this->user);

        $regularProduct = Product::factory()->create(['is_preorder' => false]);

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
    public function it_prevents_duplicate_preorders_for_same_product()
    {
        Sanctum::actingAs($this->user);

        // Create existing preorder
        PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

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
    public function it_creates_preorder_with_correct_calculations()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/preorders', [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'deposit_percentage' => 0.4, // 40%
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'preorder_number',
                        'deposit_amount',
                        'remaining_amount',
                        'total_amount',
                        'status',
                    ]
                ]);

        $preorder = PreOrder::where('user_id', $this->user->id)->first();
        $this->assertEquals(2000.00, $preorder->total_amount); // 1000 * 2
        $this->assertEquals(800.00, $preorder->deposit_amount); // 2000 * 0.4
        $this->assertEquals(1200.00, $preorder->remaining_amount); // 2000 - 800
    }

    /** @test */
    public function it_validates_deposit_payment_request()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

        // Test missing payment method
        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['payment_method']);
    }

    /** @test */
    public function it_validates_payment_method_options()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

        // Test invalid payment method
        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'invalid_method',
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['payment_method']);
    }

    /** @test */
    public function it_validates_gcash_payment_data()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

        // Test missing phone number for GCash
        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'gcash',
            'gateway_data' => [],
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['gateway_data.phone']);
    }

    /** @test */
    public function it_validates_maya_payment_data()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

        // Test missing phone number for Maya
        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'maya',
            'gateway_data' => [],
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['gateway_data.phone']);
    }

    /** @test */
    public function it_validates_bank_transfer_payment_data()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

        // Test missing bank details
        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'bank_transfer',
            'gateway_data' => [],
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'gateway_data.bank_name',
                    'gateway_data.account_number',
                    'gateway_data.account_name'
                ]);
    }

    /** @test */
    public function it_processes_gcash_deposit_payment_successfully()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 500.00,
        ]);

        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'gcash',
            'gateway_data' => [
                'phone' => '09123456789',
            ],
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

        // Check preorder status updated
        $preorder->refresh();
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertNotNull($preorder->deposit_paid_at);

        // Check payment record created
        $this->assertDatabaseHas('payments', [
            'preorder_id' => $preorder->id,
            'payment_method' => 'gcash',
            'amount' => 500.00,
            'status' => Payment::STATUS_COMPLETED,
        ]);
    }

    /** @test */
    public function it_processes_maya_deposit_payment_successfully()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 750.00,
        ]);

        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'maya',
            'gateway_data' => [
                'phone' => '09987654321',
            ],
        ]);

        $response->assertStatus(200);

        $preorder->refresh();
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertEquals('maya', $preorder->payment_method);

        $this->assertDatabaseHas('payments', [
            'preorder_id' => $preorder->id,
            'payment_method' => 'maya',
            'amount' => 750.00,
        ]);
    }

    /** @test */
    public function it_processes_bank_transfer_deposit_payment_successfully()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 1000.00,
        ]);

        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'bank_transfer',
            'gateway_data' => [
                'bank_name' => 'BPI',
                'account_number' => '1234567890',
                'account_name' => 'John Doe',
            ],
        ]);

        $response->assertStatus(200);

        $preorder->refresh();
        $this->assertEquals(PreOrder::STATUS_DEPOSIT_PAID, $preorder->status);
        $this->assertEquals('bank_transfer', $preorder->payment_method);

        $this->assertDatabaseHas('payments', [
            'preorder_id' => $preorder->id,
            'payment_method' => 'bank_transfer',
            'amount' => 1000.00,
        ]);
    }

    /** @test */
    public function it_prevents_deposit_payment_for_wrong_status()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PAID, // Already paid
        ]);

        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'gcash',
            'gateway_data' => ['phone' => '09123456789'],
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Deposit has already been paid for this pre-order'
                ]);
    }

    /** @test */
    public function it_completes_final_payment_successfully()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'remaining_amount' => 1500.00,
        ]);

        $response = $this->postJson("/api/preorders/{$preorder->id}/complete-payment", [
            'payment_method' => 'gcash',
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

        $preorder->refresh();
        $this->assertEquals(PreOrder::STATUS_PAYMENT_COMPLETED, $preorder->status);

        $this->assertDatabaseHas('payments', [
            'preorder_id' => $preorder->id,
            'payment_method' => 'gcash',
            'amount' => 1500.00,
            'payment_type' => 'final_payment',
        ]);
    }

    /** @test */
    public function it_prevents_final_payment_for_wrong_status()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

        $response = $this->postJson("/api/preorders/{$preorder->id}/complete-payment", [
            'payment_method' => 'gcash',
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Pre-order is not ready for final payment'
                ]);
    }

    /** @test */
    public function it_prevents_unauthorized_access_to_other_users_preorders()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $preorder = PreOrder::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/preorders/{$preorder->id}");
        
        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized access to pre-order'
                ]);
    }

    /** @test */
    public function it_returns_preorder_status_information()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
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

        $data = $response->json('data');
        $this->assertEquals('ready_for_payment', $data['status']);
        $this->assertEquals('Ready for Payment', $data['status_label']);
        $this->assertTrue($data['can_be_cancelled']);
        $this->assertEquals(7, $data['days_until_due']);
    }

    /** @test */
    public function it_handles_payment_gateway_failures_gracefully()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
            'deposit_amount' => 500.00,
        ]);

        // Mock a gateway failure by using invalid phone format
        $response = $this->postJson("/api/preorders/{$preorder->id}/deposit", [
            'payment_method' => 'gcash',
            'gateway_data' => [
                'phone' => 'invalid_phone',
            ],
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['gateway_data.phone']);
    }

    /** @test */
    public function it_filters_preorders_by_status()
    {
        Sanctum::actingAs($this->user);

        PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PENDING,
        ]);

        PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_DEPOSIT_PAID,
        ]);

        $response = $this->getJson('/api/preorders?status=deposit_pending');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('deposit_pending', $data[0]['status']);
    }

    /** @test */
    public function it_paginates_preorder_list()
    {
        Sanctum::actingAs($this->user);

        PreOrder::factory()->count(15)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/preorders?per_page=10');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total'
                    ]
                ]);

        $pagination = $response->json('pagination');
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(2, $pagination['last_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(15, $pagination['total']);
    }

    /** @test */
    public function it_returns_preorder_notifications()
    {
        Sanctum::actingAs($this->user);

        $preorder = PreOrder::factory()->create([
            'user_id' => $this->user->id,
            'status' => PreOrder::STATUS_READY_FOR_PAYMENT,
            'notification_sent' => true,
            'payment_reminder_sent_at' => now()->subDay(),
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
}