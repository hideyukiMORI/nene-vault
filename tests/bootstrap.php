<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Test database bootstrap.
 * Creates the schema in the SQLite test database before any tests run.
 * Uses the same DB_NAME env var that phpunit.xml.dist sets.
 */
$dbName = (string) (getenv('DB_NAME') ?: ':memory:');
$adapter = (string) (getenv('DB_ADAPTER') ?: 'sqlite');

if ($adapter !== 'sqlite') {
    return; // MySQL/other: expect real migrations to have been run
}

if ($dbName !== ':memory:') {
    $dbPath = str_starts_with($dbName, '/') ? $dbName : dirname(__DIR__) . '/' . $dbName;
    $dir = dirname($dbPath);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $dsn = "sqlite:{$dbPath}";
} else {
    // :memory: — schema is shared via singleton PDO trick (not supported here, skip)
    return;
}

$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('PRAGMA journal_mode=WAL');

$pdo->exec('CREATE TABLE IF NOT EXISTS organizations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    external_id VARCHAR(255),
    custom_domain VARCHAR(255),
    plan VARCHAR(32) NOT NULL DEFAULT "free",
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE(slug),
    UNIQUE(custom_domain),
    UNIQUE(external_id)
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT "admin",
    organization_id INTEGER,
    status VARCHAR(16) NOT NULL DEFAULT "active",
    invite_token_hash VARCHAR(64),
    invite_expires_at DATETIME,
    password_reset_token_hash VARCHAR(64),
    password_reset_expires_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE(email)
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS vault_settings (
    organization_id INTEGER PRIMARY KEY,
    retention_years INTEGER NOT NULL DEFAULT 10,
    storage_path_override VARCHAR(512),
    invoice_api_base_url VARCHAR(512),
    clear_api_base_url VARCHAR(512),
    updated_by INTEGER,
    updated_at DATETIME NOT NULL
)');
