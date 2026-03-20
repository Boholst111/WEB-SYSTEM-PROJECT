<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Content Delivery Network integration for static assets
    | and product images to improve performance during high-traffic events.
    |
    */

    'enabled' => env('CDN_ENABLED', false),

    'url' => env('CDN_URL', ''),

    'assets_path' => env('CDN_ASSETS_PATH', 'assets'),

    'images_path' => env('CDN_IMAGES_PATH', 'images'),

    /*
    |--------------------------------------------------------------------------
    | Asset Types to Serve from CDN
    |--------------------------------------------------------------------------
    */

    'asset_types' => [
        'css',
        'js',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'svg',
        'webp',
        'woff',
        'woff2',
        'ttf',
        'eot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Control Headers
    |--------------------------------------------------------------------------
    */

    'cache_control' => [
        'images' => 'public, max-age=31536000, immutable', // 1 year
        'css' => 'public, max-age=31536000, immutable', // 1 year
        'js' => 'public, max-age=31536000, immutable', // 1 year
        'fonts' => 'public, max-age=31536000, immutable', // 1 year
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Optimization
    |--------------------------------------------------------------------------
    */

    'image_optimization' => [
        'enabled' => env('CDN_IMAGE_OPTIMIZATION', true),
        'quality' => env('CDN_IMAGE_QUALITY', 85),
        'formats' => ['webp', 'jpg', 'png'],
    ],

];
