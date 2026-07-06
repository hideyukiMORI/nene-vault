<?php

declare(strict_types=1);

namespace NeneVault\Install;

use Nene2\Install\ProvisioningProbe;
use PDO;
use Throwable;

/**
 * Defence-in-depth for {@see \Nene2\Install\ReInstallationGuard}: reports whether the
 * configured database already holds a provisioned Vault (at least one `users` row),
 * so a lost `.installed` marker (e.g. an ephemeral `var/` wiped on redeploy) cannot
 * let the installer overwrite `.env` or recreate the admin account.
 *
 * Reads the connection from a parsed `.env`; supports both adapters Vault ships with
 * (sqlite / mysql). Returns `false` — never throws — when there is no `.env`, the DB
 * is unreachable, or the schema is absent, so a genuinely fresh target is not mistaken
 * for a provisioned one.
 */
final class DatabaseProvisioningProbe implements ProvisioningProbe
{
    /**
     * @param array<string, string> $env       Parsed `.env` values.
     * @param string                $projectRoot Absolute path used to resolve a relative SQLite path.
     */
    public function __construct(
        private readonly array $env,
        private readonly string $projectRoot,
    ) {
    }

    public static function fromEnvFile(string $envPath, string $projectRoot): self
    {
        $env = is_file($envPath) ? (parse_ini_file($envPath) ?: []) : [];

        /** @var array<string, string> $env */
        return new self($env, $projectRoot);
    }

    public function isProvisioned(): bool
    {
        if ($this->env === []) {
            return false;
        }

        try {
            $pdo = $this->connect();

            if ($pdo === null) {
                return false;
            }

            $statement = $pdo->query('SELECT COUNT(*) FROM users');

            return $statement !== false && (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            // No env / unreachable DB / no schema yet → genuinely not provisioned.
            return false;
        }
    }

    private function connect(): ?PDO
    {
        $adapter = $this->env['DB_ADAPTER'] ?? 'sqlite';

        if ($adapter === 'mysql') {
            if (($this->env['DB_NAME'] ?? '') === '') {
                return null;
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $this->env['DB_HOST'] ?? '127.0.0.1',
                $this->env['DB_PORT'] ?? '3306',
                $this->env['DB_NAME'],
                $this->env['DB_CHARSET'] ?? 'utf8mb4',
            );

            return new PDO($dsn, $this->env['DB_USER'] ?? '', $this->env['DB_PASSWORD'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);
        }

        $dbName = $this->env['DB_NAME'] ?? 'var/nene_vault.sqlite';
        $dbPath = str_starts_with($dbName, '/') ? $dbName : $this->projectRoot . '/' . $dbName;

        if (!is_file($dbPath)) {
            // Fresh target: the SQLite file has not been created yet.
            return null;
        }

        return new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
}
