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

$pdo->exec('CREATE TABLE IF NOT EXISTS audit_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    actor_user_id INTEGER,
    organization_id INTEGER,
    before_json TEXT,
    after_json TEXT,
    source VARCHAR(32) NOT NULL DEFAULT "api",
    metadata_json TEXT,
    created_at DATETIME NOT NULL
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS vault_documents (
    id CHAR(26) PRIMARY KEY,
    organization_id INTEGER NOT NULL,
    current_version_id CHAR(26) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT "active",
    transaction_date DATE,
    amount_cents INTEGER,
    counterparty_name VARCHAR(255) NOT NULL,
    category VARCHAR(32) NOT NULL,
    tags TEXT,
    date_uncertain INTEGER NOT NULL DEFAULT 0,
    is_metadata_confirmed INTEGER NOT NULL DEFAULT 0,
    retention_years INTEGER NOT NULL DEFAULT 10,
    retention_expires_at DATE NOT NULL,
    uploaded_at DATETIME NOT NULL,
    uploaded_by INTEGER,
    voided_at DATETIME,
    voided_by INTEGER,
    void_reason VARCHAR(255),
    void_note TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier_hash VARCHAR(64) NOT NULL,
    attempt_count INTEGER NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    locked_until DATETIME,
    created_at DATETIME,
    UNIQUE(identifier_hash)
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS document_versions (
    id CHAR(26) PRIMARY KEY,
    vault_document_id CHAR(26) NOT NULL,
    organization_id INTEGER NOT NULL,
    version_number INTEGER NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    file_sha256 CHAR(64) NOT NULL,
    mime_type VARCHAR(64) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size_bytes INTEGER NOT NULL,
    source VARCHAR(32) NOT NULL DEFAULT "web_upload",
    uploaded_at DATETIME NOT NULL,
    uploaded_by INTEGER,
    UNIQUE(vault_document_id, version_number)
)');

// Transient state cleanup (#155): the login throttle table and the file-backed
// demo-start rate limits persist across local runs (the SQLite file and var/
// survive), which would make repeated suite runs trip their own locks. Both
// hold only rate-limit counters, never business data, so a fresh suite starts
// clean — mirroring what CI gets for free.
$pdo->exec('DELETE FROM login_attempts');

$rateLimitDir = dirname(__DIR__) . '/var/rate-limits';

if (is_dir($rateLimitDir)) {
    foreach (glob($rateLimitDir . '/*') ?: [] as $rateLimitFile) {
        if (is_file($rateLimitFile)) {
            @unlink($rateLimitFile);
        }
    }
}
