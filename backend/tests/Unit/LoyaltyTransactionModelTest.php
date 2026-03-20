<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Models\Order;
use App\Models\PreOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoyaltyTransactionModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'order_id',
            'preorder_id',
            'transaction_type',
            'amount',
            'balance_before',
            'balance_after',
            'description',
            'reference_id',
            'expires_at',
            'is_expired',
            'metadata',
        ];

        $transaction = new LoyaltyTransaction();
        $this->assertEquals($fillable, $transaction->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $transaction = LoyaltyTransaction::factory()->create([
            'amount' => '123.45',
            'balance_before' => '500.00',
            'balance_after' => '623.45',
            'expires_at' => '2024-12-31 23:59:59',
            'is_expired' => true,
            'metadata' => ['source' => 'bonus', 'campaign' => 'new_year'],
        ]);

        $this->assertEquals(123.45, $transaction->amount);
        $this->assertEquals(500.00, $transaction->balance_before);
        $this->assertEquals(623.45, $transaction->balance_after);
        $this->assertInstanceOf(\Carbon\Carbon::class, $transaction->expires_at);
        $this->assertTrue($transaction->is_expired);
        $this->assertIsArray($transaction->metadata);
        $this->assertEquals('bonus', $transaction->metadata['source']);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $transaction = LoyaltyTransaction::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $transaction->user);
        $this->assertEquals($user->id, $transaction->user->id);
    }

    /** @test */
    public function it_belongs_to_order()
    {
        $order = Order::factory()->create();
        $transaction = LoyaltyTransaction::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(Order::class, $transaction->order);
        $this->assertEquals($order->id, $transaction->order->id);
    }

    /** @test */
    public function it_belongs_to_preorder()
    {
        $preorder = PreOrder::factory()->create();
        $transaction = LoyaltyTransaction::factory()->create(['preorder_id' => $preorder->id]);

        $this->assertInstanceOf(PreOrder::class, $transaction->preorder);
        $this->assertEquals($preorder->id, $transaction->preorder->id);
    }

    /** @test */
    public function it_scopes_by_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $transaction1 = LoyaltyTransaction::factory()->create(['user_id' => $user1->id]);
        $transaction2 = LoyaltyTransaction::factory()->create(['user_id' => $user2->id]);

        $user1Transactions = LoyaltyTransaction::byUser($user1->id)->get();

        $this->assertTrue($user1Transactions->contains($transaction1));
        $this->assertFalse($user1Transactions->contains($transaction2));
    }

    /** @test */
    public function it_scopes_by_type()
    {
        $earnedTransaction = LoyaltyTransaction::factory()->create(['transaction_type' => LoyaltyTransaction::TYPE_EARNED]);
        $redeemedTransaction = LoyaltyTransaction::factory()->create(['transaction_type' => LoyaltyTransaction::TYPE_REDEEMED]);

        $earnedTransactions = LoyaltyTransaction::byType(LoyaltyTransaction::TYPE_EARNED)->get();

        $this->assertTrue($earnedTransactions->contains($earnedTransaction));
        $this->assertFalse($earnedTransactions->contains($redeemedTransaction));
    }

    /** @test */
    public function it_scopes_earned_transactions()
    {
        $earnedTransaction = LoyaltyTransaction::factory()->create(['transaction_type' => LoyaltyTransaction::TYPE_EARNED]);
        $redeemedTransaction = LoyaltyTransaction::factory()->create(['transaction_type' => LoyaltyTransaction::TYPE_REDEEMED]);

        $earnedTransactions = LoyaltyTransaction::earned()->get();

        $this->assertTrue($earnedTransactions->contains($earnedTransaction));
        $this->assertFalse($earnedTransactions->contains($redeemedTransaction));
    }

    /** @test */
    public function it_scopes_redeemed_transactions()
    {
        $earnedTransaction = LoyaltyTransaction::factory()->create(['transaction_type' => LoyaltyTransaction::TYPE_EARNED]);
        $redeemedTransaction = LoyaltyTransaction::factory()->create(['transaction_type' => LoyaltyTransaction::TYPE_REDEEMED]);

        $redeemedTransactions = LoyaltyTransaction::redeemed()->get();

        $this->assertFalse($redeemedTransactions->contains($earnedTransaction));
        $this->assertTrue($redeemedTransactions->contains($redeemedTransaction));
    }

    /** @test */
    public function it_scopes_expired_transactions()
    {
        $expiredTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_EXPIRED,
        ]);
        $expiredByFlagTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'is_expired' => true,
        ]);
        $activeTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'is_expired' => false,
        ]);

        $expiredTransactions = LoyaltyTransaction::expired()->get();

        $this->assertTrue($expiredTransactions->contains($expiredTransaction));
        $this->assertTrue($expiredTransactions->contains($expiredByFlagTransaction));
        $this->assertFalse($expiredTransactions->contains($activeTransaction));
    }

    /** @test */
    public function it_scopes_not_expired_transactions()
    {
        $activeTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);
        $expiredTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'is_expired' => true,
        ]);
        $expiredByDateTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'is_expired' => false,
            'expires_at' => now()->subDay(),
        ]);

        $notExpiredTransactions = LoyaltyTransaction::notExpired()->get();

        $this->assertTrue($notExpiredTransactions->contains($activeTransaction));
        $this->assertFalse($notExpiredTransactions->contains($expiredTransaction));
        $this->assertFalse($notExpiredTransactions->contains($expiredByDateTransaction));
    }

    /** @test */
    public function it_scopes_expiring_soon_transactions()
    {
        $expiringSoonTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'is_expired' => false,
            'expires_at' => now()->addDays(15),
        ]);
        $notExpiringSoonTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'is_expired' => false,
            'expires_at' => now()->addDays(45),
        ]);

        $expiringSoonTransactions = LoyaltyTransaction::expiringSoon(30)->get();

        $this->assertTrue($expiringSoonTransactions->contains($expiringSoonTransaction));
        $this->assertFalse($expiringSoonTransactions->contains($notExpiringSoonTransaction));
    }

    /** @test */
    public function it_scopes_by_date_range()
    {
        $oldTransaction = LoyaltyTransaction::factory()->create(['created_at' => '2024-01-01']);
        $recentTransaction = LoyaltyTransaction::factory()->create(['created_at' => '2024-01-15']);

        $recentTransactions = LoyaltyTransaction::byDateRange('2024-01-10', '2024-01-20')->get();

        $this->assertFalse($recentTransactions->contains($oldTransaction));
        $this->assertTrue($recentTransactions->contains($recentTransaction));
    }

    /** @test */
    public function it_creates_earned_credits_transaction()
    {
        $user = User::factory()->create(['loyalty_credits' => 100.00]);
        $order = Order::factory()->create();

        $transaction = LoyaltyTransaction::createEarned(
            $user->id,
            50.00,
            'Test earned credits',
            $order->id,
            null,
            now()->addYear()->toDateTimeString()
        );

        $this->assertEquals($user->id, $transaction->user_id);
        $this->assertEquals($order->id, $transaction->order_id);
        $this->assertEquals(LoyaltyTransaction::TYPE_EARNED, $transaction->transaction_type);
        $this->assertEquals(50.00, $transaction->amount);
        $this->assertEquals(100.00, $transaction->balance_before);
        $this->assertEquals(150.00, $transaction->balance_after);
        $this->assertEquals('Test earned credits', $transaction->description);

        // Check user's balance was updated
        $user->refresh();
        $this->assertEquals(150.00, $user->loyalty_credits);
    }

    /** @test */
    public function it_creates_redeemed_credits_transaction()
    {
        $user = User::factory()->create(['loyalty_credits' => 100.00]);
        $order = Order::factory()->create();

        $transaction = LoyaltyTransaction::createRedeemed(
            $user->id,
            30.00,
            'Test redeemed credits',
            $order->id,
            'REF123'
        );

        $this->assertEquals($user->id, $transaction->user_id);
        $this->assertEquals($order->id, $transaction->order_id);
        $this->assertEquals(LoyaltyTransaction::TYPE_REDEEMED, $transaction->transaction_type);
        $this->assertEquals(-30.00, $transaction->amount); // Negative for redemption
        $this->assertEquals(100.00, $transaction->balance_before);
        $this->assertEquals(70.00, $transaction->balance_after);
        $this->assertEquals('Test redeemed credits', $transaction->description);
        $this->assertEquals('REF123', $transaction->reference_id);

        // Check user's balance was updated
        $user->refresh();
        $this->assertEquals(70.00, $user->loyalty_credits);
    }

    /** @test */
    public function it_creates_bonus_credits_transaction()
    {
        $user = User::factory()->create(['loyalty_credits' => 100.00]);

        $transaction = LoyaltyTransaction::createBonus(
            $user->id,
            25.00,
            'Test bonus credits',
            'BONUS123',
            ['campaign' => 'new_year']
        );

        $this->assertEquals($user->id, $transaction->user_id);
        $this->assertEquals(LoyaltyTransaction::TYPE_BONUS, $transaction->transaction_type);
        $this->assertEquals(25.00, $transaction->amount);
        $this->assertEquals(100.00, $transaction->balance_before);
        $this->assertEquals(125.00, $transaction->balance_after);
        $this->assertEquals('Test bonus credits', $transaction->description);
        $this->assertEquals('BONUS123', $transaction->reference_id);
        $this->assertEquals(['campaign' => 'new_year'], $transaction->metadata);

        // Check user's balance was updated
        $user->refresh();
        $this->assertEquals(125.00, $user->loyalty_credits);
    }

    /** @test */
    public function it_creates_adjustment_transaction()
    {
        $user = User::factory()->create(['loyalty_credits' => 100.00]);

        $transaction = LoyaltyTransaction::createAdjustment(
            $user->id,
            -20.00,
            'Test adjustment',
            'ADJ123'
        );

        $this->assertEquals($user->id, $transaction->user_id);
        $this->assertEquals(LoyaltyTransaction::TYPE_ADJUSTMENT, $transaction->transaction_type);
        $this->assertEquals(-20.00, $transaction->amount);
        $this->assertEquals(100.00, $transaction->balance_before);
        $this->assertEquals(80.00, $transaction->balance_after);
        $this->assertEquals('Test adjustment', $transaction->description);
        $this->assertEquals('ADJ123', $transaction->reference_id);

        // Check user's balance was updated
        $user->refresh();
        $this->assertEquals(80.00, $user->loyalty_credits);
    }

    /** @test */
    public function it_prevents_negative_balance_in_adjustments()
    {
        $user = User::factory()->create(['loyalty_credits' => 50.00]);

        $transaction = LoyaltyTransaction::createAdjustment(
            $user->id,
            -100.00,
            'Large negative adjustment'
        );

        $this->assertEquals(-50.00, $transaction->balance_after); // Shows the calculation
        
        // But user's balance should not go negative
        $user->refresh();
        $this->assertEquals(0.00, $user->loyalty_credits);
    }

    /** @test */
    public function it_expires_credits_transaction()
    {
        $user = User::factory()->create(['loyalty_credits' => 100.00]);
        
        $earnedTransaction = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 50.00,
            'is_expired' => false,
        ]);

        $result = $earnedTransaction->expireCredits();

        $this->assertTrue($result);
        $this->assertTrue($earnedTransaction->is_expired);

        // Check expiration transaction was created
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EXPIRED,
            'amount' => -50.00,
            'reference_id' => (string) $earnedTransaction->id,
        ]);

        // Check user's balance was updated
        $user->refresh();
        $this->assertEquals(50.00, $user->loyalty_credits); // 100 - 50
    }

    /** @test */
    public function it_fails_to_expire_already_expired_credits()
    {
        $expiredTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'is_expired' => true,
        ]);

        $result = $expiredTransaction->expireCredits();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_fails_to_expire_non_earned_credits()
    {
        $redeemedTransaction = LoyaltyTransaction::factory()->create([
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
            'is_expired' => false,
        ]);

        $result = $redeemedTransaction->expireCredits();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_gets_transaction_type_labels()
    {
        $earnedTransaction = LoyaltyTransaction::factory()->create(['transaction_type' => LoyaltyTransaction::TYPE_EARNED]);
        $this->assertEquals('Earned', $earnedTransaction->getTypeLabelAttribute());

        $redeemedTransaction = LoyaltyTransaction::factory()->create(['transaction_type' => LoyaltyTransaction::TYPE_REDEEMED]);
        $this->assertEquals('Redeemed', $redeemedTransaction->getTypeLabelAttribute());

        $bonusTransaction = LoyaltyTransaction::factory()->create(['transaction_type' => LoyaltyTransaction::TYPE_BONUS]);
        $this->assertEquals('Bonus', $bonusTransaction->getTypeLabelAttribute());
    }

    /** @test */
    public function it_gets_formatted_amount()
    {
        $positiveTransaction = LoyaltyTransaction::factory()->create(['amount' => 123.45]);
        $this->assertEquals('+123.45', $positiveTransaction->getFormattedAmountAttribute());

        $negativeTransaction = LoyaltyTransaction::factory()->create(['amount' => -67.89]);
        $this->assertEquals('-67.89', $negativeTransaction->getFormattedAmountAttribute());
    }

    /** @test */
    public function it_checks_if_transaction_is_credit()
    {
        $creditTransaction = LoyaltyTransaction::factory()->create(['amount' => 50.00]);
        $debitTransaction = LoyaltyTransaction::factory()->create(['amount' => -30.00]);

        $this->assertTrue($creditTransaction->isCredit());
        $this->assertFalse($debitTransaction->isCredit());
    }

    /** @test */
    public function it_checks_if_transaction_is_debit()
    {
        $creditTransaction = LoyaltyTransaction::factory()->create(['amount' => 50.00]);
        $debitTransaction = LoyaltyTransaction::factory()->create(['amount' => -30.00]);

        $this->assertFalse($creditTransaction->isDebit());
        $this->assertTrue($debitTransaction->isDebit());
    }

    /** @test */
    public function it_checks_if_credits_are_expired_or_expiring()
    {
        $expiredTransaction = LoyaltyTransaction::factory()->create(['is_expired' => true]);
        $this->assertTrue($expiredTransaction->isExpiredOrExpiring());

        $expiringSoonTransaction = LoyaltyTransaction::factory()->create([
            'is_expired' => false,
            'expires_at' => now()->addDays(15),
        ]);
        $this->assertTrue($expiringSoonTransaction->isExpiredOrExpiring(30));

        $notExpiringTransaction = LoyaltyTransaction::factory()->create([
            'is_expired' => false,
            'expires_at' => now()->addDays(45),
        ]);
        $this->assertFalse($notExpiringTransaction->isExpiredOrExpiring(30));
    }

    /** @test */
    public function it_calculates_days_until_expiration()
    {
        $expirationDate = now()->addDays(10);
        $transaction = LoyaltyTransaction::factory()->create([
            'expires_at' => $expirationDate,
            'is_expired' => false,
        ]);
        
        // Use the actual expiration date to calculate expected days
        $expectedDays = now()->diffInDays($expirationDate, false);
        $this->assertEquals($expectedDays, $transaction->getDaysUntilExpirationAttribute());

        $expiredTransaction = LoyaltyTransaction::factory()->create(['is_expired' => true]);
        $this->assertNull($expiredTransaction->getDaysUntilExpirationAttribute());

        $noExpirationTransaction = LoyaltyTransaction::factory()->create(['expires_at' => null]);
        $this->assertNull($noExpirationTransaction->getDaysUntilExpirationAttribute());
    }

    /** @test */
    public function it_expires_old_credits_in_batch()
    {
        $user = User::factory()->create(['loyalty_credits' => 200.00]);

        // Create expired transactions
        $expiredTransaction1 = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 50.00,
            'is_expired' => false,
            'expires_at' => now()->subDay(),
        ]);

        $expiredTransaction2 = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 30.00,
            'is_expired' => false,
            'expires_at' => now()->subDays(2),
        ]);

        // Create non-expired transaction
        $activeTransaction = LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 40.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        $expiredCount = LoyaltyTransaction::expireOldCredits();

        $this->assertEquals(2, $expiredCount);

        // Check transactions were marked as expired
        $expiredTransaction1->refresh();
        $expiredTransaction2->refresh();
        $activeTransaction->refresh();

        $this->assertTrue($expiredTransaction1->is_expired);
        $this->assertTrue($expiredTransaction2->is_expired);
        $this->assertFalse($activeTransaction->is_expired);
    }

    /** @test */
    public function it_calculates_available_balance()
    {
        $user = User::factory()->create();

        // Create earned credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 100.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 50.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        // Create redeemed credits (negative amount)
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
            'amount' => -30.00,
        ]);

        // Create expired credits (should not count)
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 25.00,
            'is_expired' => true,
        ]);

        $availableBalance = LoyaltyTransaction::calculateAvailableBalance($user->id);
        
        $this->assertEquals(120.00, $availableBalance); // 100 + 50 - 30
    }

    /** @test */
    public function it_validates_ledger_integrity()
    {
        $user = User::factory()->create(['loyalty_credits' => 120.00]);

        // Create transactions that should sum to 120.00
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 100.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 50.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
            'amount' => -30.00,
        ]);

        $isValid = LoyaltyTransaction::validateLedgerIntegrity($user->id);
        
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_detects_ledger_integrity_issues()
    {
        $user = User::factory()->create(['loyalty_credits' => 200.00]); // Incorrect balance

        // Create transactions that should sum to 120.00
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'amount' => 150.00,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
            'amount' => -30.00,
        ]);

        $isValid = LoyaltyTransaction::validateLedgerIntegrity($user->id);
        
        $this->assertFalse($isValid); // 200 != 120
    }
}