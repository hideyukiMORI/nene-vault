<?php

declare(strict_types=1);

/**
 * Disposable-demo sweep (`Nene2\Demo` consumer, #141): expires demo orgs past
 * their TTL and reaps overflow beyond DEMO_MAX_ORGS. Thin by design — org
 * selection here (the product owns its schema), TTL/overflow policy in the
 * framework {@see \Nene2\Demo\DisposableDemoSweeper}, teardown (DB rows AND
 * the org's document storage tree) in {@see \NeneVault\Demo\DemoOrgReaper}.
 * Also prunes stale per-IP rate-limit state files. Run hourly from cron.
 *
 * Only orgs whose slug carries the demo prefix (`demo-…`) are ever selected;
 * the fixed showcase org (no hyphenated prefix) and real tenants are
 * untouched. Idempotent: running it twice, or concurrently with a reap that
 * already removed an org, is success.
 *
 * Usage: php tools/sweep-demo.php
 * Config-boundary entry point: env wiring mirrors tools/seed-demo.php.
 */

use Nene2\Config\ConfigLoader;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Demo\DemoOrgRecord;
use Nene2\Demo\DisposableDemoSweeper;
use Nene2\Http\UtcClock;
use NeneVault\Demo\DemoOrgReaper;
use NeneVault\Demo\DemoServiceProvider;
use NeneVault\Support\EnvFileLoader;

require_once __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'This script must be run from the command line.' . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__);
EnvFileLoader::load($root);
$config = (new ConfigLoader($root))->load();

$query = new PdoDatabaseQueryExecutor(new PdoConnectionFactory($config->database));

// ESCAPE '|' — a backslash escape char is parsed differently by MySQL string
// literals vs SQLite (clear #277); '|' needs no literal-level escaping anywhere.
$likePrefix = str_replace(['|', '%', '_'], ['||', '|%', '|_'], $config->demo->slugPrefix) . '%';
$rows = $query->fetchAll(
    "SELECT id, created_at FROM organizations WHERE slug LIKE ? ESCAPE '|'",
    [$likePrefix],
);

$records = [];
foreach ($rows as $row) {
    $records[] = new DemoOrgRecord(
        orgId: (int) $row['id'],
        // created_at is written in UTC (the app-wide UtcClock). Parse it as
        // UTC explicitly: on a host whose default timezone is ahead of UTC
        // (production runs Asia/Tokyo) a bare parse would read every fresh
        // org as hours old and expire it on the spot (clear #280, deal #72).
        createdAt: new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')),
    );
}

// Storage root: mirror DocumentServiceProvider's resolution (config boundary) —
// the reaper deletes the org's document tree alongside its rows.
$storageRoot = (string) (getenv('NENE_VAULT_STORAGE_PATH') ?: 'storage/vault');
if (!str_starts_with($storageRoot, '/')) {
    $storageRoot = $root . '/' . $storageRoot;
}

$report = (new DisposableDemoSweeper(
    $config->demo,
    new DemoOrgReaper($query, $storageRoot),
    new UtcClock(),
))->sweep($records);

// Prune stale per-IP throttle state (fully expired windows only).
$pruned = 0;
$cutoff = time() - DemoServiceProvider::THROTTLE_WINDOW_SECONDS * 2;
foreach (glob($root . '/var/rate-limits/*.json') ?: [] as $file) {
    $mtime = @filemtime($file);
    if ($mtime !== false && $mtime < $cutoff && @unlink($file)) {
        $pruned++;
    }
}

fwrite(STDOUT, sprintf(
    "demo sweep: %d org(s) total, %d expired, %d overflow, %d reaped, %d throttle file(s) pruned\n",
    count($records),
    count($report->expiredOrgIds),
    count($report->overflowOrgIds),
    count($report->reapedOrgIds),
    $pruned,
));

exit(0);
