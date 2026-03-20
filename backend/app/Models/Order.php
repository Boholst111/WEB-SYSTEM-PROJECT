<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'subtotal',
        'credits_used',
        'discount_amount',
        'shipping_fee',
        'tax_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'shipping_address',
        'billing_address',
        'tracking_number',
        'courier_service',
        'shipped_at',
        'delivered_at',
        'notes',
        'admin_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subtotal' => 'decimal:2',
        'credits_used' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Order status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Payment status constants.
     */
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_REFUNDED = 'refunded';
    const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the payment for the order.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Get the loyalty transactions for the order.
     */
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    /**
     * Scope for orders by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for orders by payment status.
     */
    public function scopeByPaymentStatus(Builder $query, string $paymentStatus): Builder
    {
        return $query->where('payment_status', $paymentStatus);
    }

    /**
     * Scope for orders by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for orders within date range.
     */
    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for pending orders.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for confirmed orders.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope for shipped orders.
     */
    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SHIPPED);
    }

    /**
     * Scope for delivered orders.
     */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Generate unique order number.
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'DE'; // Diecast Empire
        $timestamp = now()->format('ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return $prefix . $timestamp . $random;
    }

    /**
     * Calculate order totals.
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum(function ($item) {
            return $item->unit_price * $item->quantity;
        });

        // Apply loyalty credits discount
        $creditsDiscount = min($this->credits_used, $this->subtotal);
        
        // Calculate total before shipping and tax
        $totalBeforeShipping = $this->subtotal - $creditsDiscount - $this->discount_amount;
        
        // Add shipping fee
        $this->total_amount = $totalBeforeShipping + $this->shipping_fee + $this->tax_amount;
        
        // Ensure total is not negative
        $this->total_amount = max(0, $this->total_amount);
    }

    /**
     * Update order status with validation.
     */
    public function updateStatus(string $newStatus): bool
    {
        $validTransitions = [
            self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED => [self::STATUS_DELIVERED],
            self::STATUS_DELIVERED => [self::STATUS_REFUNDED],
            self::STATUS_CANCELLED => [],
            self::STATUS_REFUNDED => [],
        ];

        $currentStatus = $this->status;
        
        if (!isset($validTransitions[$currentStatus]) || 
            !in_array($newStatus, $validTransitions[$currentStatus])) {
            return false;
        }

        $this->status = $newStatus;

        // Set timestamps for specific status changes
        if ($newStatus === self::STATUS_SHIPPED) {
            $this->shipped_at = now();
        } elseif ($newStatus === self::STATUS_DELIVERED) {
            $this->delivered_at = now();
        }

        return $this->save();
    }

    /**
     * Check if order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING
        ]);
    }

    /**
     * Check if order can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_DELIVERED && 
               $this->payment_status === self::PAYMENT_PAID;
    }

    /**
     * Get order total items count.
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * Get formatted total amount.
     */
    public function getFormattedTotalAttribute(): string
    {
        return '₱' . number_format($this->total_amount, 2);
    }

    /**
     * Get order status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_SHIPPED => 'Shipped',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
        ];

        return $labels[$this->status] ?? 'Unknown';
    }

    /**
     * Get payment status label.
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        $labels = [
            self::PAYMENT_PENDING => 'Pending',
            self::PAYMENT_PAID => 'Paid',
            self::PAYMENT_FAILED => 'Failed',
            self::PAYMENT_REFUNDED => 'Refunded',
            self::PAYMENT_PARTIALLY_REFUNDED => 'Partially Refunded',
        ];

        return $labels[$this->payment_status] ?? 'Unknown';
    }

    /**
     * Check if order is paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    /**
     * Check if order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Get estimated delivery date.
     */
    public function getEstimatedDeliveryAttribute(): ?string
    {
        if ($this->shipped_at) {
            return $this->shipped_at->addDays(3)->format('Y-m-d'); // 3 days standard delivery
        }
        
        if ($this->status === self::STATUS_PROCESSING) {
            return now()->addDays(5)->format('Y-m-d'); // 5 days if still processing
        }
        
        return null;
    }

    /**
     * Award loyalty credits for completed order.
     */
    public function awardLoyaltyCredits(): void
    {
        if ($this->status === self::STATUS_DELIVERED && $this->isPaid()) {
            $user = $this->user;
            $loyaltyRate = config('loyalty.credits_rate', 0.05);
            $creditsToAward = $this->subtotal * $loyaltyRate;
            
            // Apply tier multiplier
            $tierBenefits = $user->getLoyaltyBenefits();
            $creditsToAward *= $tierBenefits['credits_multiplier'];

            // Create loyalty transaction
            LoyaltyTransaction::create([
                'user_id' => $user->id,
                'order_id' => $this->id,
                'transaction_type' => 'earned',
                'amount' => $creditsToAward,
                'balance_before' => $user->loyalty_credits,
                'balance_after' => $user->loyalty_credits + $creditsToAward,
                'description' => "Credits earned from order #{$this->order_number}",
                'expires_at' => now()->addYear(), // Credits expire after 1 year
            ]);

            // Update user's loyalty credits and total spent
            $user->loyalty_credits += $creditsToAward;
            $user->total_spent += $this->subtotal;
            $user->save();

            // Update loyalty tier if needed
            $user->updateLoyaltyTier();
        }
    }
}