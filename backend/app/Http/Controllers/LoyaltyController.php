<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoyaltyController extends Controller
{
    /**
     * Get user's loyalty credits balance and tier information.
     */
    public function balance(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Calculate available credits (non-expired)
            $availableCredits = LoyaltyTransaction::calculateAvailableBalance($user->id);
            
            // Get credits expiring soon
            $expiringCredits = $user->loyaltyTransactions()
                ->earned()
                ->expiringSoon(config('loyalty.expiration.warning_days', 30))
                ->sum('amount');
            
            // Get tier information
            $tierBenefits = $user->getLoyaltyBenefits();
            $nextTier = $this->getNextTier($user->loyalty_tier);
            $progressToNextTier = $this->calculateTierProgress($user);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'available_credits' => round($availableCredits, 2),
                    'total_earned' => round($user->loyaltyTransactions()->earned()->sum('amount'), 2),
                    'total_redeemed' => round(abs($user->loyaltyTransactions()->redeemed()->sum('amount')), 2),
                    'expiring_soon' => round($expiringCredits, 2),
                    'expiring_days' => config('loyalty.expiration.warning_days', 30),
                    'current_tier' => $user->loyalty_tier,
                    'tier_benefits' => $tierBenefits,
                    'next_tier' => $nextTier,
                    'progress_to_next_tier' => $progressToNextTier,
                    'total_spent' => round($user->total_spent, 2),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching loyalty balance: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch loyalty balance'
            ], 500);
        }
    }

    /**
     * Get user's loyalty transaction history with pagination.
     */
    public function transactions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'per_page' => 'integer|min:1|max:100',
                'type' => 'string|in:earned,redeemed,expired,bonus,adjustment',
                'start_date' => 'date',
                'end_date' => 'date|after_or_equal:start_date',
            ]);

            $query = $user->loyaltyTransactions()
                ->with(['order', 'preorder'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('type')) {
                $query->byType($request->type);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->byDateRange($request->start_date, $request->end_date);
            }

            $transactions = $query->paginate($request->get('per_page', 20));

            // Transform the data
            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->transaction_type,
                    'type_label' => $transaction->type_label,
                    'amount' => $transaction->amount,
                    'formatted_amount' => $transaction->formatted_amount,
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                    'description' => $transaction->description,
                    'reference_id' => $transaction->reference_id,
                    'order_id' => $transaction->order_id,
                    'preorder_id' => $transaction->preorder_id,
                    'expires_at' => $transaction->expires_at?->toISOString(),
                    'days_until_expiration' => $transaction->days_until_expiration,
                    'is_expired' => $transaction->is_expired,
                    'is_credit' => $transaction->isCredit(),
                    'is_debit' => $transaction->isDebit(),
                    'created_at' => $transaction->created_at->toISOString(),
                    'metadata' => $transaction->metadata,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error fetching loyalty transactions: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch transaction history'
            ], 500);
        }
    }

    /**
     * Redeem loyalty credits during checkout.
     */
    public function redeem(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'amount' => 'required|numeric|min:' . config('loyalty.redemption.minimum_amount', 100),
                'order_total' => 'required|numeric|min:0',
                'order_id' => 'nullable|exists:orders,id',
                'description' => 'string|max:255',
            ]);

            $redeemAmount = $request->amount;
            $orderTotal = $request->order_total;
            $maxRedemptionPercentage = config('loyalty.redemption.maximum_percentage', 50);
            $maxRedemptionAmount = ($orderTotal * $maxRedemptionPercentage) / 100;

            // Validate redemption amount
            if ($redeemAmount > $maxRedemptionAmount) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot redeem more than {$maxRedemptionPercentage}% of order total",
                    'max_redemption_amount' => round($maxRedemptionAmount, 2)
                ], 422);
            }

            // Check available balance
            $availableCredits = LoyaltyTransaction::calculateAvailableBalance($user->id);
            if ($redeemAmount > $availableCredits) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient credits balance',
                    'available_credits' => round($availableCredits, 2),
                    'requested_amount' => $redeemAmount
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Create redemption transaction
                $transaction = LoyaltyTransaction::createRedeemed(
                    $user->id,
                    $redeemAmount,
                    $request->get('description', 'Credits redeemed during checkout'),
                    $request->order_id,
                    $request->get('reference_id')
                );

                // Calculate discount amount (1 credit = 1 PHP by default)
                $conversionRate = config('loyalty.redemption.conversion_rate', 1.0);
                $discountAmount = $redeemAmount * $conversionRate;

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'redeemed_credits' => $redeemAmount,
                        'discount_amount' => round($discountAmount, 2),
                        'remaining_credits' => round($availableCredits - $redeemAmount, 2),
                        'conversion_rate' => $conversionRate,
                        'created_at' => $transaction->created_at->toISOString(),
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error redeeming loyalty credits: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'amount' => $request->get('amount'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to redeem credits'
            ], 500);
        }
    }

    /**
     * Get user's loyalty tier status and progression information.
     */
    public function tierStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $currentTier = $user->loyalty_tier;
            $tierBenefits = $user->getLoyaltyBenefits();
            $nextTier = $this->getNextTier($currentTier);
            $progressToNextTier = $this->calculateTierProgress($user);
            
            // Get tier thresholds
            $tierThresholds = config('loyalty.tier_thresholds');
            $tierBenefitsConfig = config('loyalty.tier_benefits');
            
            // Calculate spending needed for next tier
            $spendingToNextTier = null;
            if ($nextTier) {
                $nextTierThreshold = $tierThresholds[$nextTier] ?? 0;
                $spendingToNextTier = max(0, $nextTierThreshold - $user->total_spent);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'current_tier' => $currentTier,
                    'current_tier_benefits' => $tierBenefits,
                    'next_tier' => $nextTier,
                    'next_tier_benefits' => $nextTier ? ($tierBenefitsConfig[$nextTier] ?? null) : null,
                    'total_spent' => round($user->total_spent, 2),
                    'progress_percentage' => round($progressToNextTier, 2),
                    'spending_to_next_tier' => $spendingToNextTier ? round($spendingToNextTier, 2) : null,
                    'tier_thresholds' => $tierThresholds,
                    'tier_history' => $this->getTierHistory($user),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tier status: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch tier status'
            ], 500);
        }
    }

    /**
     * Calculate credits earned from a purchase amount.
     */
    public function calculateEarnings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'purchase_amount' => 'required|numeric|min:0',
            ]);

            $purchaseAmount = $request->purchase_amount;
            $baseRate = config('loyalty.credits_rate', 0.05);
            $tierBenefits = $user->getLoyaltyBenefits();
            $multiplier = $tierBenefits['credits_multiplier'] ?? 1.0;
            $bonusRate = $tierBenefits['bonus_rate'] ?? 0.0;

            // Calculate base credits
            $baseCredits = $purchaseAmount * $baseRate;
            
            // Apply tier multiplier
            $tierCredits = $baseCredits * $multiplier;
            
            // Apply bonus rate
            $bonusCredits = $purchaseAmount * $bonusRate;
            
            // Total credits
            $totalCredits = $tierCredits + $bonusCredits;

            return response()->json([
                'success' => true,
                'data' => [
                    'purchase_amount' => round($purchaseAmount, 2),
                    'base_rate' => $baseRate,
                    'tier_multiplier' => $multiplier,
                    'bonus_rate' => $bonusRate,
                    'base_credits' => round($baseCredits, 2),
                    'tier_credits' => round($tierCredits, 2),
                    'bonus_credits' => round($bonusCredits, 2),
                    'total_credits' => round($totalCredits, 2),
                    'current_tier' => $user->loyalty_tier,
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error calculating earnings: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate earnings'
            ], 500);
        }
    }

    /**
     * Process credits earning from an order.
     */
    public function earnCredits(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_id' => 'required|exists:orders,id',
                'purchase_amount' => 'required|numeric|min:0',
                'description' => 'string|max:255',
            ]);

            $order = Order::findOrFail($request->order_id);
            $user = $order->user;
            
            // Prevent duplicate earning for the same order
            $existingTransaction = LoyaltyTransaction::where('order_id', $order->id)
                ->where('transaction_type', LoyaltyTransaction::TYPE_EARNED)
                ->first();
                
            if ($existingTransaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credits already earned for this order',
                    'existing_transaction_id' => $existingTransaction->id
                ], 422);
            }

            $purchaseAmount = $request->purchase_amount;
            $baseRate = config('loyalty.credits_rate', 0.05);
            $tierBenefits = $user->getLoyaltyBenefits();
            $multiplier = $tierBenefits['credits_multiplier'] ?? 1.0;
            $bonusRate = $tierBenefits['bonus_rate'] ?? 0.0;

            // Calculate total credits
            $baseCredits = $purchaseAmount * $baseRate;
            $tierCredits = $baseCredits * $multiplier;
            $bonusCredits = $purchaseAmount * $bonusRate;
            $totalCredits = $tierCredits + $bonusCredits;

            DB::beginTransaction();

            try {
                // Create earning transaction
                $expirationDate = config('loyalty.expiration.enabled') 
                    ? now()->addMonths(config('loyalty.expiration.months', 12))
                    : null;

                $transaction = LoyaltyTransaction::createEarned(
                    $user->id,
                    $totalCredits,
                    $request->get('description', "Credits earned from order #{$order->order_number}"),
                    $order->id,
                    null,
                    $expirationDate?->toDateTimeString()
                );

                // Update user's total spent and tier if needed
                $user->increment('total_spent', $purchaseAmount);
                $user->updateLoyaltyTier();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'credits_earned' => round($totalCredits, 2),
                        'base_credits' => round($baseCredits, 2),
                        'tier_credits' => round($tierCredits, 2),
                        'bonus_credits' => round($bonusCredits, 2),
                        'expires_at' => $transaction->expires_at?->toISOString(),
                        'new_balance' => round($transaction->balance_after, 2),
                        'current_tier' => $user->fresh()->loyalty_tier,
                        'created_at' => $transaction->created_at->toISOString(),
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error earning credits: ' . $e->getMessage(), [
                'order_id' => $request->get('order_id'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process credits earning'
            ], 500);
        }
    }

    /**
     * Get credits expiring soon for notifications.
     */
    public function expiringCredits(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'days' => 'integer|min:1|max:365',
            ]);

            $days = $request->get('days', config('loyalty.expiration.warning_days', 30));
            
            $expiringTransactions = $user->loyaltyTransactions()
                ->earned()
                ->notExpired()
                ->expiringSoon($days)
                ->orderBy('expires_at', 'asc')
                ->get();

            $totalExpiring = $expiringTransactions->sum('amount');
            
            $groupedByDate = $expiringTransactions->groupBy(function ($transaction) {
                return $transaction->expires_at->format('Y-m-d');
            })->map(function ($transactions, $date) {
                return [
                    'date' => $date,
                    'amount' => $transactions->sum('amount'),
                    'transaction_count' => $transactions->count(),
                    'days_until_expiration' => $transactions->first()->days_until_expiration,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_expiring' => round($totalExpiring, 2),
                    'warning_days' => $days,
                    'expiring_by_date' => $groupedByDate,
                    'transactions' => $expiringTransactions->map(function ($transaction) {
                        return [
                            'id' => $transaction->id,
                            'amount' => $transaction->amount,
                            'expires_at' => $transaction->expires_at->toISOString(),
                            'days_until_expiration' => $transaction->days_until_expiration,
                            'description' => $transaction->description,
                            'created_at' => $transaction->created_at->toISOString(),
                        ];
                    }),
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error fetching expiring credits: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch expiring credits'
            ], 500);
        }
    }

    /**
     * Get the next loyalty tier for a user.
     */
    private function getNextTier(string $currentTier): ?string
    {
        $tiers = ['bronze', 'silver', 'gold', 'platinum'];
        $currentIndex = array_search($currentTier, $tiers);
        
        if ($currentIndex === false || $currentIndex >= count($tiers) - 1) {
            return null;
        }
        
        return $tiers[$currentIndex + 1];
    }

    /**
     * Calculate progress to next tier as percentage.
     */
    private function calculateTierProgress(User $user): float
    {
        $currentTier = $user->loyalty_tier;
        $nextTier = $this->getNextTier($currentTier);
        
        if (!$nextTier) {
            return 100.0; // Already at highest tier
        }
        
        $tierThresholds = config('loyalty.tier_thresholds');
        $currentThreshold = $tierThresholds[$currentTier] ?? 0;
        $nextThreshold = $tierThresholds[$nextTier] ?? 0;
        
        if ($nextThreshold <= $currentThreshold) {
            return 100.0;
        }
        
        $progress = ($user->total_spent - $currentThreshold) / ($nextThreshold - $currentThreshold);
        return max(0, min(100, $progress * 100));
    }

    /**
     * Get tier upgrade history for a user.
     */
    private function getTierHistory(User $user): array
    {
        // This would typically come from a tier_history table
        // For now, return basic info based on current tier
        $tiers = ['bronze', 'silver', 'gold', 'platinum'];
        $currentTierIndex = array_search($user->loyalty_tier, $tiers);
        
        $history = [];
        for ($i = 0; $i <= $currentTierIndex; $i++) {
            $history[] = [
                'tier' => $tiers[$i],
                'achieved_at' => $user->created_at->toISOString(), // Simplified
                'threshold_met' => config("loyalty.tier_thresholds.{$tiers[$i]}", 0),
            ];
        }
        
        return $history;
    }
}