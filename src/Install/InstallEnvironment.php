<?php

declare(strict_types=1);

namespace NeneVault\Install;

/**
 * Builds the ordered `KEY => value` map the web installer writes to `.env`.
 *
 * Extracted out of `install.php` so the exact set/order of persisted keys is a
 * single, unit-tested seam. The map is handed to {@see \Nene2\Install\EnvironmentWriter},
 * which restricts the file to 0640 (fail-closed) and escapes the values — so a DB
 * password or JWT secret containing spaces, quotes, `#` or `$` survives a round-trip
 * and no value can inject an extra `.env` line.
 */
final class InstallEnvironment
{
    /**
     * @param array{adapter?: string, host?: string, port?: string, name?: string, user?: string, password?: string} $db
     *
     * @return array<string, string>
     */
    public static function values(
        string $jwtSecret,
        string $storagePath,
        string $orgSlug,
        string $orgName,
        string $adminEmail,
        string $adminPassword,
        array $db,
    ): array {
        $adapter = $db['adapter'] ?? 'sqlite';

        return [
            'APP_ENV'                     => 'production',
            'APP_DEBUG'                   => 'false',
            'APP_NAME'                    => 'NeNe Vault',
            'NENE2_LOCAL_JWT_SECRET'      => $jwtSecret,
            'PROBLEM_DETAILS_BASE_URL'    => 'https://nene-vault.dev/problems/',
            'NENE_VAULT_STORAGE_PATH'     => $storagePath,
            'NENE_VAULT_MAX_FILE_SIZE_MB' => '20',
            'TENANT_RESOLUTION'           => 'single',
            'ORG_SLUG'                    => $orgSlug,
            'DB_ENV'                      => 'production',
            'DB_ADAPTER'                  => $adapter,
            'DB_HOST'                     => $db['host'] ?? '127.0.0.1',
            'DB_PORT'                     => $db['port'] ?? '3306',
            'DB_NAME'                     => $adapter === 'sqlite'
                ? (($db['name'] ?? '') !== '' ? (string) $db['name'] : 'var/nene_vault.sqlite')
                : ($db['name'] ?? 'nene_vault'),
            'DB_USER'                     => $db['user'] ?? '',
            'DB_PASSWORD'                 => $db['password'] ?? '',
            'DB_CHARSET'                  => 'utf8mb4',
            'ORG_NAME'                    => $orgName,
            'ADMIN_EMAIL'                 => $adminEmail,
            'ADMIN_PASSWORD'              => $adminPassword,
        ];
    }
}
