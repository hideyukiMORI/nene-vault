<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\UtcClock;
use NeneVault\Demo\DemoDataSeeder;
use NeneVault\Demo\DemoOrgReaper;
use NeneVault\Document\RestoreDocumentUseCaseInterface;
use NeneVault\Document\UploadDocumentUseCaseInterface;
use NeneVault\Document\VoidDocumentUseCaseInterface;
use NeneVault\Organization\CreateOrganizationInput;
use NeneVault\Organization\CreateOrganizationUseCaseInterface;
use NeneVault\Tests\Support\ApiTestCase;
use NeneVault\User\CreateUserUseCaseInterface;

/**
 * End-to-end lifecycle over the shared test container (#118): seed a demo org
 * through the real use cases, verify the dataset and the on-disk files, then
 * reap and verify nothing of the org remains (rows or files).
 */
final class DemoSeedLifecycleTest extends ApiTestCase
{
    private function storageRoot(): string
    {
        $root = (string) (getenv('NENE_VAULT_STORAGE_PATH') ?: 'storage/vault');

        return str_starts_with($root, '/') ? $root : dirname(__DIR__, 2) . '/' . $root;
    }

    private function query(): DatabaseQueryExecutorInterface
    {
        $query = self::bootContainer()->get(DatabaseQueryExecutorInterface::class);
        assert($query instanceof DatabaseQueryExecutorInterface);

        return $query;
    }

    private function countFor(string $table, int $orgId): int
    {
        $row = $this->query()->fetchOne("SELECT COUNT(*) AS n FROM {$table} WHERE organization_id = ?", [$orgId]);

        return is_array($row) ? (int) $row['n'] : -1;
    }

    public function test_seed_then_reap_lifecycle(): void
    {
        self::bootContainer();

        $container = self::bootContainer();
        $createOrg = $container->get(CreateOrganizationUseCaseInterface::class);
        assert($createOrg instanceof CreateOrganizationUseCaseInterface);
        $createUser = $container->get(CreateUserUseCaseInterface::class);
        assert($createUser instanceof CreateUserUseCaseInterface);
        $upload = $container->get(UploadDocumentUseCaseInterface::class);
        assert($upload instanceof UploadDocumentUseCaseInterface);
        $void = $container->get(VoidDocumentUseCaseInterface::class);
        assert($void instanceof VoidDocumentUseCaseInterface);
        $restore = $container->get(RestoreDocumentUseCaseInterface::class);
        assert($restore instanceof RestoreDocumentUseCaseInterface);

        $slug = 'demo-lifecycle-' . bin2hex(random_bytes(4));
        $org = $createOrg->execute(new CreateOrganizationInput(name: 'デモ商事株式会社', slug: $slug));
        $admin = $createUser->execute('demo-admin@' . $slug . '.example', 'lifecycle-password-1', 'admin', $org->id, null);

        $summary = (new DemoDataSeeder(
            $upload,
            $void,
            $restore,
            $this->query(),
            new UtcClock(),
        ))->seed($org->id, $admin->id);

        self::assertSame(20, $summary['documents']);
        self::assertSame(20, $this->countFor('vault_documents', $org->id));
        self::assertSame(20, $this->countFor('document_versions', $org->id));

        // One document stays voided; the two restored ones are active again.
        $row = $this->query()->fetchOne(
            "SELECT COUNT(*) AS n FROM vault_documents WHERE organization_id = ? AND status = 'voided'",
            [$org->id],
        );
        self::assertSame(1, is_array($row) ? (int) $row['n'] : -1);

        // Files exist on disk under the org's tree, and SHA-256 round-trips.
        $orgDir = $this->storageRoot() . '/vault/' . $org->id;
        self::assertDirectoryExists($orgDir);
        $version = $this->query()->fetchOne(
            'SELECT file_path, file_sha256 FROM document_versions WHERE organization_id = ? LIMIT 1',
            [$org->id],
        );
        self::assertIsArray($version);
        $absolute = $this->storageRoot() . '/' . (string) $version['file_path'];
        self::assertFileExists($absolute);
        self::assertSame((string) $version['file_sha256'], hash_file('sha256', $absolute));

        // Weak oracle (kept, demoted): a plausibility band on the aggregate.
        // Amounts are whole yen (JPY has no minor unit — naming-conventions) and
        // the line generator yields ¥33,000–¥3,300,000 per document. This is only
        // a "does the magnitude look sane" smoke test, NOT the real invariant: a
        // ×100 on a small row (e.g. ¥33,000 → ¥3,300,000) lands on the upper bound
        // and passes, and because it reads MIN/MAX only, any ×100 that does not
        // move the aggregate is invisible. Cheap smoke, not a guarantee.
        $amounts = $this->query()->fetchOne(
            'SELECT MIN(amount_cents) AS lo, MAX(amount_cents) AS hi FROM vault_documents WHERE organization_id = ?',
            [$org->id],
        );
        self::assertIsArray($amounts);
        self::assertGreaterThanOrEqual(33_000, (int) $amounts['lo']);
        self::assertLessThanOrEqual(3_300_000, (int) $amounts['hi']);

        // True invariant (the real oracle): every document's stored amount equals
        // the total *printed in that document's own PDF*. The two values reach
        // their sinks by independent paths — amount_cents via `amountCents:` (DB)
        // and the printed total via `totalYen:` (PDF bytes) — so extracting the
        // total from the actual stored PDF and comparing it to amount_cents is a
        // non-circular cross-artifact check. A ×100 on amount_cents (#136) breaks
        // it on that row at any magnitude, including the small-amount cases the
        // band above cannot see.
        $rows = $this->query()->fetchAll(
            'SELECT d.amount_cents AS amount, v.file_path AS path
               FROM vault_documents d
               JOIN document_versions v ON v.vault_document_id = d.id
              WHERE d.organization_id = ?',
            [$org->id],
        );
        self::assertCount(20, $rows);
        foreach ($rows as $row) {
            $pdf = (string) file_get_contents($this->storageRoot() . '/' . (string) $row['path']);
            if (preg_match('/TOTAL\s+JPY\s+([\d,]+)/', $pdf, $m) !== 1) {
                self::fail('demo PDF is missing its printed TOTAL');
            }
            self::assertSame(
                (int) $row['amount'],
                (int) str_replace(',', '', $m[1]),
                'amount_cents must equal the total printed in the document PDF (list == PDF)',
            );
        }

        // Dates are spread — not a single bulk-upload day.
        $range = $this->query()->fetchOne(
            'SELECT MIN(transaction_date) AS lo, MAX(transaction_date) AS hi FROM vault_documents WHERE organization_id = ?',
            [$org->id],
        );
        self::assertIsArray($range);
        self::assertNotSame($range['lo'], $range['hi']);

        // Reap: every row and every file of the org is gone.
        (new DemoOrgReaper($this->query(), $this->storageRoot()))->reap($org->id);

        foreach (['vault_documents', 'document_versions', 'audit_events', 'vault_settings', 'users'] as $table) {
            self::assertSame(0, $this->countFor($table, $org->id), "{$table} not fully reaped");
        }
        $row = $this->query()->fetchOne('SELECT COUNT(*) AS n FROM organizations WHERE id = ?', [$org->id]);
        self::assertSame(0, is_array($row) ? (int) $row['n'] : -1);
        self::assertDirectoryDoesNotExist($orgDir);

        // Idempotent: reaping again is a no-op success.
        (new DemoOrgReaper($this->query(), $this->storageRoot()))->reap($org->id);
    }
}
