<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used
    | when no specific gateway is requested.
    |
    */
    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'gcash'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Here you may configure the payment gateways for your application.
    | Each gateway has its own configuration options.
    |
    */
    'gateways' => [
        'gcash' => [
            'merchant_id' => env('GCASH_MERCHANT_ID'),
            'secret_key' => env('GCASH_SECRET_KEY'),
            'api_url' => env('GCASH_API_URL', 'https://api.gcash.com'),
            'webhook_secret' => env('GCASH_WEBHOOK_SECRET'),
            'timeout' => 30,
            'enabled' => env('GCASH_ENABLED', true),
        ],

        'maya' => [
            'public_key' => env('MAYA_PUBLIC_KEY'),
            'secret_key' => env('MAYA_SECRET_KEY'),
            'api_url' => env('MAYA_API_URL', 'https://pg-sandbox.paymaya.com'),
            'webhook_secret' => env('MAYA_WEBHOOK_SECRET'),
            'timeout' => 30,
            'enabled' => env('MAYA_ENABLED', true),
        ],

        'bank_transfer' => [
            'enabled' => env('BANK_TRANSFER_ENABLED', true),
            'banks' => [
                'bpi' => [
                    'name' => 'Bank of the Philippine Islands',
                    'account_number' => env('BPI_ACCOUNT_NUMBER'),
                    'account_name' => env('BPI_ACCOUNT_NAME'),
                ],
                'bdo' => [
                    'name' => 'Banco de Oro',
                    'account_number' => env('BDO_ACCOUNT_NUMBER'),
                    'account_name' => env('BDO_ACCOUNT_NAME'),
                ],
                'metrobank' => [
                    'name' => 'Metropolitan Bank & Trust Company',
                    'account_number' => env('METROBANK_ACCOUNT_NUMBER'),
                    'account_name' => env('METROBANK_ACCOUNT_NAME'),
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Security
    |--------------------------------------------------------------------------
    |
    | Security settings for payment processing including fraud prevention
    | and transaction limits.
    |
    */
    'security' => [
        'max_amount' => env('PAYMENT_MAX_AMOUNT', 100000), // PHP 100,000
        'min_amount' => env('PAYMENT_MIN_AMOUNT', 1), // PHP 1
        'daily_limit' => env('PAYMENT_DAILY_LIMIT', 500000), // PHP 500,000
        'fraud_detection' => env('PAYMENT_FRAUD_DETECTION', true),
        'require_verification' => env('PAYMENT_REQUIRE_VERIFICATION', true),
        'webhook_timeout' => env('PAYMENT_WEBHOOK_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    |
    | Default currency and supported currencies for payments.
    |
    */
    'currency' => [
        'default' => 'PHP',
        'supported' => ['PHP'],
        'symbol' => '₱',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for payment webhook handling and verification.
    |
    */
    'webhooks' => [
        'verify_signature' => env('PAYMENT_VERIFY_WEBHOOK_SIGNATURE', true),
        'max_retries' => env('PAYMENT_WEBHOOK_MAX_RETRIES', 3),
        'retry_delay' => env('PAYMENT_WEBHOOK_RETRY_DELAY', 60), // seconds
    ],
];