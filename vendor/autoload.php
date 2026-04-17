<?php

/**
 * PSR-4 Autoloader
 *
 * When Composer is available, run `composer install` and this file
 * will be overwritten by Composer's autoloader. This manual autoloader
 * provides the same PSR-4 functionality for environments without Composer.
 */

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\' => dirname(__DIR__) . '/app/',
        'Config\\' => dirname(__DIR__) . '/config/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
