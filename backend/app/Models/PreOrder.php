<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PreOrder extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'preorders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'preorder_number',
        'product_id',
        'user_id',
        'quantity',
        'deposit_amount',
        'remaining_amount',
        'total_amount',
        'deposit_paid_at',
        'full_payment_due_date',
        'status',
        'estimated_arrival_date',
        'actual_arrival_date',
        'payment_method',
        'shipping_address',
        'notes',
        'admin_notes',
        'notification_sent',
        'payment_reminder_sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deposit_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_paid_at' => 'datetime',
        'full_payment_due_date' => 'date',
        'estimated_arrival_date' => 'date',
        'actual_arrival_date' => 'date',
        'shipping_address' => 'array',
        'notification_sent' => 'boolean',
        'payment_reminder_sent_at' => 'datetime',
    ];

    /**
     * Pre-order status constants.
     */
    const STATUS_DEPOSIT_PENDING = 'deposit_pending';
    const STATUS_DEPOSIT_PAID = 'deposit_paid';
    const STATUS_READY_FOR_PAYMENT = 'ready_for_payment';
    const STATUS_PAYMENT_COMPLETED = 'payment_completed';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    /**
     * Get the product that owns the pre-order.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user that owns the pre-order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the loyalty transactions for the pre-order.
     */
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class, 'preorder_id');
    }

    /**
     * Get the payments for the pre-order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'preorder_id');
    }

    /**
     * Scope for pre-orders by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for pre-orders by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for pre-orders by product.
     */
    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope for pre-orders ready for payment.
     */
    public function scopeReadyForPayment(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY_FOR_PAYMENT);
    }

    /**
     * Scope for pre-orders with pending deposits.
     */
    public function scopeDepositPending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DEPOSIT_PENDING);
    }

    /**
     * Scope for pre-orders that have arrived.
     */
    public function scopeArrived(Builder $query): Builder
    {
        return $query->whereNotNull('actual_arrival_date');
    }

    /**
     * Scope for pre-orders due for payment reminder.
     */
    public function scopeDueForReminder(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY_FOR_PAYMENT)
                    ->where('full_payment_due_date', '<=', now()->addDays(7))
                    ->where(function ($q) {
                        $q->whereNull('payment_reminder_sent_at')
                          ->orWhere('payment_reminder_sent_at', '<', now()->subDays(3));
                    });
    }

    /**
     * Generate unique pre-order number.
     */
    public static function generatePreOrderNumber(): string
    {
        $prefix = 'PO'; // Pre-Order
        $timestamp = now()->format('ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return $prefix . $timestamp . $random;
    }

    /**
     * Calculate deposit and remaining amounts.
     */
    public function calculateAmounts(float $depositPercentage = 0.3): void
    {
        $this->total_amount = $this->product->current_price * $this->quantity;
        $this->deposit_amount = $this->total_amount * $depositPercentage;
        $this->remaining_amount = $this->total_amount - $this->deposit_amount;
    }

    /**
     * Process deposit payment.
     */
    public function processDepositPayment(string $paymentMethod): bool
    {
        if ($this->status !== self::STATUS_DEPOSIT_PENDING) {
            return false;
        }

        $this->status = self::STATUS_DEPOSIT_PAID;
        $this->deposit_paid_at = now();
        $this->payment_method = $paymentMethod;
        
        return $this->save();
    }

    /**
     * Mark as ready for final payment.
     */
    public function markReadyForPayment(): bool
    {
        if ($this->status !== self::STATUS_DEPOSIT_PAID) {
            return false;
        }

        $this->status = self::STATUS_READY_FOR_PAYMENT;
        $this->full_payment_due_date = now()->addDays(30); // 30 days to complete payment
        $this->notification_sent = false; // Reset notification flag
        
        return $this->save();
    }

    /**
     * Complete final payment.
     */
    public function completePayment(): bool
    {
        if ($this->status !== self::STATUS_READY_FOR_PAYMENT) {
            return false;
        }

        $this->status = self::STATUS_PAYMENT_COMPLETED;
        
        return $this->save();
    }

    /**
     * Update status with validation.
     */
    public function updateStatus(string $newStatus): bool
    {
        $validTransitions = [
            self::STATUS_DEPOSIT_PENDING => [self::STATUS_DEPOSIT_PAID, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
            self::STATUS_DEPOSIT_PAID => [self::STATUS_READY_FOR_PAYMENT, self::STATUS_CANCELLED],
            self::STATUS_READY_FOR_PAYMENT => [self::STATUS_PAYMENT_COMPLETED, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
            self::STATUS_PAYMENT_COMPLETED => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED => [self::STATUS_DELIVERED],
            self::STATUS_DELIVERED => [],
            self::STATUS_CANCELLED => [],
            self::STATUS_EXPIRED => [],
        ];

        $currentStatus = $this->status;
        
        if (!isset($validTransitions[$currentStatus]) || 
            !in_array($newStatus, $validTransitions[$currentStatus])) {
            return false;
        }

        $this->status = $newStatus;
        
        return $this->save();
    }

    /**
     * Check if pre-order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        // Cannot cancel if already completed, delivered, or cancelled
        if (in_array($this->status, [
            self::STATUS_PAYMENT_COMPLETED,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED
        ])) {
            return false;
        }

        // Cannot cancel if product has arrived and payment is ready
        if ($this->status === self::STATUS_READY_FOR_PAYMENT && $this->actual_arrival_date) {
            return false;
        }

        // Can cancel in other states
        return in_array($this->status, [
            self::STATUS_DEPOSIT_PENDING,
            self::STATUS_DEPOSIT_PAID,
            self::STATUS_READY_FOR_PAYMENT, // Only if not arrived yet
        ]);
    }

    /**
     * Check if deposit is paid.
     */
    public function isDepositPaid(): bool
    {
        return !is_null($this->deposit_paid_at);
    }

    /**
     * Check if final payment is completed.
     */
    public function isPaymentCompleted(): bool
    {
        return $this->status === self::STATUS_PAYMENT_COMPLETED;
    }

    /**
     * Check if pre-order is overdue for payment.
     */
    public function isPaymentOverdue(): bool
    {
        return $this->status === self::STATUS_READY_FOR_PAYMENT &&
               $this->full_payment_due_date &&
               $this->full_payment_due_date->isPast();
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_DEPOSIT_PENDING => 'Deposit Pending',
            self::STATUS_DEPOSIT_PAID => 'Deposit Paid',
            self::STATUS_READY_FOR_PAYMENT => 'Ready for Payment',
            self::STATUS_PAYMENT_COMPLETED => 'Payment Completed',
            self::STATUS_SHIPPED => 'Shipped',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_EXPIRED => 'Expired',
        ];

        return $labels[$this->status] ?? 'Unknown';
    }

    /**
     * Get formatted total amount.
     */
    public function getFormattedTotalAttribute(): string
    {
        return '₱' . number_format($this->total_amount, 2);
    }

    /**
     * Get formatted deposit amount.
     */
    public function getFormattedDepositAttribute(): string
    {
        return '₱' . number_format($this->deposit_amount, 2);
    }

    /**
     * Get formatted remaining amount.
     */
    public function getFormattedRemainingAttribute(): string
    {
        return '₱' . number_format($this->remaining_amount, 2);
    }

    /**
     * Get days until payment due.
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->full_payment_due_date) {
            return null;
        }

        return now()->diffInDays($this->full_payment_due_date, false);
    }

    /**
     * Send arrival notification.
     */
    public function sendArrivalNotification(): bool
    {
        if ($this->status === self::STATUS_DEPOSIT_PAID && !$this->notification_sent) {
            // Mark as ready for payment
            $this->markReadyForPayment();
            
            // TODO: Send email/SMS notification to user
            // This would be implemented in a notification service
            
            $this->notification_sent = true;
            return $this->save();
        }
        
        return false;
    }

    /**
     * Send payment reminder.
     */
    public function sendPaymentReminder(): bool
    {
        if ($this->status === self::STATUS_READY_FOR_PAYMENT) {
            // TODO: Send payment reminder email/SMS
            // This would be implemented in a notification service
            
            $this->payment_reminder_sent_at = now();
            return $this->save();
        }
        
        return false;
    }

    /**
     * Award loyalty credits for completed pre-order.
     */
    public function awardLoyaltyCredits(): void
    {
        // Award credits when delivered and payment was previously completed
        if ($this->status === self::STATUS_DELIVERED && 
            ($this->deposit_paid_at !== null && $this->remaining_amount <= 0)) {
            
            $user = $this->user;
            $loyaltyRate = config('loyalty.credits_rate', 0.05);
            $creditsToAward = $this->total_amount * $loyaltyRate;
            
            // Apply tier multiplier
            $tierBenefits = $user->getLoyaltyBenefits();
            $creditsToAward *= $tierBenefits['credits_multiplier'];

            // Check if credits already awarded to prevent duplicates
            $existingTransaction = LoyaltyTransaction::where('preorder_id', $this->id)
                ->where('transaction_type', 'earned')
                ->first();
                
            if ($existingTransaction) {
                return; // Credits already awarded
            }

            // Create loyalty transaction
            LoyaltyTransaction::create([
                'user_id' => $user->id,
                'preorder_id' => $this->id,
                'transaction_type' => 'earned',
                'amount' => $creditsToAward,
                'balance_before' => $user->loyalty_credits,
                'balance_after' => $user->loyalty_credits + $creditsToAward,
                'description' => "Credits earned from pre-order #{$this->preorder_number}",
                'expires_at' => now()->addYear(), // Credits expire after 1 year
            ]);

            // Update user's loyalty credits and total spent
            $user->loyalty_credits += $creditsToAward;
            $user->total_spent += $this->total_amount;
            $user->save();

            // Update loyalty tier if needed
            $user->updateLoyaltyTier();
        }
    }
}