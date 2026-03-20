<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security hardening configuration for the Diecast
    | Empire platform including CSRF protection, XSS prevention, rate limiting,
    | and other security measures.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */
    'csrf' => [
        'enabled' => env('CSRF_ENABLED', true),
        'token_lifetime' => env('CSRF_TOKEN_LIFETIME', 7200), // seconds
        'exclude_routes' => [
            'api/webhooks/*',
            'api/health',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => env('RATE_LIMITING_ENABLED', true),
        'limits' => [
            'api' => [
                'max_attempts' => env('RATE_LIMIT_API', 60),
                'decay_minutes' => 1,
            ],
            'auth' => [
                'max_attempts' => env('RATE_LIMIT_AUTH', 5),
                'decay_minutes' => 15,
            ],
            'payment' => [
                'max_attempts' => env('RATE_LIMIT_PAYMENT', 10),
                'decay_minutes' => 5,
            ],
            'search' => [
                'max_attempts' => env('RATE_LIMIT_SEARCH', 30),
                'decay_minutes' => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    */
    'password' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_special_chars' => env('PASSWORD_REQUIRE_SPECIAL', true),
        'max_age_days' => env('PASSWORD_MAX_AGE_DAYS', 90),
        'prevent_reuse' => env('PASSWORD_PREVENT_REUSE', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    */
    'session' => [
        'secure_cookie' => env('SESSION_SECURE_COOKIE', true),
        'http_only' => true,
        'same_site' => env('SESSION_SAME_SITE', 'strict'),
        'lifetime' => env('SESSION_LIFETIME', 120),
        'expire_on_close' => false,
        'encrypt' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    */
    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Validation
    |--------------------------------------------------------------------------
    */
    'input_validation' => [
        'enabled' => env('INPUT_VALIDATION_ENABLED', true),
        'sanitize_html' => true,
        'strip_tags' => true,
        'max_input_length' => env('MAX_INPUT_LENGTH', 10000),
        'allowed_file_types' => [
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'documents' => ['pdf', 'doc', 'docx'],
        ],
        'max_file_size' => env('MAX_FILE_SIZE', 5120), // KB
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Injection Prevention
    |--------------------------------------------------------------------------
    */
    'sql_injection' => [
        'use_prepared_statements' => true,
        'escape_user_input' => true,
        'log_suspicious_queries' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | XSS Prevention
    |--------------------------------------------------------------------------
    */
    'xss_prevention' => [
        'enabled' => env('XSS_PREVENTION_ENABLED', true),
        'escape_output' => true,
        'sanitize_input' => true,
        'content_security_policy' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Security
    |--------------------------------------------------------------------------
    */
    'api' => [
        'require_authentication' => true,
        'token_expiration' => env('API_TOKEN_EXPIRATION', 3600), // seconds
        'refresh_token_expiration' => env('API_REFRESH_TOKEN_EXPIRATION', 604800), // 7 days
        'max_tokens_per_user' => env('API_MAX_TOKENS_PER_USER', 5),
        'ip_whitelist' => env('API_IP_WHITELIST', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Security
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'verify_webhook_signatures' => env('PAYMENT_VERIFY_WEBHOOK_SIGNATURE', true),
        'encrypt_sensitive_data' => true,
        'pci_compliance' => true,
        'fraud_detection' => env('PAYMENT_FRAUD_DETECTION', true),
        'require_cvv' => true,
        'require_3d_secure' => env('PAYMENT_REQUIRE_3D_SECURE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => env('AUDIT_LOGGING_ENABLED', true),
        'log_authentication' => true,
        'log_authorization' => true,
        'log_data_changes' => true,
        'log_admin_actions' => true,
        'retention_days' => env('AUDIT_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Blocking
    |--------------------------------------------------------------------------
    */
    'ip_blocking' => [
        'enabled' => env('IP_BLOCKING_ENABLED', true),
        'auto_block_threshold' => env('IP_AUTO_BLOCK_THRESHOLD', 10), // failed attempts
        'block_duration' => env('IP_BLOCK_DURATION', 3600), // seconds
        'whitelist' => explode(',', env('IP_WHITELIST', '')),
        'blacklist' => explode(',', env('IP_BLACKLIST', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'enabled' => env('TWO_FACTOR_ENABLED', false),
        'required_for_admin' => env('TWO_FACTOR_REQUIRED_ADMIN', true),
        'methods' => ['email', 'sms', 'authenticator'],
        'code_expiration' => env('TWO_FACTOR_CODE_EXPIRATION', 300), // seconds
    ],
];
