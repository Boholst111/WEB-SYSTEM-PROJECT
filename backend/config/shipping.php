<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Shipping Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for shipping services and courier integrations
    |
    */

    'sender_address' => [
        'company' => env('SHIPPING_SENDER_COMPANY', 'Diecast Empire'),
        'address_line_1' => env('SHIPPING_SENDER_ADDRESS_1', '123 Business St.'),
        'address_line_2' => env('SHIPPING_SENDER_ADDRESS_2', 'Unit 456'),
        'city' => env('SHIPPING_SENDER_CITY', 'Makati City'),
        'province' => env('SHIPPING_SENDER_PROVINCE', 'Metro Manila'),
        'postal_code' => env('SHIPPING_SENDER_POSTAL', '1200'),
        'country' => 'Philippines',
    ],

    'sender_phone' => env('SHIPPING_SENDER_PHONE', '+63 2 8123 4567'),
    'sender_email' => env('SHIPPING_SENDER_EMAIL', 'shipping@diecastempire.com'),

    /*
    |--------------------------------------------------------------------------
    | Courier Services Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for each supported courier service
    |
    */

    'couriers' => [
        'lbc' => [
            'enabled' => env('LBC_ENABLED', true),
            'name' => 'LBC Express',
            'api_url' => env('LBC_API_URL', 'https://api.lbcexpress.com'),
            'api_key' => env('LBC_API_KEY'),
            'api_secret' => env('LBC_API_SECRET'),
            'rates' => [
                'standard' => 150.0,
                'express' => 250.0,
            ],
            'weight_rate' => 20.0, // Per kg after first kg
            'services' => [
                'standard' => [
                    'name' => 'LBC Standard',
                    'estimated_days' => 3,
                    'description' => 'Standard delivery within 3-5 business days'
                ],
                'express' => [
                    'name' => 'LBC Express',
                    'estimated_days' => 1,
                    'description' => 'Express delivery within 1-2 business days'
                ]
            ]
        ],

        'jnt' => [
            'enabled' => env('JNT_ENABLED', true),
            'name' => 'J&T Express',
            'api_url' => env('JNT_API_URL', 'https://api.jtexpress.ph'),
            'api_key' => env('JNT_API_KEY'),
            'api_secret' => env('JNT_API_SECRET'),
            'rates' => [
                'standard' => 120.0,
                'express' => 200.0,
            ],
            'weight_rate' => 15.0,
            'services' => [
                'standard' => [
                    'name' => 'J&T Standard',
                    'estimated_days' => 3,
                    'description' => 'Standard delivery within 3-4 business days'
                ],
                'express' => [
                    'name' => 'J&T Express',
                    'estimated_days' => 2,
                    'description' => 'Express delivery within 2-3 business days'
                ]
            ]
        ],

        'ninjavan' => [
            'enabled' => env('NINJAVAN_ENABLED', true),
            'name' => 'Ninja Van',
            'api_url' => env('NINJAVAN_API_URL', 'https://api.ninjavan.co'),
            'api_key' => env('NINJAVAN_API_KEY'),
            'api_secret' => env('NINJAVAN_API_SECRET'),
            'rates' => [
                'standard' => 130.0,
                'express' => 220.0,
                'same_day' => 350.0,
            ],
            'weight_rate' => 18.0,
            'services' => [
                'standard' => [
                    'name' => 'Ninja Van Standard',
                    'estimated_days' => 3,
                    'description' => 'Standard delivery within 3-5 business days'
                ],
                'express' => [
                    'name' => 'Ninja Van Express',
                    'estimated_days' => 2,
                    'description' => 'Express delivery within 2-3 business days'
                ],
                'same_day' => [
                    'name' => 'Ninja Van Same Day',
                    'estimated_days' => 0,
                    'description' => 'Same day delivery (Metro Manila only)'
                ]
            ]
        ],

        '2go' => [
            'enabled' => env('2GO_ENABLED', true),
            'name' => '2GO Express',
            'api_url' => env('2GO_API_URL', 'https://api.2go.com.ph'),
            'api_key' => env('2GO_API_KEY'),
            'api_secret' => env('2GO_API_SECRET'),
            'rates' => [
                'standard' => 140.0,
                'express' => 240.0,
            ],
            'weight_rate' => 22.0,
            'services' => [
                'standard' => [
                    'name' => '2GO Standard',
                    'estimated_days' => 4,
                    'description' => 'Standard delivery within 4-6 business days'
                ],
                'express' => [
                    'name' => '2GO Express',
                    'estimated_days' => 2,
                    'description' => 'Express delivery within 2-3 business days'
                ]
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Shipping Settings
    |--------------------------------------------------------------------------
    */

    'default_courier' => env('DEFAULT_COURIER', 'lbc'),
    'default_service' => env('DEFAULT_SERVICE', 'standard'),
    'free_shipping_threshold' => env('FREE_SHIPPING_THRESHOLD', 2000.0),
    'max_package_weight' => env('MAX_PACKAGE_WEIGHT', 30.0), // kg
    'max_package_dimensions' => [
        'length' => 100, // cm
        'width' => 80,   // cm
        'height' => 60,  // cm
    ],

    /*
    |--------------------------------------------------------------------------
    | Regional Shipping Zones
    |--------------------------------------------------------------------------
    */

    'zones' => [
        'metro_manila' => [
            'name' => 'Metro Manila',
            'provinces' => [
                'Metro Manila',
                'Manila',
                'Quezon City',
                'Makati',
                'Taguig',
                'Pasig',
                'Mandaluyong',
                'San Juan',
                'Marikina',
                'Caloocan',
                'Malabon',
                'Navotas',
                'Valenzuela',
                'Las Piñas',
                'Muntinlupa',
                'Parañaque',
                'Pasay'
            ],
            'rate_multiplier' => 1.0
        ],
        'luzon' => [
            'name' => 'Luzon',
            'rate_multiplier' => 1.2
        ],
        'visayas' => [
            'name' => 'Visayas',
            'rate_multiplier' => 1.5
        ],
        'mindanao' => [
            'name' => 'Mindanao',
            'rate_multiplier' => 1.8
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Configuration
    |--------------------------------------------------------------------------
    */

    'tracking' => [
        'update_interval' => 3600, // seconds (1 hour)
        'webhook_enabled' => env('SHIPPING_WEBHOOK_ENABLED', true),
        'webhook_url' => env('SHIPPING_WEBHOOK_URL', '/api/webhooks/shipping'),
    ],
];