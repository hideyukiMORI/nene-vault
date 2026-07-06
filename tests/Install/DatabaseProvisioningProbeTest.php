<?php

declare(strict_types=1);

namespace NeneVault\Tests\Install;

use Nene2\Install\ReInstallationGuard;
use NeneVault\Install\DatabaseProvisioningProbe;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Proves the re-installation guard refuses a second run: a present `.installed`
 * marker blocks outright, and — when the marker is missing — the DB probe blocks
 * once the target already holds users, while a genuinely fresh target is allowed.
 */
final class DatabaseProvisioningProbeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/vault-probe-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/var', 0770, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,var/,*/}*', GLOB_BRACE) ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . '/var');
        @rmdir($this->dir);
    }

    private function makeSqlite(bool $withUser): string
    {
        $path = $this->dir . '/var/nene_vault.sqlite';
        $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');

        if ($withUser) {
            $pdo->exec("INSERT INTO users (email) VALUES ('admin@example.com')");
        }

        return $path;
    }

    public function test_fresh_target_without_env_is_not_provisioned(): void
    {
        $probe = new DatabaseProvisioningProbe([], $this->dir);
        self::assertFalse($probe->isProvisioned());
    }

    public function test_sqlite_without_users_is_not_provisioned(): void
    {
        $this->makeSqlite(withUser: false);
        $probe = new DatabaseProvisioningProbe(
            ['DB_ADAPTER' => 'sqlite', 'DB_NAME' => 'var/nene_vault.sqlite'],
            $this->dir,
        );

        self::assertFalse($probe->isProvisioned());
    }

    public function test_sqlite_with_users_is_provisioned(): void
    {
        $this->makeSqlite(withUser: true);
        $probe = new DatabaseProvisioningProbe(
            ['DB_ADAPTER' => 'sqlite', 'DB_NAME' => 'var/nene_vault.sqlite'],
            $this->dir,
        );

        self::assertTrue($probe->isProvisioned());
    }

    public function test_missing_sqlite_file_is_not_provisioned(): void
    {
        $probe = new DatabaseProvisioningProbe(
            ['DB_ADAPTER' => 'sqlite', 'DB_NAME' => 'var/does-not-exist.sqlite'],
            $this->dir,
        );

        self::assertFalse($probe->isProvisioned());
    }

    public function test_guard_blocks_when_marker_present(): void
    {
        $marker = $this->dir . '/var/.installed';
        $guard = new ReInstallationGuard($marker, new DatabaseProvisioningProbe([], $this->dir));

        self::assertFalse($guard->isBlocked());

        $guard->markInstalled('2026-07-06T00:00:00+00:00');

        self::assertTrue($guard->isBlocked());
        self::assertSame('marker_present', $guard->blockedReason());
    }

    public function test_guard_blocks_via_probe_when_marker_absent_but_db_provisioned(): void
    {
        $this->makeSqlite(withUser: true);
        $guard = new ReInstallationGuard(
            $this->dir . '/var/.installed',
            new DatabaseProvisioningProbe(['DB_ADAPTER' => 'sqlite', 'DB_NAME' => 'var/nene_vault.sqlite'], $this->dir),
        );

        self::assertTrue($guard->isBlocked());
        self::assertSame('database_provisioned', $guard->blockedReason());
    }
}
