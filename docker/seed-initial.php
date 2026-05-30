<?php

/**
 * Idempotent initial seed for Docker development.
 *
 * Creates (if absent):
 *   1. The default organization (matching ORG_SLUG env)
 *   2. Default vault_settings for that org
 *   3. A superadmin user (email + password from env, or safe defaults)
 *
 * Uses raw PDO to guarantee the same database file that phinx just migrated.
 * Safe to run multiple times — INSERT OR IGNORE / INSERT IGNORE.
 */

declare(strict_types=1);

$adapter       = (string) (getenv('DB_ADAPTER') ?: 'sqlite');
$dbName        = (string) (getenv('DB_NAME') ?: 'var/nene_vault.sqlite');
$orgSlug       = (string) (getenv('ORG_SLUG') ?: 'default');
$orgName       = (string) (getenv('ORG_NAME') ?: 'NeNe Vault');
$adminEmail    = (string) (getenv('ADMIN_EMAIL') ?: 'admin@example.com');
$adminPassword = (string) (getenv('ADMIN_PASSWORD') ?: 'changeme123');

// ── Build PDO connection directly ─────────────────────────────────────────────
if ($adapter === 'mysql') {
    $host   = (string) (getenv('DB_HOST') ?: 'mysql');
    $port   = (string) (getenv('DB_PORT') ?: '3306');
    $user   = (string) (getenv('DB_USER') ?: 'nene_vault');
    $pass   = (string) (getenv('DB_PASSWORD') ?: 'nene_vault');
    $dsn    = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $pdo    = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $ignore = 'INSERT IGNORE INTO';
} else {
    // Resolve relative path from CWD (init.sh does `cd /var/www/html`)
    $dbPath = str_starts_with($dbName, '/') ? $dbName : (string) getcwd() . '/' . $dbName;
    $pdo    = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $ignore = 'INSERT OR IGNORE INTO';
}

echo "[seed] DB adapter={$adapter}, name={$dbName}\n";

$now = date('Y-m-d H:i:s');

// ── 1. Default org ────────────────────────────────────────────────────────────
$pdo->exec("{$ignore} organizations
    (name, slug, plan, is_active, created_at, updated_at)
    VALUES ('{$orgName}', '{$orgSlug}', 'free', 1, '{$now}', '{$now}')");

$stmt = $pdo->query("SELECT id FROM organizations WHERE slug = '{$orgSlug}'");
assert($stmt !== false);
$row  = $stmt->fetch();
assert($row !== false);
$orgId = (int) $row['id'];
echo "[seed] org '{$orgSlug}' → id={$orgId}\n";

// ── 2. vault_settings for the org ─────────────────────────────────────────────
$pdo->exec("{$ignore} vault_settings
    (organization_id, retention_years, updated_at)
    VALUES ({$orgId}, 10, '{$now}')");
echo "[seed] vault_settings seeded\n";

// ── 3. Superadmin user ────────────────────────────────────────────────────────
$hash = password_hash($adminPassword, PASSWORD_BCRYPT);
$pdo->exec("{$ignore} users
    (email, password_hash, role, organization_id, status, created_at, updated_at)
    VALUES ('{$adminEmail}', '{$hash}', 'superadmin', NULL, 'active', '{$now}', '{$now}')");
echo "[seed] superadmin '{$adminEmail}' seeded\n";
echo "[seed] Done.\n";
