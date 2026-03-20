<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\PreOrder;
use App\Models\LoyaltyTransaction;
use App\Models\UserAddress;
use App\Models\ShoppingCart;
use App\Models\Wishlist;
use App\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up loyalty tier thresholds for testing
        Config::set('loyalty.tier_thresholds', [
            'platinum' => 100000,
            'gold' => 50000,
            'silver' => 10000,
        ]);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'email',
            'password_hash',
            'first_name',
            'last_name',
            'phone',
            'date_of_birth',
            'loyalty_tier',
            'loyalty_credits',
            'total_spent',
            'email_verified_at',
            'phone_verified_at',
            'status',
            'preferences',
        ];

        $user = new User();
        $this->assertEquals($fillable, $user->getFillable());
    }

    /** @test */
    public function it_has_correct_hidden_attributes()
    {
        $hidden = ['password_hash', 'remember_token'];
        $user = new User();
        $this->assertEquals($hidden, $user->getHidden());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $user = User::factory()->create([
            'email_verified_at' => '2024-01-01 12:00:00',
            'phone_verified_at' => '2024-01-01 12:00:00',
            'date_of_birth' => '1990-01-01',
            'loyalty_credits' => '123.45',
            'total_spent' => '678.90',
            'preferences' => ['theme' => 'dark', 'notifications' => true],
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $user->email_verified_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->phone_verified_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->date_of_birth);
        $this->assertEquals(123.45, $user->loyalty_credits);
        $this->assertEquals(678.90, $user->total_spent);
        $this->assertIsArray($user->preferences);
        $this->assertEquals('dark', $user->preferences['theme']);
    }

    /** @test */
    public function it_has_orders_relationship()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->orders);
        $this->assertTrue($user->orders->contains($order));
    }

    /** @test */
    public function it_has_preorders_relationship()
    {
        $user = User::factory()->create();
        $preorder = PreOrder::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->preorders);
        $this->assertTrue($user->preorders->contains($preorder));
    }

    /** @test */
    public function it_has_loyalty_transactions_relationship()
    {
        $user = User::factory()->create();
        $transaction = LoyaltyTransaction::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->loyaltyTransactions);
        $this->assertTrue($user->loyaltyTransactions->contains($transaction));
    }

    /** @test */
    public function it_has_addresses_relationship()
    {
        $user = User::factory()->create();
        $address = UserAddress::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->addresses);
        $this->assertTrue($user->addresses->contains($address));
    }

    /** @test */
    public function it_has_shopping_cart_relationship()
    {
        $user = User::factory()->create();
        $cartItem = ShoppingCart::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->shoppingCart);
        $this->assertTrue($user->shoppingCart->contains($cartItem));
    }

    /** @test */
    public function it_has_wishlist_items_relationship()
    {
        $user = User::factory()->create();
        $wishlistItem = Wishlist::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->wishlistItems);
        $this->assertTrue($user->wishlistItems->contains($wishlistItem));
    }

    /** @test */
    public function it_has_reviews_relationship()
    {
        $user = User::factory()->create();
        $review = ProductReview::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->reviews);
        $this->assertTrue($user->reviews->contains($review));
    }

    /** @test */
    public function it_calculates_loyalty_tier_correctly()
    {
        // Bronze tier (default)
        $user = User::factory()->create(['total_spent' => 5000]);
        $this->assertEquals('bronze', $user->calculateLoyaltyTier());

        // Silver tier
        $user->total_spent = 15000;
        $this->assertEquals('silver', $user->calculateLoyaltyTier());

        // Gold tier
        $user->total_spent = 75000;
        $this->assertEquals('gold', $user->calculateLoyaltyTier());

        // Platinum tier
        $user->total_spent = 150000;
        $this->assertEquals('platinum', $user->calculateLoyaltyTier());
    }

    /** @test */
    public function it_updates_loyalty_tier_when_needed()
    {
        $user = User::factory()->create([
            'total_spent' => 15000,
            'loyalty_tier' => 'bronze'
        ]);

        $result = $user->updateLoyaltyTier();
        
        $this->assertTrue($result);
        $this->assertEquals('silver', $user->loyalty_tier);
    }

    /** @test */
    public function it_does_not_update_loyalty_tier_when_not_needed()
    {
        $user = User::factory()->create([
            'total_spent' => 15000,
            'loyalty_tier' => 'silver'
        ]);

        $result = $user->updateLoyaltyTier();
        
        $this->assertFalse($result);
        $this->assertEquals('silver', $user->loyalty_tier);
    }

    /** @test */
    public function it_gets_available_credits_correctly()
    {
        $user = User::factory()->create();
        
        // Create earned credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => 'earned',
            'amount' => 100,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => 'earned',
            'amount' => 50,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        // Create redeemed credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => 'redeemed',
            'amount' => 30,
        ]);

        $this->assertEquals(120, $user->getAvailableCreditsAttribute()); // 150 earned - 30 redeemed
    }

    /** @test */
    public function it_excludes_expired_credits_from_available_credits()
    {
        $user = User::factory()->create();
        
        // Create non-expired credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => 'earned',
            'amount' => 100,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        // Create expired credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => 'earned',
            'amount' => 50,
            'is_expired' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertEquals(100, $user->getAvailableCreditsAttribute());
    }

    /** @test */
    public function it_gets_redeemed_credits_correctly()
    {
        $user = User::factory()->create();
        
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => 'redeemed',
            'amount' => 25,
        ]);

        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => 'redeemed',
            'amount' => 15,
        ]);

        $this->assertEquals(40, $user->getRedeemedCreditsAttribute());
    }

    /** @test */
    public function it_checks_if_user_can_redeem_credits()
    {
        $user = User::factory()->create();
        
        // Create available credits
        LoyaltyTransaction::factory()->create([
            'user_id' => $user->id,
            'transaction_type' => 'earned',
            'amount' => 100,
            'is_expired' => false,
            'expires_at' => now()->addYear(),
        ]);

        $this->assertTrue($user->canRedeemCredits(50));
        $this->assertTrue($user->canRedeemCredits(100));
        $this->assertFalse($user->canRedeemCredits(150));
    }

    /** @test */
    public function it_gets_loyalty_benefits_for_each_tier()
    {
        // Bronze tier
        $user = User::factory()->create(['loyalty_tier' => 'bronze']);
        $benefits = $user->getLoyaltyBenefits();
        $this->assertEquals(1.0, $benefits['credits_multiplier']);
        $this->assertNull($benefits['free_shipping_threshold']);
        $this->assertFalse($benefits['early_access']);

        // Silver tier
        $user->loyalty_tier = 'silver';
        $benefits = $user->getLoyaltyBenefits();
        $this->assertEquals(1.2, $benefits['credits_multiplier']);
        $this->assertEquals(5000, $benefits['free_shipping_threshold']);
        $this->assertFalse($benefits['early_access']);

        // Gold tier
        $user->loyalty_tier = 'gold';
        $benefits = $user->getLoyaltyBenefits();
        $this->assertEquals(1.5, $benefits['credits_multiplier']);
        $this->assertEquals(3000, $benefits['free_shipping_threshold']);
        $this->assertTrue($benefits['early_access']);

        // Platinum tier
        $user->loyalty_tier = 'platinum';
        $benefits = $user->getLoyaltyBenefits();
        $this->assertEquals(2.0, $benefits['credits_multiplier']);
        $this->assertEquals(0, $benefits['free_shipping_threshold']);
        $this->assertTrue($benefits['early_access']);
    }

    /** @test */
    public function it_scopes_active_users()
    {
        $activeUser = User::factory()->create(['status' => 'active']);
        $inactiveUser = User::factory()->create(['status' => 'inactive']);

        $activeUsers = User::active()->get();

        $this->assertTrue($activeUsers->contains($activeUser));
        $this->assertFalse($activeUsers->contains($inactiveUser));
    }

    /** @test */
    public function it_scopes_users_by_loyalty_tier()
    {
        $goldUser = User::factory()->create(['loyalty_tier' => 'gold']);
        $silverUser = User::factory()->create(['loyalty_tier' => 'silver']);

        $goldUsers = User::byLoyaltyTier('gold')->get();

        $this->assertTrue($goldUsers->contains($goldUser));
        $this->assertFalse($goldUsers->contains($silverUser));
    }

    /** @test */
    public function it_uses_password_hash_for_authentication()
    {
        $user = User::factory()->create(['password_hash' => 'hashed_password']);
        
        $this->assertEquals('hashed_password', $user->getAuthPassword());
    }
}