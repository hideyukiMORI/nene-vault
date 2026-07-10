<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real `tools/sweep-demo.php` as a subprocess with the host timezone
 * pinned to Asia/Tokyo — the production (HETEML) configuration — and to UTC.
 *
 * Vault writes `organizations.created_at` with `date()` in the host's default
 * timezone (unlike clear/deal, which write UTC), so the sweep must parse it
 * with that same default timezone (#143): each scenario here writes
 * `created_at` in the timezone the subprocess sweeps under and expects the
 * same outcome — fresh orgs survive, genuinely expired ones are reaped, and
 * the reaped org's document storage tree goes with it. (A UTC-forced parse on
 * the JST host — the transplanted clear #280 / deal #72 shape — read every
 * org as 9 h in the future and stretched the 3 h TTL to 12 h.)
 */
final class SweepDemoScriptTest extends TestCase
{
    private string $dbPath;
    private string $storageRoot;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('vault-sweep-script-', true) . '.sqlite';
        $this->storageRoot = sys_get_temp_dir() . '/' . uniqid('vault-sweep-storage-', true);
        mkdir($this->storageRoot, 0o775, true);

        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Minimal schema: organizations + every table the reaper touches.
        $this->pdo->exec('CREATE TABLE organizations (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
            external_id TEXT, custom_domain TEXT, plan TEXT NOT NULL DEFAULT "free",
            is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
        foreach (['document_versions', 'vault_documents', 'vault_settings', 'users'] as $table) {
            $this->pdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER)");
        }
        $this->pdo->exec('CREATE TABLE audit_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id TEXT, organization_id INTEGER)');
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
        exec('rm -rf ' . escapeshellarg($this->storageRoot));
    }

    public function test_fresh_org_survives_and_expired_org_is_reaped_on_a_jst_host(): void
    {
        // created_at written the way the app writes it on a JST host: date()
        // in Asia/Tokyo — the sweep subprocess below runs under the same TZ.
        $this->insertOrg(1, 'demo-fresh', $this->localStamp('Asia/Tokyo', 60));               // 1 minute old
        $this->insertOrg(2, 'demo-expired', $this->localStamp('Asia/Tokyo', 4 * 3600));       // past the 3h TTL
        $this->insertOrg(3, 'ayane', $this->localStamp('Asia/Tokyo', 30 * 24 * 3600));        // fixed showcase org (no prefix)

        // Child rows + a storage tree for the org that must be reaped.
        $this->pdo->exec('INSERT INTO users (organization_id) VALUES (2)');
        $this->pdo->exec("INSERT INTO audit_events (entity_type, entity_id, organization_id) VALUES ('organization', '2', NULL)");
        mkdir($this->storageRoot . '/vault/2', 0o775, true);
        file_put_contents($this->storageRoot . '/vault/2/doc.pdf', 'x');

        $output = $this->runSweep('Asia/Tokyo');

        self::assertStringContainsString('2 org(s) total, 1 expired, 0 overflow, 1 reaped', $output);
        self::assertSame(['ayane', 'demo-fresh'], $this->remainingSlugs());
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM users WHERE organization_id = 2'));
        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM audit_events WHERE entity_id = '2'"));
        self::assertDirectoryDoesNotExist($this->storageRoot . '/vault/2');
    }

    public function test_behaves_identically_on_a_utc_host_and_is_idempotent(): void
    {
        $this->insertOrg(1, 'demo-fresh', $this->localStamp('UTC', 60));
        $this->insertOrg(2, 'demo-expired', $this->localStamp('UTC', 4 * 3600));

        $output = $this->runSweep('UTC');
        self::assertStringContainsString('2 org(s) total, 1 expired, 0 overflow, 1 reaped', $output);
        self::assertSame(['demo-fresh'], $this->remainingSlugs());

        // Second run: the already-reaped org is gone; sweeping again is a no-op.
        $again = $this->runSweep('UTC');
        self::assertStringContainsString('1 org(s) total, 0 expired, 0 overflow, 0 reaped', $again);
        self::assertSame(['demo-fresh'], $this->remainingSlugs());
    }

    /** A created_at string as the app's date() would write it in $timezone, $secondsAgo in the past. */
    private function localStamp(string $timezone, int $secondsAgo): string
    {
        return (new \DateTimeImmutable('@' . (time() - $secondsAgo)))
            ->setTimezone(new \DateTimeZone($timezone))
            ->format('Y-m-d H:i:s');
    }

    private function insertOrg(int $id, string $slug, string $createdAt): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO organizations (id, slug, name, created_at, updated_at) VALUES (?, ?, 'Demo', ?, ?)",
        );
        $stmt->execute([$id, $slug, $createdAt, $createdAt]);
    }

    private function countRows(string $sql): int
    {
        $stmt = $this->pdo->query($sql);
        self::assertNotFalse($stmt);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<string> remaining org slugs, sorted */
    private function remainingSlugs(): array
    {
        $stmt = $this->pdo->query('SELECT slug FROM organizations ORDER BY slug');
        self::assertNotFalse($stmt);

        /** @var list<string> */
        return array_map(strval(...), $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function runSweep(string $timezone): string
    {
        $root = dirname(__DIR__, 2);
        $command = [
            PHP_BINARY,
            '-d', 'date.timezone=' . $timezone,
            $root . '/tools/sweep-demo.php',
        ];

        // Explicit env wins over the repo's .env (EnvFileLoader: real env wins).
        $env = [
            'APP_ENV' => 'test',
            'DB_ADAPTER' => 'sqlite',
            'DB_NAME' => $this->dbPath,
            'DEMO_TTL_HOURS' => '3',
            'DEMO_MAX_ORGS' => '200',
            'NENE_VAULT_STORAGE_PATH' => $this->storageRoot,
            'PATH' => (string) getenv('PATH'),
        ];

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, 'sweep-demo.php failed: ' . $stderr);

        return $stdout;
    }
}
