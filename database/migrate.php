<?php

/**
 * Database Migration Script
 * Usage: php database/migrate.php
 *
 * This script creates the database and all tables from schema.sql,
 * then optionally seeds with default data from seed.sql.
 */

echo "PediCare Clinic - Database Migration\n";
echo "=====================================\n\n";

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = (int) ($_ENV['DB_PORT'] ?? 3306);
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

// Connect without database first
$conn = new mysqli($host, $username, $password, '', $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Connected to MySQL server.\n";

// Run schema
$schemaFile = __DIR__ . '/schema.sql';
if (!file_exists($schemaFile)) {
    die("Schema file not found: $schemaFile\n");
}

echo "Running schema.sql...\n";
$schema = file_get_contents($schemaFile);

// Execute multi-query
$conn->multi_query($schema);
do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
} while ($conn->next_result());

if ($conn->error) {
    echo "Schema error: " . $conn->error . "\n";
} else {
    echo "Schema created successfully.\n";
}

// Run seed data
$seedFile = __DIR__ . '/seed.sql';
if (file_exists($seedFile)) {
    $seed = (in_array('--seed', $argv ?? [])) || (in_array('-s', $argv ?? []));

    if ($seed || !$conn->query("SELECT 1 FROM pediatric_clinic.users LIMIT 1")) {
        echo "Running seed.sql...\n";
        $seedSql = file_get_contents($seedFile);
        $conn->multi_query($seedSql);
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());

        if ($conn->error) {
            echo "Seed error: " . $conn->error . "\n";
        } else {
            echo "Seed data inserted.\n";
        }
    } else {
        echo "Data already exists, skipping seed (use --seed to force).\n";
    }
}

$conn->close();

echo "\nMigration complete!\n";
echo "\nDefault admin credentials:\n";
echo "  Email: admin@pedicare.com\n";
echo "  Password: password\n";
