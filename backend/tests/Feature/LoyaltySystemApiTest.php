<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\LoyaltyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class LoyaltySystemApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'loyalty_tier' => 'bronze',
            'loyalty_credits' => 100.00,
            'total_spent' => 5000.00,
        ]);
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_returns_user_loyalty_balance_and_tier_information()
    {
        // Create some transactions
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 50.00,
            'is_expired' => false,
            'expires_at' => now()->addDays(15), // Expiring soon
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
            'amount' => -20.00,
        ]);

        $response = $this->getJson('/api/loyalty/balance');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'available_credits',
                    'total_earned',
                    'total_redeemed',
                    'expiring_soon',
                    'expiring_days',
                    'current_tier',
                    'tier_benefits',
                    'next_tier',
                    'progress_to_next_tier',
                    'total_spent',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_tier' => 'bronze',
                    'next_tier' => 'silver',
                    'total_spent' => 5000.00,
                ]
            ]);
    }

    /** @test */
    public function it_returns_paginated_transaction_history()
    {
        // Create multiple transactions
        LoyaltyTransaction::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
        ]);

        LoyaltyTransaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
        ]);

        $response = $this->getJson('/api/loyalty/transactions?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'type',
                            'type_label',
                            'amount',
                            'formatted_amount',
                            'balance_before',
                            'balance_after',
                            'description',
                            'reference_id',
                            'order_id',
                            'preorder_id',
                            'expires_at',
                            'days_until_expiration',
                            'is_expired',
                            'is_credit',
                            'is_debit',
                            'created_at',
                            'metadata',
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'per_page' => 5,
                ]
            ]);
    }

    /** @test */
    public function it_filters_transactions_by_type()
    {
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
        ]);

        $response = $this->getJson('/api/loyalty/transactions?type=earned');

        $response->assertOk();
        
        $transactions = $response->json('data.data');
        $this->assertCount(1, $transactions);
        $this->assertEquals('earned', $transactions[0]['type']);
    }

    /** @test */
    public function it_filters_transactions_by_date_range()
    {
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => '2024-01-01',
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => '2024-01-15',
        ]);

        $response = $this->getJson('/api/loyalty/transactions?start_date=2024-01-10&end_date=2024-01-20');

        $response->assertOk();
        
        $transactions = $response->json('data.data');
        $this->assertCount(1, $transactions);
    }

    /** @test */
    public function it_redeems_loyalty_credits_successfully()
    {
        // Create earned credits transaction to give user available balance
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 150.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        $order = Order::factory()->create(['user_id' => $this->user->id]);
        
        $redeemData = [
            'amount' => 100.00, // Changed from 50 to meet minimum requirement
            'order_total' => 300.00, // Increased to maintain percentage
            'order_id' => $order->id,
            'description' => 'Test redemption',
        ];

        $response = $this->postJson('/api/loyalty/redeem', $redeemData);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_id',
                    'redeemed_credits',
                    'discount_amount',
                    'remaining_credits',
                    'conversion_rate',
                    'created_at',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'redeemed_credits' => 100.00,
                    'discount_amount' => 100.00,
                ]
            ]);

        // Check transaction was created
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $this->user->id,
            'order_id' => $order->id,
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
            'amount' => -100.00,
        ]);
    }

    /** @test */
    public function it_prevents_redemption_exceeding_maximum_percentage()
    {
        $redeemData = [
            'amount' => 150.00, // 75% of 200
            'order_total' => 200.00,
        ];

        $response = $this->postJson('/api/loyalty/redeem', $redeemData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot redeem more than 50% of order total',
            ]);
    }

    /** @test */
    public function it_prevents_redemption_exceeding_available_balance()
    {
        $redeemData = [
            'amount' => 150.00, // More than available 100.00
            'order_total' => 500.00,
        ];

        $response = $this->postJson('/api/loyalty/redeem', $redeemData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient credits balance',
            ]);
    }

    /** @test */
    public function it_validates_minimum_redemption_amount()
    {
        $redeemData = [
            'amount' => 50.00, // Below minimum of 100
            'order_total' => 200.00,
        ];

        $response = $this->postJson('/api/loyalty/redeem', $redeemData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_returns_tier_status_and_progression()
    {
        $response = $this->getJson('/api/loyalty/tier-status');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_tier',
                    'current_tier_benefits',
                    'next_tier',
                    'next_tier_benefits',
                    'total_spent',
                    'progress_percentage',
                    'spending_to_next_tier',
                    'tier_thresholds',
                    'tier_history',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_tier' => 'bronze',
                    'next_tier' => 'silver',
                ]
            ]);
    }

    /** @test */
    public function it_calculates_credits_earnings_correctly()
    {
        $calculationData = [
            'purchase_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/loyalty/calculate-earnings', $calculationData);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'purchase_amount',
                    'base_rate',
                    'tier_multiplier',
                    'bonus_rate',
                    'base_credits',
                    'tier_credits',
                    'bonus_credits',
                    'total_credits',
                    'current_tier',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'purchase_amount' => 1000.00,
                    'current_tier' => 'bronze',
                ]
            ]);
    }

    /** @test */
    public function it_processes_credits_earning_from_order()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'total_amount' => 1000.00,
        ]);

        $earningData = [
            'order_id' => $order->id,
            'purchase_amount' => 1000.00,
            'description' => 'Credits from test order',
        ];

        $response = $this->postJson('/api/loyalty/earn-credits', $earningData);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_id',
                    'credits_earned',
                    'base_credits',
                    'tier_credits',
                    'bonus_credits',
                    'expires_at',
                    'new_balance',
                    'current_tier',
                    'created_at',
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Check transaction was created
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $this->user->id,
            'order_id' => $order->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
        ]);

        // Check user's total spent was updated
        $this->user->refresh();
        $this->assertEquals(6000.00, $this->user->total_spent); // 5000 + 1000
    }

    /** @test */
    public function it_prevents_duplicate_earning_for_same_order()
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        // Create existing transaction
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'order_id' => $order->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
        ]);

        $earningData = [
            'order_id' => $order->id,
            'purchase_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/loyalty/earn-credits', $earningData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Credits already earned for this order',
            ]);
    }

    /** @test */
    public function it_returns_expiring_credits_information()
    {
        // Create expiring credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 30.00,
            'is_expired' => false,
            'expires_at' => now()->addDays(15),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 20.00,
            'is_expired' => false,
            'expires_at' => now()->addDays(25),
        ]);

        // Create non-expiring credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $this->user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 40.00,
            'is_expired' => false,
            'expires_at' => now()->addDays(45),
        ]);

        $response = $this->getJson('/api/loyalty/expiring-credits?days=30');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_expiring',
                    'warning_days',
                    'expiring_by_date',
                    'transactions',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_expiring' => 50.00, // 30 + 20
                    'warning_days' => 30,
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_loyalty_endpoints()
    {
        // Remove authentication for this test
        $this->app['auth']->forgetGuards();
        
        $response = $this->getJson('/api/loyalty/balance');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_required_fields_for_redemption()
    {
        $response = $this->postJson('/api/loyalty/redeem', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'order_total']);
    }

    /** @test */
    public function it_validates_required_fields_for_earning_calculation()
    {
        $response = $this->postJson('/api/loyalty/calculate-earnings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_amount']);
    }

    /** @test */
    public function it_validates_required_fields_for_earning_credits()
    {
        $response = $this->postJson('/api/loyalty/earn-credits', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_id', 'purchase_amount']);
    }

    /** @test */
    public function it_handles_tier_progression_correctly()
    {
        // Update user to be close to silver tier
        $this->user->update(['total_spent' => 9500.00]); // Silver threshold is 10000

        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $earningData = [
            'order_id' => $order->id,
            'purchase_amount' => 1000.00, // This should push to silver tier
        ];

        $response = $this->postJson('/api/loyalty/earn-credits', $earningData);

        $response->assertOk();

        // Check user was upgraded to silver tier
        $this->user->refresh();
        $this->assertEquals('silver', $this->user->loyalty_tier);
        $this->assertEquals(10500.00, $this->user->total_spent);
    }

    /** @test */
    public function it_applies_tier_multipliers_correctly()
    {
        // Update user to silver tier
        $this->user->update(['loyalty_tier' => 'silver']);

        $calculationData = [
            'purchase_amount' => 1000.00,
        ];

        $response = $this->postJson('/api/loyalty/calculate-earnings', $calculationData);

        $response->assertOk();
        
        $data = $response->json('data');
        
        // Silver tier has 1.2x multiplier
        $this->assertEquals(1.2, $data['tier_multiplier']);
        $this->assertEquals(50.00, $data['base_credits']); // 5% of 1000
        $this->assertEquals(60.00, $data['tier_credits']); // 50 * 1.2
    }
}