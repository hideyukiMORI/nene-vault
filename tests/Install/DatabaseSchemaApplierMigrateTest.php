<?php

declare(strict_types=1);

namespace NeneVault\Tests\Install;

use Nene2\Install\DatabaseSchemaApplier;
use PDO;
use Phinx\Config\Config;
use PHPUnit\Framework\TestCase;

/**
 * Proves the installer's migrate path runs the real phinx migrations in-process
 * via {@see DatabaseSchemaApplier} (phinx's Manager API), with no shell-out and no
 * dev-only dependency. If phinx were still in `require-dev` a `--no-dev` production
 * build would not autoload it and this test would fail to construct the applier.
 */
final class DatabaseSchemaApplierMigrateTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/vault-migrate-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0770, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function test_phinx_migrations_apply_in_process(): void
    {
        $dbBase = $this->dir . '/vault';

        $output = (new DatabaseSchemaApplier())->apply(new Config([
            'paths' => ['migrations' => dirname(__DIR__, 2) . '/database/migrations'],
            'environments' => [
                'default_environment' => 'install',
                'install' => [
                    'adapter' => 'sqlite',
                    'name' => $dbBase,
                    'suffix' => '.sqlite',
                ],
            ],
            'version_order' => 'creation',
        ]));

        self::assertStringContainsString('CreateUsersTable', $output);

        // Re-open the migrated database and confirm the schema landed.
        $pdo = new PDO('sqlite:' . $dbBase . '.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        self::assertNotFalse($statement);
        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);

        self::assertContains('users', $tables);
        self::assertContains('organizations', $tables);
        self::assertContains('vault_documents', $tables);
    }
}
