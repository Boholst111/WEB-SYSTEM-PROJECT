<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for application monitoring, logging,
    | and performance tracking for the Diecast Empire platform.
    |
    */

    'enabled' => env('MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'enabled' => env('PERFORMANCE_MONITORING_ENABLED', true),
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'slow_request_threshold' => env('SLOW_REQUEST_THRESHOLD', 2000), // milliseconds
        'memory_limit_warning' => env('MEMORY_LIMIT_WARNING', 128), // MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Tracking
    |--------------------------------------------------------------------------
    */
    'error_tracking' => [
        'enabled' => env('ERROR_TRACKING_ENABLED', true),
        'sentry' => [
            'dsn' => env('SENTRY_LARAVEL_DSN'),
            'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
            'environment' => env('APP_ENV', 'production'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    */
    'health_checks' => [
        'enabled' => env('HEALTH_CHECKS_ENABLED', true),
        'interval' => env('HEALTH_CHECK_INTERVAL', 60), // seconds
        'checks' => [
            'database' => true,
            'redis' => true,
            'storage' => true,
            'queue' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'enabled' => env('METRICS_ENABLED', true),
        'collect' => [
            'requests' => true,
            'database_queries' => true,
            'cache_hits' => true,
            'queue_jobs' => true,
            'memory_usage' => true,
        ],
        'retention_days' => env('METRICS_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    */
    'alerts' => [
        'enabled' => env('ALERTS_ENABLED', true),
        'channels' => [
            'email' => env('ALERT_EMAIL', 'admin@diecastempire.com'),
            'slack' => env('ALERT_SLACK_WEBHOOK'),
        ],
        'thresholds' => [
            'error_rate' => env('ALERT_ERROR_RATE', 5), // errors per minute
            'response_time' => env('ALERT_RESPONSE_TIME', 3000), // milliseconds
            'cpu_usage' => env('ALERT_CPU_USAGE', 80), // percentage
            'memory_usage' => env('ALERT_MEMORY_USAGE', 85), // percentage
            'disk_usage' => env('ALERT_DISK_USAGE', 90), // percentage
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channels' => [
            'application' => [
                'enabled' => true,
                'level' => env('LOG_LEVEL', 'error'),
            ],
            'security' => [
                'enabled' => true,
                'level' => 'warning',
                'events' => [
                    'failed_login',
                    'unauthorized_access',
                    'suspicious_activity',
                    'payment_fraud_attempt',
                ],
            ],
            'performance' => [
                'enabled' => true,
                'level' => 'info',
                'log_slow_queries' => true,
                'log_slow_requests' => true,
            ],
            'audit' => [
                'enabled' => true,
                'level' => 'info',
                'events' => [
                    'order_created',
                    'payment_processed',
                    'inventory_updated',
                    'user_registered',
                    'admin_action',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Tracking
    |--------------------------------------------------------------------------
    */
    'request_tracking' => [
        'enabled' => env('REQUEST_TRACKING_ENABLED', true),
        'log_headers' => false,
        'log_body' => false,
        'exclude_paths' => [
            '/health',
            '/api/health',
        ],
    ],
];
