<?php

declare(strict_types=1);

namespace NeneVault\Tests\Document;

use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneVault\Document\DuplicateFileException;
use NeneVault\Document\FileTooLargeException;
use NeneVault\Document\MimeTypeNotAllowedException;
use NeneVault\Document\UploadDocumentInput;
use NeneVault\Document\UploadDocumentUseCase;
use NeneVault\Document\VaultDocument;
use NeneVault\Document\VaultDocumentRepositoryInterface;
use NeneVault\DocumentVersion\DocumentStorageInterface;
use NeneVault\DocumentVersion\DocumentVersion;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;
use NeneVault\Tests\Audit\InMemoryAuditRecorderFactory;
use NeneVault\Tests\Support\SynchronousTransactionManager;
use NeneVault\VaultSettings\VaultSettings;
use NeneVault\VaultSettings\VaultSettingsRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class UploadDocumentUseCaseTest extends TestCase
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function test_uploads_pdf_and_records_audit(): void
    {
        $docs = new InMemoryVaultDocumentRepository();
        $versions = new InMemoryDocumentVersionRepository();
        $storage = new FakeDocumentStorage('abc123sha');
        $settings = new FakeVaultSettingsRepository(retentionYears: 10);
        $auditFactory = new InMemoryAuditRecorderFactory();
        $useCase = new UploadDocumentUseCase(
            new SynchronousTransactionManager(),
            static fn (DatabaseQueryExecutorInterface $e): VaultDocumentRepositoryInterface => $docs,
            static fn (DatabaseQueryExecutorInterface $e): DocumentVersionRepositoryInterface => $versions,
            $storage,
            static fn (DatabaseQueryExecutorInterface $e): VaultSettingsRepositoryInterface => $settings,
            $auditFactory,
            self::MAX_BYTES,
        );

        $output = $useCase->execute($this->input(transactionDate: '2026-03-31', amountCents: 110000));

        $this->assertSame('abc123sha', $output->fileSha256);
        $this->assertSame(1, $output->versionNumber);
        $this->assertSame('active', $output->document->status);
        $this->assertSame(110000, $output->document->amountCents);
        $this->assertFalse($output->document->dateUncertain);
        // 10 years from transaction date
        $this->assertSame('2036-03-31', $output->document->retentionExpiresAt);

        $this->assertCount(1, $docs->all());
        $this->assertCount(1, $versions->all());

        $events = $auditFactory->all();
        $this->assertCount(1, $events);
        $this->assertSame('document.uploaded', $events[0]->action);
        $this->assertNull($events[0]->before);
        $this->assertSame('abc123sha', $events[0]->after['file_sha256'] ?? null);
        // No storage path in the audit snapshot
        $this->assertArrayNotHasKey('file_path', $events[0]->after ?? []);
    }

    public function test_null_transaction_date_sets_date_uncertain(): void
    {
        $useCase = $this->makeUseCase();
        $output = $useCase->execute($this->input(transactionDate: null));

        $this->assertTrue($output->document->dateUncertain);
        $this->assertNull($output->document->transactionDate);
    }

    public function test_rejects_disallowed_mime_type(): void
    {
        $useCase = $this->makeUseCase();

        $this->expectException(MimeTypeNotAllowedException::class);

        $useCase->execute($this->input(mimeType: 'application/zip'));
    }

    public function test_rejects_oversized_file(): void
    {
        $useCase = $this->makeUseCase();

        $this->expectException(FileTooLargeException::class);

        $useCase->execute($this->input(fileSizeBytes: self::MAX_BYTES + 1));
    }

    public function test_rejects_duplicate_sha256_unless_confirmed(): void
    {
        $versions = new InMemoryDocumentVersionRepository();
        $versions->markSha('dup-sha');
        $storage = new FakeDocumentStorage('dup-sha');
        $useCase = new UploadDocumentUseCase(
            new SynchronousTransactionManager(),
            static fn (DatabaseQueryExecutorInterface $e): VaultDocumentRepositoryInterface => new InMemoryVaultDocumentRepository(),
            static fn (DatabaseQueryExecutorInterface $e): DocumentVersionRepositoryInterface => $versions,
            $storage,
            static fn (DatabaseQueryExecutorInterface $e): VaultSettingsRepositoryInterface => new FakeVaultSettingsRepository(10),
            new InMemoryAuditRecorderFactory(),
            self::MAX_BYTES,
        );

        $this->expectException(DuplicateFileException::class);
        $useCase->execute($this->input());
    }

    public function test_accepts_duplicate_when_confirmed(): void
    {
        $versions = new InMemoryDocumentVersionRepository();
        $versions->markSha('dup-sha');
        $storage = new FakeDocumentStorage('dup-sha');
        $useCase = new UploadDocumentUseCase(
            new SynchronousTransactionManager(),
            static fn (DatabaseQueryExecutorInterface $e): VaultDocumentRepositoryInterface => new InMemoryVaultDocumentRepository(),
            static fn (DatabaseQueryExecutorInterface $e): DocumentVersionRepositoryInterface => $versions,
            $storage,
            static fn (DatabaseQueryExecutorInterface $e): VaultSettingsRepositoryInterface => new FakeVaultSettingsRepository(10),
            new InMemoryAuditRecorderFactory(),
            self::MAX_BYTES,
        );

        $output = $useCase->execute($this->input(confirmDuplicate: true));
        $this->assertSame('dup-sha', $output->fileSha256);
    }

    public function test_uses_org_retention_years(): void
    {
        $docs = new InMemoryVaultDocumentRepository();
        $useCase = new UploadDocumentUseCase(
            new SynchronousTransactionManager(),
            static fn (DatabaseQueryExecutorInterface $e): VaultDocumentRepositoryInterface => $docs,
            static fn (DatabaseQueryExecutorInterface $e): DocumentVersionRepositoryInterface => new InMemoryDocumentVersionRepository(),
            new FakeDocumentStorage('x'),
            static fn (DatabaseQueryExecutorInterface $e): VaultSettingsRepositoryInterface => new FakeVaultSettingsRepository(retentionYears: 7),
            new InMemoryAuditRecorderFactory(),
            self::MAX_BYTES,
        );

        $output = $useCase->execute($this->input(transactionDate: '2026-01-01'));
        $this->assertSame(7, $output->document->retentionYears);
        $this->assertSame('2033-01-01', $output->document->retentionExpiresAt);
    }

    private function makeUseCase(): UploadDocumentUseCase
    {
        return new UploadDocumentUseCase(
            new SynchronousTransactionManager(),
            static fn (DatabaseQueryExecutorInterface $e): VaultDocumentRepositoryInterface => new InMemoryVaultDocumentRepository(),
            static fn (DatabaseQueryExecutorInterface $e): DocumentVersionRepositoryInterface => new InMemoryDocumentVersionRepository(),
            new FakeDocumentStorage('sha'),
            static fn (DatabaseQueryExecutorInterface $e): VaultSettingsRepositoryInterface => new FakeVaultSettingsRepository(10),
            new InMemoryAuditRecorderFactory(),
            self::MAX_BYTES,
        );
    }

    /** @param list<string> $tags */
    private function input(
        string $mimeType = 'application/pdf',
        int $fileSizeBytes = 1024,
        ?string $transactionDate = '2026-03-31',
        ?int $amountCents = null,
        bool $confirmDuplicate = false,
        array $tags = [],
    ): UploadDocumentInput {
        return new UploadDocumentInput(
            organizationId: 1,
            tmpPath: '/tmp/fake-upload',
            originalFilename: 'invoice.pdf',
            mimeType: $mimeType,
            fileSizeBytes: $fileSizeBytes,
            counterpartyName: 'Sample Inc.',
            category: 'invoice_received',
            transactionDate: $transactionDate,
            amountCents: $amountCents,
            tags: $tags,
            source: 'web_upload',
            confirmDuplicate: $confirmDuplicate,
            actorUserId: 5,
        );
    }
}

final class FakeDocumentStorage implements DocumentStorageInterface
{
    public function __construct(private readonly string $sha)
    {
    }

    public function store(string $sourceTmpPath, int $organizationId, string $documentId, int $versionNumber, string $originalFilename): string
    {
        return sprintf('vault/%d/%s/v%d/%s', $organizationId, $documentId, $versionNumber, $originalFilename);
    }

    public function resolveAbsolutePath(string $relativePath): string
    {
        return '/tmp/' . $relativePath;
    }

    public function exists(string $relativePath): bool
    {
        return true;
    }

    public function readContents(string $relativePath): string
    {
        return '';
    }

    public function sha256(string $absolutePath): string
    {
        return $this->sha;
    }
}

final class InMemoryVaultDocumentRepository implements VaultDocumentRepositoryInterface
{
    /** @var array<string, VaultDocument> */
    private array $docs = [];

    public function save(VaultDocument $document): void
    {
        $this->docs[$document->id] = $document;
    }

    public function findById(string $id, int $organizationId): ?VaultDocument
    {
        $d = $this->docs[$id] ?? null;

        return $d !== null && $d->organizationId === $organizationId ? $d : null;
    }

    public function updateCurrentVersion(string $id, int $organizationId, string $currentVersionId): void
    {
        // not needed for upload slice tests
    }

    /** @param list<string> $tags */
    public function updateMetadata(
        string $id,
        int $organizationId,
        ?string $transactionDate,
        ?int $amountCents,
        string $counterpartyName,
        string $category,
        array $tags,
        bool $dateUncertain,
    ): void {
        // not needed for upload slice tests
    }

    public function void(string $id, int $organizationId, int $voidedBy, string $voidReason, ?string $voidNote): void
    {
        // not needed for upload slice tests
    }

    public function restore(string $id, int $organizationId): void
    {
        // not needed for upload slice tests
    }

    /** @return list<array{0: VaultDocument, 1: \NeneVault\DocumentVersion\DocumentVersion}> */
    public function search(\NeneVault\Document\DocumentSearchCriteria $criteria): array
    {
        return [];
    }

    public function countByCriteria(\NeneVault\Document\DocumentSearchCriteria $criteria): int
    {
        return 0;
    }

    /** @return list<VaultDocument> */
    public function all(): array
    {
        return array_values($this->docs);
    }
}

final class InMemoryDocumentVersionRepository implements DocumentVersionRepositoryInterface
{
    /** @var list<DocumentVersion> */
    private array $versions = [];

    /** @var array<string, bool> */
    private array $shas = [];

    public function markSha(string $sha): void
    {
        $this->shas[$sha] = true;
    }

    public function save(DocumentVersion $version): void
    {
        $this->versions[] = $version;
        $this->shas[$version->fileSha256] = true;
    }

    public function findById(string $id, int $organizationId): ?DocumentVersion
    {
        foreach ($this->versions as $v) {
            if ($v->id === $id && $v->organizationId === $organizationId) {
                return $v;
            }
        }

        return null;
    }

    /** @return list<DocumentVersion> */
    public function listByDocumentId(string $vaultDocumentId, int $organizationId): array
    {
        return array_values(array_filter(
            $this->versions,
            static fn (DocumentVersion $v) => $v->vaultDocumentId === $vaultDocumentId && $v->organizationId === $organizationId,
        ));
    }

    public function existsBySha256(string $fileSha256, int $organizationId): bool
    {
        return $this->shas[$fileSha256] ?? false;
    }

    public function nextVersionNumber(string $vaultDocumentId, int $organizationId): int
    {
        $max = 0;
        foreach ($this->versions as $v) {
            if ($v->vaultDocumentId === $vaultDocumentId) {
                $max = max($max, $v->versionNumber);
            }
        }

        return $max + 1;
    }

    /** @return list<DocumentVersion> */
    public function all(): array
    {
        return $this->versions;
    }
}

final class FakeVaultSettingsRepository implements VaultSettingsRepositoryInterface
{
    public function __construct(private readonly int $retentionYears)
    {
    }

    public function findByOrganizationId(int $organizationId): VaultSettings
    {
        return new VaultSettings(organizationId: $organizationId, retentionYears: $this->retentionYears);
    }

    public function save(VaultSettings $settings): void
    {
    }

    public function update(VaultSettings $settings): void
    {
    }
}
