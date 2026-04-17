<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'PediCare Clinic',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'key' => $_ENV['APP_KEY'] ?? '',

    'timezone' => 'Asia/Manila',
    'locale' => 'en',

    'session' => [
        'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 120),
        'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'name' => 'pedicare_session',
    ],

    'upload' => [
        'max_size' => (int) ($_ENV['UPLOAD_MAX_SIZE'] ?? 10485760),
        'allowed_types' => explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'jpg,jpeg,png,pdf,doc,docx'),
        'path' => dirname(__DIR__) . '/storage/uploads',
    ],

    'pagination' => [
        'per_page' => 15,
    ],

    'security' => [
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes in seconds
        'password_reset_expiry' => 3600, // 1 hour
        'email_verification_expiry' => 86400, // 24 hours
        'cancellation_hours' => 24,
    ],

    'cache' => [
        'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
        'path' => dirname(__DIR__) . '/storage/cache',
        'ttl' => 3600,
    ],
];
