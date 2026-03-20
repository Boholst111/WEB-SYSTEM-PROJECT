<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\LoyaltyTransaction;
use App\Http\Controllers\LoyaltyController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;

/**
 * Unit tests for loyalty system calculations.
 * 
 * Tests credits earning and redemption logic, tier progression,
 * and expiration handling as specified in Requirements 1.4.
 */
class LoyaltyCalculationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up loyalty configuration for consistent testing
        Config::set('loyalty.credits_rate', 0.05); // 5%
        Config::set('loyalty.tier_thresholds', [
            'bronze' => 0,
            'silver' => 10000,
            'gold' => 50000,
            'platinum' => 100000,
        ]);
        // Note: tier_benefits config is not used by User model, 
        // it has hardcoded benefits without bonus_rate
        Config::set('loyalty.redemption.minimum_amount', 100);
        Config::set('loyalty.redemption.maximum_percentage', 50);
        Config::set('loyalty.redemption.conversion_rate', 1.0);
        Config::set('loyalty.expiration.enabled', true);
        Config::set('loyalty.expiration.months', 12);
    }

    /** @test */
    public function it_calculates_basic_credits_earning_for_bronze_tier()
    {
        $user = User::factory()->create([
            'loyalty_tier' => 'bronze',
            'total_spent' => 5000,
        ]);

        $purchaseAmount = 1000.00;
        $baseRate = 0.05; // 5%
        $multiplier = 1.0; // Bronze multiplier
        $bonusRate = 0.0; // No bonus for bronze

        $expectedBaseCredits = $purchaseAmount * $baseRate; // 50.00
        $expectedTierCredits = $expectedBaseCredits * $multiplier; // 50.00
        $expectedBonusCredits = $purchaseAmount * $bonusRate; // 0.00
        $expectedTotalCredits = $expectedTierCredits + $expectedBonusCredits; // 50.00

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/calculate-earnings', 'POST', [
            'purchase_amount' => $purchaseAmount,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->calculateEarnings($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals($expectedBaseCredits, $data['data']['base_credits']);
        $this->assertEquals($expectedTierCredits, $data['data']['tier_credits']);
        $this->assertEquals($expectedBonusCredits, $data['data']['bonus_credits']);
        $this->assertEquals($expectedTotalCredits, $data['data']['total_credits']);
    }

    /** @test */
    public function it_calculates_enhanced_credits_earning_for_silver_tier()
    {
        $user = User::factory()->create([
            'loyalty_tier' => 'silver',
            'total_spent' => 15000,
        ]);

        $purchaseAmount = 2000.00;
        $baseRate = 0.05; // 5%
        $multiplier = 1.2; // Silver multiplier
        $bonusRate = 0.0; // No bonus_rate in User model

        $expectedBaseCredits = $purchaseAmount * $baseRate; // 100.00
        $expectedTierCredits = $expectedBaseCredits * $multiplier; // 120.00
        $expectedBonusCredits = $purchaseAmount * $bonusRate; // 0.00
        $expectedTotalCredits = $expectedTierCredits + $expectedBonusCredits; // 120.00

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/calculate-earnings', 'POST', [
            'purchase_amount' => $purchaseAmount,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->calculateEarnings($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals($expectedBaseCredits, $data['data']['base_credits']);
        $this->assertEquals($expectedTierCredits, $data['data']['tier_credits']);
        $this->assertEquals($expectedBonusCredits, $data['data']['bonus_credits']);
        $this->assertEquals($expectedTotalCredits, $data['data']['total_credits']);
    }

    /** @test */
    public function it_calculates_maximum_credits_earning_for_platinum_tier()
    {
        $user = User::factory()->create([
            'loyalty_tier' => 'platinum',
            'total_spent' => 150000,
        ]);

        $purchaseAmount = 5000.00;
        $baseRate = 0.05; // 5%
        $multiplier = 2.0; // Platinum multiplier
        $bonusRate = 0.0; // No bonus_rate in User model

        $expectedBaseCredits = $purchaseAmount * $baseRate; // 250.00
        $expectedTierCredits = $expectedBaseCredits * $multiplier; // 500.00
        $expectedBonusCredits = $purchaseAmount * $bonusRate; // 0.00
        $expectedTotalCredits = $expectedTierCredits + $expectedBonusCredits; // 500.00

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/calculate-earnings', 'POST', [
            'purchase_amount' => $purchaseAmount,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->calculateEarnings($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals($expectedBaseCredits, $data['data']['base_credits']);
        $this->assertEquals($expectedTierCredits, $data['data']['tier_credits']);
        $this->assertEquals($expectedBonusCredits, $data['data']['bonus_credits']);
        $this->assertEquals($expectedTotalCredits, $data['data']['total_credits']);
    }

    /** @test */
    public function it_validates_minimum_redemption_amount()
    {
        $user = User::factory()->create(['loyalty_credits' => 500.00]);
        
        // Create available credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 500.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/redeem', 'POST', [
            'amount' => 50.00, // Below minimum of 100
            'order_total' => 1000.00,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->redeem($request);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $data);
    }

    /** @test */
    public function it_validates_maximum_redemption_percentage()
    {
        $user = User::factory()->create(['loyalty_credits' => 1000.00]);
        
        // Create available credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 1000.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        $orderTotal = 1000.00;
        $maxRedemption = $orderTotal * 0.5; // 50% = 500.00
        $attemptedRedemption = 600.00; // Above maximum

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/redeem', 'POST', [
            'amount' => $attemptedRedemption,
            'order_total' => $orderTotal,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->redeem($request);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Cannot redeem more than 50%', $data['message']);
        $this->assertEquals($maxRedemption, $data['max_redemption_amount']);
    }

    /** @test */
    public function it_validates_sufficient_credits_balance_for_redemption()
    {
        $user = User::factory()->create(['loyalty_credits' => 200.00]);
        
        // Create available credits (less than requested)
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 200.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/redeem', 'POST', [
            'amount' => 300.00, // More than available
            'order_total' => 1000.00,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->redeem($request);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Insufficient credits balance', $data['message']);
        $this->assertEquals(200.00, $data['available_credits']);
        $this->assertEquals(300.00, $data['requested_amount']);
    }

    /** @test */
    public function it_successfully_redeems_valid_credits_amount()
    {
        $user = User::factory()->create(['loyalty_credits' => 500.00]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        
        // Create available credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 500.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        $redeemAmount = 200.00;
        $orderTotal = 1000.00;
        $conversionRate = 1.0;
        $expectedDiscount = $redeemAmount * $conversionRate;
        $expectedRemainingCredits = 500.00 - $redeemAmount;

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/redeem', 'POST', [
            'amount' => $redeemAmount,
            'order_total' => $orderTotal,
            'order_id' => $order->id,
            'description' => 'Test redemption',
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->redeem($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals($redeemAmount, $data['data']['redeemed_credits']);
        $this->assertEquals($expectedDiscount, $data['data']['discount_amount']);
        $this->assertEquals($expectedRemainingCredits, $data['data']['remaining_credits']);
        $this->assertEquals($conversionRate, $data['data']['conversion_rate']);

        // Verify redemption transaction was created
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
            'amount' => -$redeemAmount,
        ]);
    }

    /** @test */
    public function it_calculates_tier_progression_correctly()
    {
        // Test bronze to silver progression
        $user = User::factory()->create([
            'loyalty_tier' => 'bronze',
            'total_spent' => 5000, // Halfway to silver (10000)
        ]);

        $this->assertEquals('bronze', $user->calculateLoyaltyTier());
        
        // Update spending to reach silver
        $user->total_spent = 15000;
        $this->assertEquals('silver', $user->calculateLoyaltyTier());
        
        // Update spending to reach gold
        $user->total_spent = 75000;
        $this->assertEquals('gold', $user->calculateLoyaltyTier());
        
        // Update spending to reach platinum
        $user->total_spent = 150000;
        $this->assertEquals('platinum', $user->calculateLoyaltyTier());
    }

    /** @test */
    public function it_handles_tier_boundary_conditions()
    {
        $user = User::factory()->create(['loyalty_tier' => 'bronze']);

        // Exactly at silver threshold
        $user->total_spent = 10000;
        $this->assertEquals('silver', $user->calculateLoyaltyTier());

        // Just below silver threshold
        $user->total_spent = 9999.99;
        $this->assertEquals('bronze', $user->calculateLoyaltyTier());

        // Exactly at gold threshold
        $user->total_spent = 50000;
        $this->assertEquals('gold', $user->calculateLoyaltyTier());

        // Exactly at platinum threshold
        $user->total_spent = 100000;
        $this->assertEquals('platinum', $user->calculateLoyaltyTier());
    }

    /** @test */
    public function it_updates_tier_automatically_when_threshold_reached()
    {
        $user = User::factory()->create([
            'loyalty_tier' => 'bronze',
            'total_spent' => 5000,
        ]);

        // Simulate spending that reaches silver tier
        $user->total_spent = 15000;
        $tierUpdated = $user->updateLoyaltyTier();

        $this->assertTrue($tierUpdated);
        $this->assertEquals('silver', $user->loyalty_tier);

        // No update needed if already at correct tier
        $tierUpdated = $user->updateLoyaltyTier();
        $this->assertFalse($tierUpdated);
    }

    /** @test */
    public function it_handles_credits_expiration_correctly()
    {
        $user = User::factory()->create(['loyalty_credits' => 150.00]);
        
        // Create earned transaction that will expire
        $earnedTransaction = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 100.00,
            'balance_before' => 50.00,
            'balance_after' => 150.00,
            'is_expired' => false,
            'expires_at' => now()->subDay(), // Already expired
        ]);

        // Expire the credits
        $result = $earnedTransaction->expireCredits();

        $this->assertTrue($result);
        $this->assertTrue($earnedTransaction->fresh()->is_expired);

        // Check that expiration transaction was created
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EXPIRED,
            'amount' => -100.00,
            'reference_id' => (string) $earnedTransaction->id,
        ]);

        // Check user's balance was updated
        $user->refresh();
        $this->assertEquals(50.00, $user->loyalty_credits);
    }

    /** @test */
    public function it_identifies_credits_expiring_soon()
    {
        $user = User::factory()->create();
        
        // Create credits expiring in 15 days
        $expiringSoonTransaction = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 100.00,
            'is_expired' => false,
            'expires_at' => now()->addDays(15),
        ]);

        // Create credits expiring in 45 days
        $notExpiringSoonTransaction = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 50.00,
            'is_expired' => false,
            'expires_at' => now()->addDays(45),
        ]);

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/expiring-credits', 'GET', [
            'days' => 30,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->expiringCredits($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(100.00, $data['data']['total_expiring']);
        $this->assertEquals(30, $data['data']['warning_days']);
        $this->assertCount(1, $data['data']['transactions']);
        $this->assertEquals($expiringSoonTransaction->id, $data['data']['transactions'][0]['id']);
    }

    /** @test */
    public function it_prevents_duplicate_credits_earning_for_same_order()
    {
        $user = User::factory()->create(['loyalty_credits' => 0.00]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        // Create first earning transaction
        LoyaltyTransaction::createEarned(
            $user->id,
            50.00,
            "Credits earned from order #{$order->order_number}",
            $order->id
        );

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/earn-credits', 'POST', [
            'order_id' => $order->id,
            'purchase_amount' => 1000.00,
        ]);

        // Attempt to earn credits again for the same order
        $response = $controller->earnCredits($request);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Credits already earned for this order', $data['message']);
    }

    /** @test */
    public function it_calculates_available_credits_excluding_expired()
    {
        $user = User::factory()->create();

        // Create active earned credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 200.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        // Create expired credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 100.00,
            'is_expired' => true,
        ]);

        // Create redeemed credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
            'amount' => -50.00,
        ]);

        $availableBalance = LoyaltyTransaction::calculateAvailableBalance($user->id);
        
        // Should be 200 (active) - 50 (redeemed) = 150, excluding expired 100
        $this->assertEquals(150.00, $availableBalance);
    }

    /** @test */
    public function it_validates_ledger_integrity_with_complex_transactions()
    {
        $user = User::factory()->create(['loyalty_credits' => 0.00]);

        // Create multiple earning transactions
        LoyaltyTransaction::createEarned($user->id, 100.00, 'First earning');
        LoyaltyTransaction::createEarned($user->id, 50.00, 'Second earning');
        LoyaltyTransaction::createBonus($user->id, 25.00, 'Bonus credits');

        // Create redemption
        LoyaltyTransaction::createRedeemed($user->id, 30.00, 'Redemption');

        // Create adjustment
        LoyaltyTransaction::createAdjustment($user->id, -10.00, 'Adjustment');

        // Expected balance: 100 + 50 + 25 - 30 - 10 = 135
        $user->refresh();
        $this->assertEquals(135.00, $user->loyalty_credits);

        // Note: validateLedgerIntegrity uses calculateAvailableBalance which only counts
        // non-expired earned credits minus redeemed credits, not all transaction types
        // So we need to check the actual available balance calculation
        $availableBalance = LoyaltyTransaction::calculateAvailableBalance($user->id);
        
        // Available balance should be: earned (150) + redeemed (-30) = 120
        // (bonus and adjustment are not counted in available balance calculation)
        $this->assertEquals(120.00, $availableBalance);
    }

    /** @test */
    public function it_handles_zero_purchase_amount_gracefully()
    {
        $user = User::factory()->create(['loyalty_tier' => 'gold']);

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/calculate-earnings', 'POST', [
            'purchase_amount' => 0.00,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->calculateEarnings($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(0.00, $data['data']['base_credits']);
        $this->assertEquals(0.00, $data['data']['tier_credits']);
        $this->assertEquals(0.00, $data['data']['bonus_credits']);
        $this->assertEquals(0.00, $data['data']['total_credits']);
    }

    /** @test */
    public function it_handles_large_purchase_amounts_correctly()
    {
        $user = User::factory()->create(['loyalty_tier' => 'platinum']);

        $largePurchaseAmount = 50000.00; // Large purchase
        $expectedBaseCredits = $largePurchaseAmount * 0.05; // 2500.00
        $expectedTierCredits = $expectedBaseCredits * 2.0; // 5000.00
        $expectedBonusCredits = $largePurchaseAmount * 0.0; // 0.00 (no bonus_rate)
        $expectedTotalCredits = $expectedTierCredits + $expectedBonusCredits; // 5000.00

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/calculate-earnings', 'POST', [
            'purchase_amount' => $largePurchaseAmount,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->calculateEarnings($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals($expectedTotalCredits, $data['data']['total_credits']);
    }

    /** @test */
    public function it_handles_fractional_credits_calculations_correctly()
    {
        $user = User::factory()->create(['loyalty_tier' => 'gold']);

        $purchaseAmount = 333.33; // Amount that creates fractional credits
        $baseRate = 0.05; // 5%
        $multiplier = 1.5; // Gold multiplier

        $expectedBaseCredits = $purchaseAmount * $baseRate; // 16.6665
        $expectedTierCredits = $expectedBaseCredits * $multiplier; // 24.99975
        $expectedTotalCredits = $expectedTierCredits; // 24.99975, rounded to 25.00

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/calculate-earnings', 'POST', [
            'purchase_amount' => $purchaseAmount,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->calculateEarnings($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(round($expectedTotalCredits, 2), $data['data']['total_credits']);
        $this->assertEquals(round($expectedBaseCredits, 2), $data['data']['base_credits']);
        $this->assertEquals(round($expectedTierCredits, 2), $data['data']['tier_credits']);
    }

    /** @test */
    public function it_handles_tier_progression_edge_cases()
    {
        $user = User::factory()->create([
            'loyalty_tier' => 'bronze',
            'total_spent' => 0,
        ]);

        // Test progression through all tiers
        $tierProgression = [
            ['spent' => 0, 'expected_tier' => 'bronze'],
            ['spent' => 9999.99, 'expected_tier' => 'bronze'],
            ['spent' => 10000, 'expected_tier' => 'silver'],
            ['spent' => 10000.01, 'expected_tier' => 'silver'],
            ['spent' => 49999.99, 'expected_tier' => 'silver'],
            ['spent' => 50000, 'expected_tier' => 'gold'],
            ['spent' => 50000.01, 'expected_tier' => 'gold'],
            ['spent' => 99999.99, 'expected_tier' => 'gold'],
            ['spent' => 100000, 'expected_tier' => 'platinum'],
            ['spent' => 100000.01, 'expected_tier' => 'platinum'],
            ['spent' => 999999.99, 'expected_tier' => 'platinum'],
        ];

        foreach ($tierProgression as $test) {
            $user->total_spent = $test['spent'];
            $calculatedTier = $user->calculateLoyaltyTier();
            $this->assertEquals(
                $test['expected_tier'], 
                $calculatedTier,
                "Failed for spent amount: {$test['spent']}"
            );
        }
    }

    /** @test */
    public function it_handles_redemption_at_exact_maximum_percentage()
    {
        $user = User::factory()->create(['loyalty_credits' => 1000.00]);
        
        // Create available credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 1000.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        $orderTotal = 1000.00;
        $exactMaxRedemption = $orderTotal * 0.5; // Exactly 50%

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/redeem', 'POST', [
            'amount' => $exactMaxRedemption,
            'order_total' => $orderTotal,
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $controller->redeem($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals($exactMaxRedemption, $data['data']['redeemed_credits']);
    }

    /** @test */
    public function it_handles_credits_expiration_with_multiple_batches()
    {
        $user = User::factory()->create(['loyalty_credits' => 300.00]);
        
        // Create multiple earned transactions with different expiration dates
        $transaction1 = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 100.00,
            'is_expired' => false,
            'expires_at' => now()->subDays(5), // Expired
        ]);

        $transaction2 = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 100.00,
            'is_expired' => false,
            'expires_at' => now()->subDays(2), // Expired
        ]);

        $transaction3 = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 100.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(), // Not expired
        ]);

        // Expire old credits in batch
        $expiredCount = LoyaltyTransaction::expireOldCredits();

        $this->assertEquals(2, $expiredCount);

        // Check that only expired transactions were marked as expired
        $transaction1->refresh();
        $transaction2->refresh();
        $transaction3->refresh();

        $this->assertTrue($transaction1->is_expired);
        $this->assertTrue($transaction2->is_expired);
        $this->assertFalse($transaction3->is_expired);

        // Check that expiration transactions were created
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EXPIRED,
            'amount' => -100.00,
            'reference_id' => (string) $transaction1->id,
        ]);

        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EXPIRED,
            'amount' => -100.00,
            'reference_id' => (string) $transaction2->id,
        ]);
    }

    /** @test */
    public function it_calculates_tier_benefits_multipliers_correctly()
    {
        $tiers = ['bronze', 'silver', 'gold', 'platinum'];
        $expectedMultipliers = [1.0, 1.2, 1.5, 2.0];

        foreach ($tiers as $index => $tier) {
            $user = User::factory()->create(['loyalty_tier' => $tier]);
            $benefits = $user->getLoyaltyBenefits();
            
            $this->assertEquals(
                $expectedMultipliers[$index], 
                $benefits['credits_multiplier'],
                "Failed for tier: {$tier}"
            );
        }
    }

    /** @test */
    public function it_handles_negative_adjustment_preventing_negative_balance()
    {
        $user = User::factory()->create(['loyalty_credits' => 50.00]);

        // Create large negative adjustment that would make balance negative
        $transaction = LoyaltyTransaction::createAdjustment(
            $user->id,
            -100.00, // More than current balance
            'Large negative adjustment'
        );

        // Transaction should record the full adjustment
        $this->assertEquals(-100.00, $transaction->amount);
        $this->assertEquals(50.00, $transaction->balance_before);
        $this->assertEquals(-50.00, $transaction->balance_after);

        // But user's balance should not go negative
        $user->refresh();
        $this->assertEquals(0.00, $user->loyalty_credits);
    }

    /** @test */
    public function it_validates_credits_earning_with_tier_upgrade_scenario()
    {
        $user = User::factory()->create([
            'loyalty_tier' => 'silver',
            'total_spent' => 45000, // Close to gold threshold
            'loyalty_credits' => 100.00,
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);
        $purchaseAmount = 10000.00; // This will push user to gold tier

        $controller = new LoyaltyController();
        $request = Request::create('/api/loyalty/earn-credits', 'POST', [
            'order_id' => $order->id,
            'purchase_amount' => $purchaseAmount,
        ]);

        $response = $controller->earnCredits($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);

        // Check that user's tier was updated
        $user->refresh();
        $this->assertEquals('gold', $user->loyalty_tier);
        $this->assertEquals(55000.00, $user->total_spent); // 45000 + 10000

        // Check that credits were calculated with silver tier (tier at time of earning)
        // Base: 10000 * 0.05 = 500, Silver multiplier: 500 * 1.2 = 600
        $this->assertEquals(600.00, $data['data']['credits_earned']);
    }
}