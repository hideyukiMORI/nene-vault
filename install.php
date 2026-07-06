<?php

/**
 * NeNe Vault — Web Installer
 *
 * Place this file in the project root and visit it in a browser.
 * The installer writes .env, runs the database bootstrap, seeds the initial
 * admin user, and then deletes itself.
 *
 * Security: after the installer completes (or if you abort), delete this file
 * manually if the automatic cleanup fails.
 */

declare(strict_types=1);

use Nene2\Install\DatabaseSchemaApplier;
use Nene2\Install\EnvironmentWriter;
use NeneVault\Install\InstallEnvironment;
use Phinx\Config\Config;

const INSTALLER_VERSION = '1.0';
const MIN_PHP = '8.4.1';

// ── Bootstrap ────────────────────────────────────────────────────────────────

// Composer autoloader is required to reach the NENE2 installer toolkit
// (EnvironmentWriter and friends). It may be absent on a freshly extracted
// archive — in that case the requirements screen tells the operator to run
// `composer install` first, so we only require it when present.
$vaultAutoload = dirname(__FILE__) . '/vendor/autoload.php';
if (is_file($vaultAutoload)) {
    require_once $vaultAutoload;
}

session_start();

$step = (int) ($_POST['step'] ?? $_GET['step'] ?? 1);
$errors = [];
$info = [];

// ── Helpers ──────────────────────────────────────────────────────────────────

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function post(string $key, string $default = ''): string
{
    return is_string($_POST[$key] ?? null) ? trim((string) $_POST[$key]) : $default;
}

function randomSecret(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function projectRoot(): string
{
    return dirname(__FILE__);
}

function envPath(): string
{
    return projectRoot() . '/.env';
}

/** @return array<string, string> */
function readEnvFile(): array
{
    $path = envPath();
    if (!file_exists($path)) {
        return [];
    }

    $result = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $result[trim($parts[0])] = trim($parts[1]);
        }
    }

    return $result;
}

// ── Step 1: Requirements check ───────────────────────────────────────────────

/** @return array<string, array{ok: bool, label: string, detail: string}> */
function checkRequirements(): array
{
    $root = projectRoot();
    $checks = [];

    // PHP version
    $phpOk = version_compare(PHP_VERSION, MIN_PHP, '>=');
    $checks['php'] = [
        'ok'     => $phpOk,
        'label'  => 'PHP >= ' . MIN_PHP,
        'detail' => 'Found PHP ' . PHP_VERSION,
    ];

    // Extensions
    foreach (['pdo', 'pdo_sqlite', 'zip', 'mbstring', 'json'] as $ext) {
        $checks['ext_' . $ext] = [
            'ok'     => extension_loaded($ext),
            'label'  => 'ext-' . $ext,
            'detail' => extension_loaded($ext) ? 'loaded' : 'MISSING',
        ];
    }

    // Vendor autoloader
    $checks['vendor'] = [
        'ok'     => file_exists($root . '/vendor/autoload.php'),
        'label'  => 'vendor/autoload.php',
        'detail' => file_exists($root . '/vendor/autoload.php')
            ? 'found'
            : 'MISSING — run composer install first',
    ];

    // Writable var/ directory
    $varDir = $root . '/var';
    if (!is_dir($varDir)) {
        @mkdir($varDir, 0755, true);
    }

    $checks['var_writable'] = [
        'ok'     => is_writable($varDir),
        'label'  => 'var/ writable',
        'detail' => is_writable($varDir) ? 'writable' : 'NOT writable — fix permissions',
    ];

    // Writable root for .env
    $checks['root_writable'] = [
        'ok'     => is_writable($root),
        'label'  => 'Project root writable',
        'detail' => is_writable($root) ? 'writable' : 'NOT writable — fix permissions',
    ];

    return $checks;
}

// ── Step 4: Run setup ─────────────────────────────────────────────────────────

/** @return array{ok: bool, messages: list<string>} */
function runSetup(): array
{
    $root = projectRoot();
    $messages = [];
    $env = readEnvFile();

    // Load env vars into environment
    foreach ($env as $k => $v) {
        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
    }

    // Bootstrap schema
    $adapter = $env['DB_ADAPTER'] ?? 'sqlite';

    if ($adapter === 'sqlite') {
        $dbName = $env['DB_NAME'] ?? 'var/nene_vault.sqlite';

        if (!str_starts_with($dbName, '/')) {
            $dbPath = $root . '/' . $dbName;
        } else {
            $dbPath = $dbName;
        }

        $dir = dirname($dbPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $messages[] = 'Bootstrapping SQLite schema at ' . $dbPath;

        try {
            $bootstrapScript = $root . '/docker/bootstrap-schema.php';

            if (!file_exists($bootstrapScript)) {
                return ['ok' => false, 'messages' => array_merge($messages, ['bootstrap-schema.php not found'])];
            }

            require $bootstrapScript;
            $messages[] = 'Schema bootstrapped.';
        } catch (Throwable $e) {
            return ['ok' => false, 'messages' => array_merge($messages, ['Schema error: ' . $e->getMessage()])];
        }
    } else {
        $messages[] = 'Running Phinx migrations (MySQL)...';

        // Apply pending migrations in-process via phinx's Manager API instead of
        // shelling out to vendor/bin/phinx. Shared hosting often disables exec(),
        // and `composer install --no-dev` used to drop phinx entirely (it was a
        // dev-only dependency) breaking the shell-out — phinx is now in `require`
        // and DatabaseSchemaApplier drives the same engine without a subprocess.
        try {
            $migrationOutput = (new DatabaseSchemaApplier())->apply(new Config([
                'paths' => ['migrations' => $root . '/database/migrations'],
                'environments' => [
                    'default_environment' => 'install',
                    'install' => [
                        'adapter' => 'mysql',
                        'host' => $env['DB_HOST'] ?? '127.0.0.1',
                        'port' => (int) ($env['DB_PORT'] ?? 3306),
                        'name' => $env['DB_NAME'] ?? 'nene_vault',
                        'user' => $env['DB_USER'] ?? '',
                        'pass' => $env['DB_PASSWORD'] ?? '',
                        'charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
                    ],
                ],
                // Keep in step with phinx.php (creation-ordered migrations).
                'version_order' => 'creation',
            ]));

            foreach (explode("\n", trim($migrationOutput)) as $line) {
                if ($line !== '') {
                    $messages[] = $line;
                }
            }

            $messages[] = 'Migrations complete.';
        } catch (Throwable $e) {
            return ['ok' => false, 'messages' => array_merge($messages, [$e->getMessage()])];
        }
    }

    // Seed initial data
    $messages[] = 'Seeding initial data...';

    try {
        $seedScript = $root . '/docker/seed-initial.php';

        if (!file_exists($seedScript)) {
            return ['ok' => false, 'messages' => array_merge($messages, ['seed-initial.php not found'])];
        }

        require $seedScript;
        $messages[] = 'Seed complete.';
    } catch (Throwable $e) {
        return ['ok' => false, 'messages' => array_merge($messages, ['Seed error: ' . $e->getMessage()])];
    }

    return ['ok' => true, 'messages' => $messages];
}

// ── Process POST ─────────────────────────────────────────────────────────────

$setupResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        // Validate DB settings
        $dbAdapter = post('db_adapter', 'sqlite');

        if ($dbAdapter === 'mysql') {
            if (post('db_host') === '') {
                $errors[] = 'DB host is required for MySQL.';
            }

            if (post('db_name') === '') {
                $errors[] = 'DB name is required for MySQL.';
            }

            if (post('db_user') === '') {
                $errors[] = 'DB user is required for MySQL.';
            }
        }

        if (empty($errors)) {
            $_SESSION['db'] = [
                'adapter'  => $dbAdapter,
                'host'     => post('db_host', '127.0.0.1'),
                'port'     => post('db_port', '3306'),
                'name'     => post('db_name', 'var/nene_vault.sqlite'),
                'user'     => post('db_user'),
                'password' => post('db_password'),
            ];
            $step = 3;
        }
    } elseif ($step === 3) {
        // Validate app settings
        $adminEmail    = post('admin_email', 'admin@example.com');
        $adminPassword = post('admin_password');
        $orgName       = post('org_name', 'My Organization');
        $orgSlug       = post('org_slug', 'default');

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid admin email address.';
        }

        if (strlen($adminPassword) < 8) {
            $errors[] = 'Admin password must be at least 8 characters.';
        }

        if (!preg_match('/^[a-z0-9-]+$/', $orgSlug)) {
            $errors[] = 'Organization slug must be lowercase letters, digits, and hyphens only.';
        }

        if (empty($errors)) {
            $db = $_SESSION['db'] ?? [];
            $jwtSecret = post('jwt_secret') ?: EnvironmentWriter::generateSecret(32);
            $storagePath = post('storage_path', 'storage/vault');

            $envValues = InstallEnvironment::values(
                jwtSecret: $jwtSecret,
                storagePath: $storagePath,
                orgSlug: $orgSlug,
                orgName: $orgName,
                adminEmail: $adminEmail,
                adminPassword: $adminPassword,
                db: $db,
            );

            try {
                // EnvironmentWriter restricts the file to 0640 (fail-closed) and escapes
                // values, so the DB password / JWT secret are neither world-readable nor
                // able to inject extra .env lines.
                (new EnvironmentWriter())->write(envPath(), $envValues);

                $setupResult = runSetup();

                if ($setupResult['ok']) {
                    $step = 5;
                } else {
                    $step = 4;
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to write .env file: ' . $e->getMessage();
            }
        }
    }
}

// ── UI ────────────────────────────────────────────────────────────────────────

$requirements = ($step === 1) ? checkRequirements() : [];
$allRequirementsMet = empty(array_filter($requirements, static fn ($c) => !$c['ok']));

$prefilledJwtSecret = randomSecret();

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NeNe Vault — Installer</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font: 15px/1.6 system-ui, sans-serif; background: #f4f5f7; color: #1a1a2e; }
.wrap { max-width: 640px; margin: 48px auto; padding: 0 16px 80px; }
h1 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
.subtitle { color: #666; font-size: 13px; margin-bottom: 32px; }
.card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 28px 32px; margin-bottom: 20px; }
h2 { font-size: 16px; font-weight: 600; margin-bottom: 16px; }
.step-bar { display: flex; gap: 8px; margin-bottom: 28px; }
.step-bar span { flex: 1; height: 4px; border-radius: 2px; background: #e0e0e0; }
.step-bar span.done { background: #22c55e; }
.step-bar span.active { background: #3b82f6; }
label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 4px; color: #333; }
input, select { width: 100%; padding: 8px 10px; border: 1px solid #d0d0d0; border-radius: 5px; font-size: 14px; }
input:focus, select:focus { outline: 2px solid #3b82f6; border-color: #3b82f6; }
.field { margin-bottom: 16px; }
.hint { font-size: 12px; color: #888; margin-top: 3px; }
.btn { display: inline-block; padding: 10px 20px; background: #3b82f6; color: #fff; border: none; border-radius: 5px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
.btn:hover { background: #2563eb; }
.btn-secondary { background: #e5e7eb; color: #374151; }
.btn-secondary:hover { background: #d1d5db; }
.check { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 13px; }
.check .ok { color: #22c55e; }
.check .fail { color: #ef4444; }
.errors { background: #fef2f2; border: 1px solid #fecaca; border-radius: 5px; padding: 12px 16px; margin-bottom: 16px; }
.errors p { color: #dc2626; font-size: 13px; }
.logs { background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 5px; padding: 12px; font: 12px/1.5 monospace; max-height: 200px; overflow-y: auto; margin-bottom: 16px; }
.success-icon { font-size: 48px; text-align: center; margin-bottom: 16px; }
.warning { background: #fffbeb; border: 1px solid #fde68a; border-radius: 5px; padding: 10px 14px; font-size: 13px; color: #92400e; margin-top: 12px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>NeNe Vault Installer</h1>
  <p class="subtitle">v<?= INSTALLER_VERSION ?> — Self-hosted received-document archive</p>

  <div class="step-bar">
    <?php foreach ([1, 2, 3, 5] as $s): ?>
      <span class="<?= $s < $step ? 'done' : ($s === $step ? 'active' : '') ?>"></span>
    <?php endforeach ?>
  </div>

<?php if ($step === 1): ?>
  <div class="card">
    <h2>Step 1: Requirements Check</h2>
    <?php foreach ($requirements as $check): ?>
      <div class="check">
        <span class="<?= $check['ok'] ? 'ok' : 'fail' ?>"><?= $check['ok'] ? '✓' : '✗' ?></span>
        <span><?= h($check['label']) ?></span>
        <span style="color:#888;margin-left:auto;font-size:12px"><?= h($check['detail']) ?></span>
      </div>
    <?php endforeach ?>
    <?php if (!$allRequirementsMet): ?>
      <p style="margin-top:16px;color:#ef4444;font-size:13px">
        Fix the items marked ✗ before continuing.
      </p>
    <?php else: ?>
      <form method="post" action="install.php?step=2" style="margin-top:20px">
        <input type="hidden" name="step" value="2">
        <button type="submit" class="btn">Continue →</button>
      </form>
    <?php endif ?>
  </div>

<?php elseif ($step === 2): ?>
  <div class="card">
    <h2>Step 2: Database</h2>
    <?php if (!empty($errors)): ?>
      <div class="errors"><?php foreach ($errors as $e): ?><p>• <?= h($e) ?></p><?php endforeach ?></div>
    <?php endif ?>
    <form method="post" action="install.php">
      <input type="hidden" name="step" value="2">
      <div class="field">
        <label>Database adapter</label>
        <select name="db_adapter" id="db_adapter" onchange="toggleMysql(this.value)">
          <option value="sqlite" <?= post('db_adapter', 'sqlite') === 'sqlite' ? 'selected' : '' ?>>SQLite (recommended for small installs)</option>
          <option value="mysql" <?= post('db_adapter') === 'mysql' ? 'selected' : '' ?>>MySQL</option>
        </select>
      </div>
      <div id="sqlite_fields">
        <div class="field">
          <label>SQLite file path</label>
          <input type="text" name="db_name" value="<?= h(post('db_name', 'var/nene_vault.sqlite')) ?>">
          <p class="hint">Relative to project root, or an absolute path.</p>
        </div>
      </div>
      <div id="mysql_fields" style="display:none">
        <div class="field"><label>Host</label><input type="text" name="db_host" value="<?= h(post('db_host', '127.0.0.1')) ?>"></div>
        <div class="field"><label>Port</label><input type="text" name="db_port" value="<?= h(post('db_port', '3306')) ?>"></div>
        <div class="field"><label>Database name</label><input type="text" name="db_name" id="db_name_mysql" value="<?= h(post('db_name', 'nene_vault')) ?>"></div>
        <div class="field"><label>User</label><input type="text" name="db_user" value="<?= h(post('db_user')) ?>"></div>
        <div class="field"><label>Password</label><input type="password" name="db_password" value="<?= h(post('db_password')) ?>"></div>
      </div>
      <button type="submit" class="btn">Continue →</button>
    </form>
  </div>
  <script>
  function toggleMysql(v) {
    document.getElementById('sqlite_fields').style.display = v === 'sqlite' ? '' : 'none';
    document.getElementById('mysql_fields').style.display = v === 'mysql' ? '' : 'none';
  }
  toggleMysql(document.getElementById('db_adapter').value);
  </script>

<?php elseif ($step === 3): ?>
  <div class="card">
    <h2>Step 3: Application Settings</h2>
    <?php if (!empty($errors)): ?>
      <div class="errors"><?php foreach ($errors as $e): ?><p>• <?= h($e) ?></p><?php endforeach ?></div>
    <?php endif ?>
    <form method="post" action="install.php">
      <input type="hidden" name="step" value="3">
      <div class="field">
        <label>JWT secret key</label>
        <input type="text" name="jwt_secret" value="<?= h(post('jwt_secret', $prefilledJwtSecret)) ?>">
        <p class="hint">A random 32+ character string. Keep it secret — changing it invalidates all sessions.</p>
      </div>
      <div class="field">
        <label>File storage path</label>
        <input type="text" name="storage_path" value="<?= h(post('storage_path', 'storage/vault')) ?>">
        <p class="hint">Where uploaded PDFs and images are stored. Relative to project root or absolute.</p>
      </div>
      <div class="field">
        <label>Organization name</label>
        <input type="text" name="org_name" value="<?= h(post('org_name', 'My Organization')) ?>">
      </div>
      <div class="field">
        <label>Organization slug</label>
        <input type="text" name="org_slug" value="<?= h(post('org_slug', 'default')) ?>">
        <p class="hint">Lowercase letters, digits, hyphens. Used for tenant resolution.</p>
      </div>
      <div class="field">
        <label>Admin email</label>
        <input type="email" name="admin_email" value="<?= h(post('admin_email', 'admin@example.com')) ?>">
      </div>
      <div class="field">
        <label>Admin password</label>
        <input type="password" name="admin_password" placeholder="Min 8 characters" value="<?= h(post('admin_password')) ?>">
      </div>
      <button type="submit" class="btn">Install →</button>
    </form>
  </div>

<?php elseif ($step === 4): ?>
  <div class="card">
    <h2>Setup failed</h2>
    <?php if ($setupResult !== null): ?>
      <div class="logs">
        <?php foreach ($setupResult['messages'] as $msg): ?>
          <?= h($msg) ?><br>
        <?php endforeach ?>
      </div>
    <?php endif ?>
    <p style="color:#ef4444;font-size:13px;margin-bottom:16px">
      Review the errors above. Fix the issue and try again.
    </p>
    <a href="install.php?step=3" class="btn btn-secondary">← Back</a>
  </div>

<?php elseif ($step === 5): ?>
  <?php
    // Attempt to delete installer
    $deleted = @unlink(__FILE__);
  ?>
  <div class="card">
    <div class="success-icon">✅</div>
    <h2 style="text-align:center">Installation complete!</h2>
    <p style="text-align:center;color:#666;margin-top:8px;margin-bottom:20px">
      NeNe Vault is ready. Log in with the admin credentials you set.
    </p>

    <?php if ($setupResult !== null): ?>
      <div class="logs">
        <?php foreach ($setupResult['messages'] as $msg): ?>
          <?= h($msg) ?><br>
        <?php endforeach ?>
      </div>
    <?php endif ?>

    <div style="text-align:center;margin-top:20px">
      <a href="public_html/" class="btn">Open NeNe Vault →</a>
    </div>

    <?php if (!$deleted): ?>
      <div class="warning">
        ⚠ Could not delete <code>install.php</code> automatically.
        <strong>Delete it manually</strong> before using the system in production.
      </div>
    <?php else: ?>
      <p style="font-size:12px;color:#888;text-align:center;margin-top:12px">
        install.php has been deleted.
      </p>
    <?php endif ?>
  </div>

<?php endif ?>

</div>
</body>
</html>
