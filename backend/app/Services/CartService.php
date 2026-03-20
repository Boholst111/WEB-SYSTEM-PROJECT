<?php

namespace App\Services;

use App\Models\ShoppingCart;
use App\Models\User;
use Illuminate\Support\Collection;

class CartService
{
    /**
     * Calculate cart totals including loyalty credits and shipping
     */
    public function calculateCartTotals(User $user, ?float $creditsToUse = null, ?string $shippingOption = null): array
    {
        $cartItems = ShoppingCart::with('product')
            ->where('user_id', $user->id)
            ->get();

        $subtotal = $cartItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        // Calculate loyalty credits
        $availableCredits = $user->loyalty_credits;
        $maxCreditsUsable = $this->calculateMaxCreditsUsable($subtotal);
        
        // Determine credits to apply
        $creditsApplied = 0;
        if ($creditsToUse !== null && $creditsToUse > 0) {
            $creditsApplied = min($creditsToUse, $availableCredits, $maxCreditsUsable);
        }

        // Calculate shipping
        $shippingCost = 0;
        if ($shippingOption) {
            $shippingCost = $this->calculateShippingCost($subtotal, $user, $shippingOption);
        }

        // Calculate final total
        $discountAmount = $creditsApplied;
        $total = $subtotal - $discountAmount + $shippingCost;

        return [
            'subtotal' => $subtotal,
            'credits_applied' => $creditsApplied,
            'discount_amount' => $discountAmount,
            'shipping_cost' => $shippingCost,
            'total' => $total,
            'formatted' => [
                'subtotal' => '₱' . number_format($subtotal, 2),
                'credits_applied' => '₱' . number_format($creditsApplied, 2),
                'discount_amount' => '₱' . number_format($discountAmount, 2),
                'shipping_cost' => $shippingCost > 0 ? '₱' . number_format($shippingCost, 2) : 'FREE',
                'total' => '₱' . number_format($total, 2),
            ],
        ];
    }

    /**
     * Calculate maximum loyalty credits that can be used
     */
    public function calculateMaxCreditsUsable(float $subtotal): float
    {
        $maxPercentage = config('loyalty.redemption.maximum_percentage', 50) / 100;
        $minRedemption = config('loyalty.redemption.minimum_amount', 100);
        
        $maxUsable = $subtotal * $maxPercentage;
        
        // Only allow redemption if it meets minimum
        return $maxUsable >= $minRedemption ? $maxUsable : 0;
    }

    /**
     * Calculate shipping cost for cart
     */
    public function calculateShippingCost(float $subtotal, User $user, string $shippingOption): float
    {
        $freeShippingThreshold = config('shipping.free_shipping_threshold', 2000);
        
        // Check tier-specific free shipping threshold
        $tierBenefits = config('loyalty.tier_benefits.' . $user->loyalty_tier, []);
        $tierFreeShippingThreshold = $tierBenefits['free_shipping_threshold'] ?? null;
        
        // Use the lower threshold if tier has one
        if ($tierFreeShippingThreshold !== null) {
            $freeShippingThreshold = min($freeShippingThreshold, $tierFreeShippingThreshold);
        }

        // Check if free shipping applies
        if ($subtotal >= $freeShippingThreshold) {
            return 0;
        }

        // Parse shipping option (format: "courier:service")
        $parts = explode(':', $shippingOption);
        if (count($parts) !== 2) {
            return 0;
        }

        [$courier, $service] = $parts;

        // Get shipping rate from config
        $courierConfig = config("shipping.couriers.{$courier}");
        if (!$courierConfig || !isset($courierConfig['rates'][$service])) {
            return 0;
        }

        return $courierConfig['rates'][$service];
    }

    /**
     * Get available shipping options for cart
     */
    public function getShippingOptions(float $subtotal, User $user): array
    {
        $freeShippingThreshold = config('shipping.free_shipping_threshold', 2000);
        
        // Check tier-specific free shipping threshold
        $tierBenefits = config('loyalty.tier_benefits.' . $user->loyalty_tier, []);
        $tierFreeShippingThreshold = $tierBenefits['free_shipping_threshold'] ?? null;
        
        // Use the lower threshold if tier has one
        if ($tierFreeShippingThreshold !== null) {
            $freeShippingThreshold = min($freeShippingThreshold, $tierFreeShippingThreshold);
        }

        $isFreeShipping = $subtotal >= $freeShippingThreshold;

        $couriers = config('shipping.couriers', []);
        $options = [];

        foreach ($couriers as $courierKey => $courier) {
            if (!($courier['enabled'] ?? false)) {
                continue;
            }

            foreach ($courier['services'] as $serviceKey => $service) {
                $baseCost = $courier['rates'][$serviceKey] ?? 0;
                $finalCost = $isFreeShipping ? 0 : $baseCost;

                $options[] = [
                    'id' => "{$courierKey}:{$serviceKey}",
                    'courier' => $courierKey,
                    'service' => $serviceKey,
                    'name' => $service['name'],
                    'description' => $service['description'],
                    'estimated_days' => $service['estimated_days'],
                    'cost' => $finalCost,
                    'formatted_cost' => $finalCost > 0 ? '₱' . number_format($finalCost, 2) : 'FREE',
                    'is_free' => $finalCost === 0,
                ];
            }
        }

        return [
            'options' => $options,
            'free_shipping_threshold' => $freeShippingThreshold,
            'formatted_threshold' => '₱' . number_format($freeShippingThreshold, 2),
            'is_free_shipping_eligible' => $isFreeShipping,
            'amount_to_free_shipping' => max(0, $freeShippingThreshold - $subtotal),
            'formatted_amount_to_free' => '₱' . number_format(max(0, $freeShippingThreshold - $subtotal), 2),
        ];
    }

    /**
     * Validate cart items have sufficient inventory
     */
    public function validateCartInventory(User $user): array
    {
        $cartItems = ShoppingCart::with('product')
            ->where('user_id', $user->id)
            ->get();

        $errors = [];

        foreach ($cartItems as $item) {
            $product = $item->product;

            if (!$product->isAvailable()) {
                $errors[] = [
                    'cart_item_id' => $item->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'error' => 'Product is no longer available',
                ];
                continue;
            }

            if ($product->stock_quantity < $item->quantity) {
                $errors[] = [
                    'cart_item_id' => $item->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'error' => 'Insufficient stock',
                    'requested_quantity' => $item->quantity,
                    'available_quantity' => $product->stock_quantity,
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get cart summary
     */
    public function getCartSummary(User $user): array
    {
        $cartItems = ShoppingCart::with('product')
            ->where('user_id', $user->id)
            ->get();

        $subtotal = $cartItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        return [
            'items_count' => $cartItems->count(),
            'total_quantity' => $cartItems->sum('quantity'),
            'subtotal' => $subtotal,
            'formatted_subtotal' => '₱' . number_format($subtotal, 2),
        ];
    }
}
