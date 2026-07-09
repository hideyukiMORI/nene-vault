<?php

declare(strict_types=1);

/**
 * Demo-org seeder (#118): drop and re-seed the fixed demo organization with a
 * T-relative received-document dataset in one command — generated invoice
 * PDFs from fictional Japanese vendors, spread over the past 12 months, with
 * void→restore history. Rerun = reset (DB rows AND the org's storage tree).
 *
 * The heavy lifting lives in org_id-parameterized classes
 * ({@see \NeneVault\Demo\DemoDataSeeder}, {@see \NeneVault\Demo\DemoOrgReaper})
 * so next round's `Nene2\Demo` disposable-org adoption reuses them unchanged
 * (owner decision 07-09).
 *
 * DESTRUCTIVE for the demo org only: every DB row and stored file belonging
 * to the org with the given slug is deleted first. Do not point this at a
 * database holding real records.
 *
 * Usage:
 *   php tools/seed-demo.php --force \
 *     --admin-password '…12+ chars…' --viewer-password '…12+ chars…'
 *   NENE_VAULT_DEMO_ADMIN_PASSWORD=… NENE_VAULT_DEMO_VIEWER_PASSWORD=… \
 *     php tools/seed-demo.php --force
 *
 * Reads .env like the app (ConfigLoader via RuntimeContainerFactory). With
 * TENANT_RESOLUTION=single the app serves the org named by ORG_SLUG — set
 * ORG_SLUG to the demo slug (default `demo`) on a demo deployment.
 */

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\UtcClock;
use NeneVault\Demo\DemoDataSeeder;
use NeneVault\Demo\DemoOrgReaper;
use NeneVault\Document\RestoreDocumentUseCaseInterface;
use NeneVault\Document\UploadDocumentUseCaseInterface;
use NeneVault\Document\VoidDocumentUseCaseInterface;
use NeneVault\Http\RuntimeContainerFactory;
use NeneVault\Support\EnvFileLoader;
use NeneVault\Organization\CreateOrganizationInput;
use NeneVault\Organization\CreateOrganizationUseCaseInterface;
use NeneVault\Organization\OrganizationRepositoryInterface;
use NeneVault\User\CreateUserUseCaseInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'This script must be run from the command line.' . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__);
EnvFileLoader::load($root);

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

// --- Parse arguments (--key value or --key=value) ------------------------------
$rawArgv = $_SERVER['argv'] ?? [];
$args = array_slice(is_array($rawArgv) ? array_values($rawArgv) : [], 1);
$count = count($args);
/** @var array<string, string> $opts */
$opts = [];
for ($i = 0; $i < $count; $i++) {
    $arg = (string) $args[$i];
    if ($arg === '-h' || $arg === '--help') {
        fwrite(STDOUT, implode(PHP_EOL, [
            'Drop and re-seed the fixed demo organization with T-relative demo documents.',
            '',
            'Usage:',
            '  php tools/seed-demo.php --force --admin-password PASS --viewer-password PASS',
            '',
            'Options:',
            '  --org-slug SLUG        Demo org slug (default: demo — match ORG_SLUG in .env)',
            '  --org-name NAME        Demo org display name (default: デモ商事株式会社)',
            '  --admin-email EMAIL    Hand-out admin login (default: demo-admin@nene-vault.dev)',
            '  --viewer-email EMAIL   Hand-out viewer login (default: demo-viewer@nene-vault.dev)',
            '  --admin-password PASS  Admin password (default: NENE_VAULT_DEMO_ADMIN_PASSWORD env)',
            '  --viewer-password PASS Viewer password (default: NENE_VAULT_DEMO_VIEWER_PASSWORD env)',
            '  --force                Skip the interactive confirmation (required for cron)',
        ]) . PHP_EOL);
        exit(0);
    }
    if (!str_starts_with($arg, '--')) {
        $fail(sprintf('Unexpected argument: %s (see --help)', $arg));
    }
    $body = substr($arg, 2);
    if (str_contains($body, '=')) {
        [$key, $value] = explode('=', $body, 2);
    } else {
        $key = $body;
        $next = $i + 1 < $count ? (string) $args[$i + 1] : '';
        if ($next !== '' && !str_starts_with($next, '--')) {
            $value = $next;
            $i++;
        } else {
            $value = '';
        }
    }
    $opts[$key] = $value;
}

$env = static fn (string $key, string $default = ''): string => (string) (getenv($key) ?: $default);
$optOr = static function (string $key, string $default) use ($opts): string {
    $value = trim($opts[$key] ?? '');

    return $value !== '' ? $value : $default;
};

$orgSlug = $optOr('org-slug', 'demo');
$orgName = $optOr('org-name', 'デモ商事株式会社');
$adminEmail = $optOr('admin-email', 'demo-admin@nene-vault.dev');
$viewerEmail = $optOr('viewer-email', 'demo-viewer@nene-vault.dev');
$adminPassword = $opts['admin-password'] ?? $env('NENE_VAULT_DEMO_ADMIN_PASSWORD');
$viewerPassword = $opts['viewer-password'] ?? $env('NENE_VAULT_DEMO_VIEWER_PASSWORD');

if (strlen($adminPassword) < 12 || strlen($viewerPassword) < 12) {
    $fail('Demo passwords are required and must be at least 12 characters. '
        . 'Pass --admin-password/--viewer-password or set NENE_VAULT_DEMO_ADMIN_PASSWORD / '
        . 'NENE_VAULT_DEMO_VIEWER_PASSWORD (keeps credentials stable across resets).');
}

// --- Confirm the destructive reset ---------------------------------------------
if (!isset($opts['force'])) {
    if (!stream_isatty(STDIN)) {
        $fail('Refusing to reset without confirmation. Pass --force (e.g. from cron).');
    }
    fwrite(STDOUT, sprintf(
        "This DELETES every record and stored file of organization '%s' and reseeds\n"
        . 'fresh demo documents. Type the org slug to continue: ',
        $orgSlug,
    ));
    $answer = trim((string) fgets(STDIN));
    if ($answer !== $orgSlug) {
        $fail('Aborted.');
    }
}

// --- Container (same composition root as the app / install.php) ----------------
$container = (new RuntimeContainerFactory($root))->create();

/** @template T @param class-string<T> $id @return T */
$get = static function (string $id) use ($container) {
    $service = $container->get($id);
    if (!$service instanceof $id) {
        throw new LogicException($id . ' service is invalid.');
    }

    return $service;
};

$query = $get(DatabaseQueryExecutorInterface::class);
$organizations = $get(OrganizationRepositoryInterface::class);

// Storage root: mirror DocumentServiceProvider's resolution (config boundary).
$storageRoot = $env('NENE_VAULT_STORAGE_PATH', 'storage/vault');
if (!str_starts_with($storageRoot, '/')) {
    $storageRoot = $root . '/' . $storageRoot;
}

// --- 1) Reap the previous demo org (DB rows + storage tree) --------------------
$existing = $organizations->findBySlug($orgSlug);
if ($existing !== null) {
    (new DemoOrgReaper($query, $storageRoot))->reap($existing->id);
    fwrite(STDOUT, sprintf('Reaped previous demo organization #%d.', $existing->id) . PHP_EOL);
} else {
    fwrite(STDOUT, 'No previous demo organization found.' . PHP_EOL);
}

// --- 2) Recreate org + hand-out users via the app's own use cases --------------
$org = $get(CreateOrganizationUseCaseInterface::class)->execute(new CreateOrganizationInput(
    name: $orgName,
    slug: $orgSlug,
));
$createUser = $get(CreateUserUseCaseInterface::class);
$admin = $createUser->execute($adminEmail, $adminPassword, 'admin', $org->id, null);
$createUser->execute($viewerEmail, $viewerPassword, 'viewer', $org->id, null);

// --- 3) Seed the T-relative document set ----------------------------------------
$summary = (new DemoDataSeeder(
    $get(UploadDocumentUseCaseInterface::class),
    $get(VoidDocumentUseCaseInterface::class),
    $get(RestoreDocumentUseCaseInterface::class),
    $query,
    new UtcClock(), // the container registers no clock; CLI is a config boundary
))->seed($org->id, $admin->id);

fwrite(STDOUT, PHP_EOL . sprintf(
    'Seeded organization #%d (%s / %s): %d documents (%d voided, %d restored).',
    $org->id,
    $orgSlug,
    $orgName,
    $summary['documents'],
    $summary['voided'],
    $summary['restored'],
) . PHP_EOL);
fwrite(STDOUT, PHP_EOL . 'Hand-out credentials:' . PHP_EOL);
fwrite(STDOUT, sprintf('  admin : %s', $adminEmail) . PHP_EOL);
fwrite(STDOUT, sprintf('  viewer: %s', $viewerEmail) . PHP_EOL);
fwrite(STDOUT, PHP_EOL . sprintf("Reminder: TENANT_RESOLUTION=single requires ORG_SLUG=%s in .env.", $orgSlug) . PHP_EOL);

exit(0);
