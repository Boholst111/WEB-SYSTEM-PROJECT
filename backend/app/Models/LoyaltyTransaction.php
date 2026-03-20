<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'expires_at' => 'datetime',
        'is_expired' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Transaction type constants.
     */
    const TYPE_EARNED = 'earned';
    const TYPE_REDEEMED = 'redeemed';
    const TYPE_EXPIRED = 'expired';
    const TYPE_BONUS = 'bonus';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_REFUND = 'refund';
    const TYPE_TIER_BONUS = 'tier_bonus';

    /**
     * Get the user that owns the loyalty transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order that generated the loyalty transaction.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the pre-order that generated the loyalty transaction.
     */
    public function preorder(): BelongsTo
    {
        return $this->belongsTo(PreOrder::class);
    }

    /**
     * Scope for transactions by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for transactions by type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope for earned credits.
     */
    public function scopeEarned(Builder $query): Builder
    {
        return $query->where('transaction_type', self::TYPE_EARNED);
    }

    /**
     * Scope for redeemed credits.
     */
    public function scopeRedeemed(Builder $query): Builder
    {
        return $query->where('transaction_type', self::TYPE_REDEEMED);
    }

    /**
     * Scope for expired credits.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('transaction_type', self::TYPE_EXPIRED)
                    ->orWhere('is_expired', true);
    }

    /**
     * Scope for non-expired credits.
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('is_expired', false)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope for credits expiring soon.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('transaction_type', self::TYPE_EARNED)
                    ->where('is_expired', false)
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    /**
     * Scope for transactions within date range.
     */
    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Create earned credits transaction.
     */
    public static function createEarned(
        int $userId,
        float $amount,
        string $description,
        ?int $orderId = null,
        ?int $preorderId = null,
        ?string $expiresAt = null
    ): self {
        $user = User::find($userId);
        $balanceBefore = $user->loyalty_credits;
        $balanceAfter = $balanceBefore + $amount;

        $transaction = self::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'preorder_id' => $preorderId,
            'transaction_type' => self::TYPE_EARNED,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'expires_at' => $expiresAt ?: now()->addYear(),
        ]);

        // Update user's loyalty credits
        $user->loyalty_credits = $balanceAfter;
        $user->save();

        return $transaction;
    }

    /**
     * Create redeemed credits transaction.
     */
    public static function createRedeemed(
        int $userId,
        float $amount,
        string $description,
        ?int $orderId = null,
        ?string $referenceId = null
    ): self {
        $user = User::find($userId);
        $balanceBefore = $user->loyalty_credits;
        $balanceAfter = $balanceBefore - $amount;

        $transaction = self::create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'transaction_type' => self::TYPE_REDEEMED,
            'amount' => -$amount, // Negative for redemption
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'reference_id' => $referenceId,
        ]);

        // Update user's loyalty credits
        $user->loyalty_credits = $balanceAfter;
        $user->save();

        return $transaction;
    }

    /**
     * Create bonus credits transaction.
     */
    public static function createBonus(
        int $userId,
        float $amount,
        string $description,
        ?string $referenceId = null,
        ?array $metadata = null
    ): self {
        $user = User::find($userId);
        $balanceBefore = $user->loyalty_credits;
        $balanceAfter = $balanceBefore + $amount;

        $transaction = self::create([
            'user_id' => $userId,
            'transaction_type' => self::TYPE_BONUS,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'reference_id' => $referenceId,
            'metadata' => $metadata,
            'expires_at' => now()->addYear(),
        ]);

        // Update user's loyalty credits
        $user->loyalty_credits = $balanceAfter;
        $user->save();

        return $transaction;
    }

    /**
     * Create adjustment transaction.
     */
    public static function createAdjustment(
        int $userId,
        float $amount,
        string $description,
        ?string $referenceId = null
    ): self {
        $user = User::find($userId);
        $balanceBefore = $user->loyalty_credits;
        $balanceAfter = $balanceBefore + $amount;

        $transaction = self::create([
            'user_id' => $userId,
            'transaction_type' => self::TYPE_ADJUSTMENT,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'reference_id' => $referenceId,
        ]);

        // Update user's loyalty credits
        $user->loyalty_credits = max(0, $balanceAfter); // Ensure balance doesn't go negative
        $user->save();

        return $transaction;
    }

    /**
     * Expire credits transaction.
     */
    public function expireCredits(): bool
    {
        if ($this->transaction_type !== self::TYPE_EARNED || $this->is_expired) {
            return false;
        }

        $this->is_expired = true;
        $this->save();

        // Create expiration transaction
        $user = User::find($this->user_id); // Use find instead of relationship to avoid lazy loading
        $balanceBefore = $user->loyalty_credits;
        $balanceAfter = $balanceBefore - $this->amount;

        self::create([
            'user_id' => $this->user_id,
            'transaction_type' => self::TYPE_EXPIRED,
            'amount' => -$this->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => "Credits expired from transaction #{$this->id}",
            'reference_id' => (string) $this->id,
        ]);

        // Update user's loyalty credits
        $user->loyalty_credits = max(0, $balanceAfter);
        $user->save();

        return true;
    }

    /**
     * Get transaction type label.
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            self::TYPE_EARNED => 'Earned',
            self::TYPE_REDEEMED => 'Redeemed',
            self::TYPE_EXPIRED => 'Expired',
            self::TYPE_BONUS => 'Bonus',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_REFUND => 'Refund',
            self::TYPE_TIER_BONUS => 'Tier Bonus',
        ];

        return $labels[$this->transaction_type] ?? 'Unknown';
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->amount >= 0 ? '+' : '';
        return $prefix . number_format($this->amount, 2);
    }

    /**
     * Check if transaction is a credit (positive amount).
     */
    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if transaction is a debit (negative amount).
     */
    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Check if credits are expired or will expire soon.
     */
    public function isExpiredOrExpiring(int $days = 30): bool
    {
        if ($this->is_expired) {
            return true;
        }

        if ($this->expires_at && $this->expires_at->lte(now()->addDays($days))) {
            return true;
        }

        return false;
    }

    /**
     * Get days until expiration.
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expires_at || $this->is_expired) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Static method to expire old credits.
     */
    public static function expireOldCredits(): int
    {
        $expiredCount = 0;
        
        $expiredTransactions = self::where('transaction_type', self::TYPE_EARNED)
            ->where('is_expired', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredTransactions as $transaction) {
            if ($transaction->expireCredits()) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * Calculate user's available credits balance.
     */
    public static function calculateAvailableBalance(int $userId): float
    {
        $earned = self::where('user_id', $userId)
            ->where('transaction_type', self::TYPE_EARNED)
            ->where('is_expired', false)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->sum('amount');

        $redeemed = self::where('user_id', $userId)
            ->where('transaction_type', self::TYPE_REDEEMED)
            ->sum('amount');

        return $earned + $redeemed; // redeemed amounts are negative
    }

    /**
     * Validate ledger integrity for a user.
     */
    public static function validateLedgerIntegrity(int $userId): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        $calculatedBalance = self::calculateAvailableBalance($userId);
        $userBalance = $user->loyalty_credits;

        // Allow for small floating point differences
        return abs($calculatedBalance - $userBalance) < 0.01;
    }
}