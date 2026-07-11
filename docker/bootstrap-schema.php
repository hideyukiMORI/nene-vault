<?php

/**
 * Idempotent SQLite schema bootstrap for Docker development.
 *
 * Mirrors tests/bootstrap.php — uses CREATE TABLE IF NOT EXISTS so it is safe
 * to run on every container start. For MySQL, run phinx migrations instead.
 */

declare(strict_types=1);

$dbName = (string) (getenv('DB_NAME') ?: 'var/nene_vault.sqlite');
$dbPath = str_starts_with($dbName, '/') ? $dbName : (string) getcwd() . '/' . $dbName;

$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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

echo "[schema] SQLite schema ready at {$dbPath}\n";
