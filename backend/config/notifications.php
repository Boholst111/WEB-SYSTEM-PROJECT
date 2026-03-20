<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */

    'enabled' => env('NOTIFICATIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Email Notifications
    |--------------------------------------------------------------------------
    */

    'email' => [
        'enabled' => env('EMAIL_NOTIFICATIONS_ENABLED', true),
        'queue' => env('EMAIL_QUEUE', 'emails'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'hello@diecastempire.com'),
        'from_name' => env('MAIL_FROM_NAME', 'Diecast Empire'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Notifications
    |--------------------------------------------------------------------------
    */

    'sms' => [
        'enabled' => env('SMS_NOTIFICATIONS_ENABLED', true),
        'provider' => env('SMS_PROVIDER', 'semaphore'),
        'queue' => env('SMS_QUEUE', 'sms'),
        
        // Semaphore SMS Gateway (Popular in Philippines)
        'semaphore' => [
            'api_key' => env('SEMAPHORE_API_KEY'),
            'sender_name' => env('SEMAPHORE_SENDER_NAME', 'DiecastEmp'),
            'api_url' => env('SEMAPHORE_API_URL', 'https://api.semaphore.co/api/v4/messages'),
        ],
        
        // Itexmo SMS Gateway (Alternative)
        'itexmo' => [
            'api_code' => env('ITEXMO_API_CODE'),
            'password' => env('ITEXMO_PASSWORD'),
            'api_url' => env('ITEXMO_API_URL', 'https://www.itexmo.com/php_api/api.php'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Templates
    |--------------------------------------------------------------------------
    */

    'templates' => [
        'order_confirmed' => [
            'email' => true,
            'sms' => false,
        ],
        'order_processing' => [
            'email' => true,
            'sms' => false,
        ],
        'order_shipped' => [
            'email' => true,
            'sms' => true,
        ],
        'order_delivered' => [
            'email' => true,
            'sms' => true,
        ],
        'order_cancelled' => [
            'email' => true,
            'sms' => true,
        ],
        'preorder_arrival' => [
            'email' => true,
            'sms' => true,
        ],
        'preorder_payment_reminder' => [
            'email' => true,
            'sms' => true,
        ],
        'loyalty_tier_advancement' => [
            'email' => true,
            'sms' => false,
        ],
        'payment_failed' => [
            'email' => true,
            'sms' => true,
        ],
        'security_alert' => [
            'email' => true,
            'sms' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Preferences
    |--------------------------------------------------------------------------
    */

    'user_preferences' => [
        'allow_email_marketing' => true,
        'allow_sms_marketing' => false,
        'allow_order_updates' => true,
        'allow_preorder_notifications' => true,
        'allow_loyalty_notifications' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'email_per_user_per_hour' => 10,
        'sms_per_user_per_hour' => 5,
        'bulk_email_batch_size' => 100,
        'bulk_sms_batch_size' => 50,
    ],

];
