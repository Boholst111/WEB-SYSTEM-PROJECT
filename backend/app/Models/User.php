<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
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
        'role',
        'preferences',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'date_of_birth' => 'date',
        'loyalty_credits' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'preferences' => 'array',
    ];

    /**
     * Get the password attribute name for authentication.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Get the user's orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the user's pre-orders.
     */
    public function preorders(): HasMany
    {
        return $this->hasMany(PreOrder::class);
    }

    /**
     * Get the user's loyalty transactions.
     */
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    /**
     * Get the user's addresses.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    /**
     * Get the user's shopping cart.
     */
    public function shoppingCart(): HasMany
    {
        return $this->hasMany(ShoppingCart::class);
    }

    /**
     * Get the user's wishlist items.
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the user's product reviews.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Calculate loyalty tier based on total spent.
     */
    public function calculateLoyaltyTier(): string
    {
        $totalSpent = $this->total_spent;

        if ($totalSpent >= config('loyalty.tier_thresholds.platinum', 100000)) {
            return 'platinum';
        } elseif ($totalSpent >= config('loyalty.tier_thresholds.gold', 50000)) {
            return 'gold';
        } elseif ($totalSpent >= config('loyalty.tier_thresholds.silver', 10000)) {
            return 'silver';
        }

        return 'bronze';
    }

    /**
     * Update loyalty tier if needed.
     */
    public function updateLoyaltyTier(): bool
    {
        $newTier = $this->calculateLoyaltyTier();
        
        if ($this->loyalty_tier !== $newTier) {
            $this->loyalty_tier = $newTier;
            return $this->save();
        }

        return false;
    }

    /**
     * Get available loyalty credits (non-expired).
     */
    public function getAvailableCreditsAttribute(): float
    {
        return $this->loyaltyTransactions()
            ->where('transaction_type', 'earned')
            ->where('is_expired', false)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->sum('amount') - $this->getRedeemedCreditsAttribute();
    }

    /**
     * Get total redeemed credits.
     */
    public function getRedeemedCreditsAttribute(): float
    {
        return $this->loyaltyTransactions()
            ->where('transaction_type', 'redeemed')
            ->sum('amount');
    }

    /**
     * Check if user can redeem specified amount of credits.
     */
    public function canRedeemCredits(float $amount): bool
    {
        return $this->getAvailableCreditsAttribute() >= $amount;
    }

    /**
     * Get loyalty tier benefits.
     */
    public function getLoyaltyBenefits(): array
    {
        $benefits = [
            'bronze' => [
                'credits_multiplier' => 1.0,
                'free_shipping_threshold' => null,
                'early_access' => false,
            ],
            'silver' => [
                'credits_multiplier' => 1.2,
                'free_shipping_threshold' => 5000,
                'early_access' => false,
            ],
            'gold' => [
                'credits_multiplier' => 1.5,
                'free_shipping_threshold' => 3000,
                'early_access' => true,
            ],
            'platinum' => [
                'credits_multiplier' => 2.0,
                'free_shipping_threshold' => 0,
                'early_access' => true,
            ],
        ];

        return $benefits[$this->loyalty_tier] ?? $benefits['bronze'];
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for users by loyalty tier.
     */
    public function scopeByLoyaltyTier($query, string $tier)
    {
        return $query->where('loyalty_tier', $tier);
    }
}