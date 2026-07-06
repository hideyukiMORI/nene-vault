<?php

declare(strict_types=1);

namespace NeneVault\Tests\Install;

use Dotenv\Dotenv;
use Nene2\Install\EnvironmentWriter;
use NeneVault\Install\InstallEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * Proves the installer's `.env` is written through the NENE2 EnvironmentWriter:
 * restricted to 0640 (not world-readable) and with values escaped so a password
 * containing shell/`.env` metacharacters cannot leak or inject extra lines.
 */
final class InstallEnvironmentTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/vault-install-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0770, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function test_env_file_is_not_world_readable(): void
    {
        $path = $this->dir . '/.env';

        (new EnvironmentWriter())->write($path, InstallEnvironment::values(
            jwtSecret: EnvironmentWriter::generateSecret(32),
            storagePath: 'storage/vault',
            orgSlug: 'default',
            orgName: 'Acme',
            adminEmail: 'admin@example.com',
            adminPassword: 'p@ss word',
            db: ['adapter' => 'sqlite', 'name' => 'var/nene_vault.sqlite'],
        ));

        $perms = fileperms($path) & 0777;
        self::assertSame(0, $perms & 0007, 'The .env file must not be world-readable.');
        self::assertSame(0640, $perms);
    }

    public function test_password_with_metacharacters_round_trips_without_injection(): void
    {
        $path = $this->dir . '/.env';
        $nasty = 'a"b $c #d e';

        (new EnvironmentWriter())->write($path, InstallEnvironment::values(
            jwtSecret: 'deadbeef',
            storagePath: 'storage/vault',
            orgSlug: 'default',
            orgName: 'Acme',
            adminEmail: 'admin@example.com',
            adminPassword: 'x',
            db: ['adapter' => 'mysql', 'host' => 'db', 'port' => '3306', 'name' => 'vault', 'user' => 'u', 'password' => $nasty],
        ));

        // Parse the file back through the exact loader NENE2 uses (vlucas/phpdotenv);
        // the value must be identical to what we wrote — escaping is transparent.
        $parsed = Dotenv::createArrayBacked($this->dir, '.env')->load();
        self::assertSame($nasty, $parsed['DB_PASSWORD'] ?? null, 'Password must round-trip verbatim.');

        // A `$` or `"` in a value must be escaped inside quotes, never spilling into a new key.
        $raw = (string) file_get_contents($path);
        self::assertStringContainsString('DB_PASSWORD="a\\"b \\$c #d e"', $raw);
    }

    public function test_jwt_secret_is_generated_as_hex(): void
    {
        $secret = EnvironmentWriter::generateSecret(32);

        self::assertSame(64, strlen($secret));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);
    }
}
