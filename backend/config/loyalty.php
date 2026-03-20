<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Loyalty Credits Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Diecast Empire loyalty credits system including
    | earning rates, tier thresholds, expiration settings, and redemption rules.
    |
    */

    // Credits earning rate (percentage of purchase amount)
    'credits_rate' => env('LOYALTY_CREDITS_RATE', 0.05), // 5% default

    // Loyalty tier thresholds (based on total spent in PHP)
    'tier_thresholds' => [
        'bronze' => 0,
        'silver' => env('LOYALTY_TIER_SILVER', 10000), // ₱10,000
        'gold' => env('LOYALTY_TIER_GOLD', 50000),     // ₱50,000
        'platinum' => env('LOYALTY_TIER_PLATINUM', 100000), // ₱100,000
    ],

    // Tier benefits and multipliers
    'tier_benefits' => [
        'bronze' => [
            'credits_multiplier' => 1.0,
            'free_shipping_threshold' => null,
            'early_access' => false,
            'bonus_rate' => 0.0,
        ],
        'silver' => [
            'credits_multiplier' => 1.2,
            'free_shipping_threshold' => 5000,
            'early_access' => false,
            'bonus_rate' => 0.01, // 1% bonus
        ],
        'gold' => [
            'credits_multiplier' => 1.5,
            'free_shipping_threshold' => 3000,
            'early_access' => true,
            'bonus_rate' => 0.02, // 2% bonus
        ],
        'platinum' => [
            'credits_multiplier' => 2.0,
            'free_shipping_threshold' => 0,
            'early_access' => true,
            'bonus_rate' => 0.03, // 3% bonus
        ],
    ],

    // Credits expiration settings
    'expiration' => [
        'enabled' => env('LOYALTY_EXPIRATION_ENABLED', true),
        'months' => env('LOYALTY_EXPIRATION_MONTHS', 12), // 12 months default
        'warning_days' => env('LOYALTY_EXPIRATION_WARNING_DAYS', 30), // 30 days warning
    ],

    // Redemption settings
    'redemption' => [
        'minimum_amount' => env('LOYALTY_MIN_REDEMPTION', 100), // ₱100 minimum
        'maximum_percentage' => env('LOYALTY_MAX_PERCENTAGE', 50), // 50% of order max
        'conversion_rate' => env('LOYALTY_CONVERSION_RATE', 1.0), // 1 credit = ₱1
    ],

    // Bonus credits settings
    'bonuses' => [
        'birthday_credits' => env('LOYALTY_BIRTHDAY_BONUS', 500), // ₱500 birthday bonus
        'referral_credits' => env('LOYALTY_REFERRAL_BONUS', 1000), // ₱1000 referral bonus
        'review_credits' => env('LOYALTY_REVIEW_BONUS', 50), // ₱50 per review
    ],

    // Notification settings
    'notifications' => [
        'tier_upgrade' => true,
        'credits_earned' => true,
        'credits_expiring' => true,
        'credits_expired' => true,
    ],
];