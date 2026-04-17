<?php

declare(strict_types=1);

/**
 * PediCare Clinic Management System
 * Public Entry Point
 */

// Error reporting based on environment
$isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
error_reporting($isDebug ? E_ALL : 0);
ini_set('display_errors', $isDebug ? '1' : '0');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Boot the application
$app = \App\Core\Application::getInstance();
$app->run();
