<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\DocumentVersion\DocumentStorageInterface;
use NeneVault\DocumentVersion\DocumentVersion;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;
use NeneVault\Support\Ulid;
use NeneVault\VaultSettings\VaultSettingsRepositoryInterface;

final readonly class UploadDocumentUseCase implements UploadDocumentUseCaseInterface
{
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    private const DEFAULT_RETENTION_YEARS = 10;

    /**
     * @param Closure(DatabaseQueryExecutorInterface): VaultDocumentRepositoryInterface    $documentRepository
     * @param Closure(DatabaseQueryExecutorInterface): DocumentVersionRepositoryInterface  $versionRepository
     * @param Closure(DatabaseQueryExecutorInterface): VaultSettingsRepositoryInterface    $settingsRepository
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $documentRepository,
        private Closure $versionRepository,
        private DocumentStorageInterface $storage,
        private Closure $settingsRepository,
        private AuditRecorderFactoryInterface $auditRecorderFactory,
        private int $maxFileSizeBytes,
    ) {
    }

    public function execute(UploadDocumentInput $input): UploadDocumentOutput
    {
        // 1. MIME allowlist (compliance §2.1, backend-standards §5)
        if (!in_array($input->mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new MimeTypeNotAllowedException($input->mimeType);
        }

        // 2. Size limit
        if ($input->fileSizeBytes > $this->maxFileSizeBytes) {
            throw new FileTooLargeException($input->fileSizeBytes, $this->maxFileSizeBytes);
        }

        // 3. SHA-256 (compliance §3.1) — hashing is filesystem work, done before the transaction
        $sha256 = $this->storage->sha256($input->tmpPath);

        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($input, $sha256): UploadDocumentOutput {
                $documents = ($this->documentRepository)($executor);
                $versions = ($this->versionRepository)($executor);
                $settings = ($this->settingsRepository)($executor);
                $audit = $this->auditRecorderFactory->forExecutor($executor);

                // Duplicate detection (§3.5)
                if (!$input->confirmDuplicate && $versions->existsBySha256($sha256, $input->organizationId)) {
                    throw new DuplicateFileException($sha256);
                }

                // 4. Retention calculation (ADR 0004)
                $current = $settings->findByOrganizationId($input->organizationId);
                $retentionYears = $current !== null ? $current->retentionYears : self::DEFAULT_RETENTION_YEARS;
                $dateUncertain = $input->transactionDate === null;
                $anchorDate = $input->transactionDate ?? date('Y-m-d');
                $retentionExpiresAt = date('Y-m-d', strtotime("{$anchorDate} +{$retentionYears} years") ?: time());

                // 5. Store file (immutable — new path per version)
                $documentId = Ulid::generate();
                $versionNumber = 1;
                $relativePath = $this->storage->store(
                    $input->tmpPath,
                    $input->organizationId,
                    $documentId,
                    $versionNumber,
                    $input->originalFilename,
                );

                $now = date('Y-m-d H:i:s');
                $versionId = Ulid::generate();

                $version = new DocumentVersion(
                    id: $versionId,
                    vaultDocumentId: $documentId,
                    organizationId: $input->organizationId,
                    versionNumber: $versionNumber,
                    filePath: $relativePath,
                    fileSha256: $sha256,
                    mimeType: $input->mimeType,
                    originalFilename: $input->originalFilename,
                    fileSizeBytes: $input->fileSizeBytes,
                    source: $input->source,
                    uploadedAt: $now,
                    uploadedBy: $input->actorUserId,
                );

                $versions->save($version);

                $document = new VaultDocument(
                    id: $documentId,
                    organizationId: $input->organizationId,
                    currentVersionId: $versionId,
                    status: 'active',
                    transactionDate: $input->transactionDate,
                    amountCents: $input->amountCents,
                    counterpartyName: $input->counterpartyName,
                    category: $input->category,
                    tags: $input->tags,
                    dateUncertain: $dateUncertain,
                    isMetadataConfirmed: false,
                    retentionYears: $retentionYears,
                    retentionExpiresAt: $retentionExpiresAt,
                    uploadedAt: $now,
                    uploadedBy: $input->actorUserId,
                );

                $documents->save($document);

                // 6. Audit (ADR 0014) — no secrets in snapshot; file_path excluded.
                // AuditTableConfig has no `source` column axis: fold the upload channel
                // into metadata (AuditEventPresenter derives it back out on read).
                $audit->record(new AuditEvent(
                    action: AuditAction::DOCUMENT_UPLOADED,
                    entityType: 'vault_document',
                    entityId: $documentId,
                    actorId: $input->actorUserId,
                    organizationId: $input->organizationId,
                    before: null,
                    after: $this->toAuditArray($document, $sha256, $versionNumber),
                    metadata: ['source' => $input->source],
                ));

                return new UploadDocumentOutput(
                    document: $document,
                    fileSha256: $sha256,
                    mimeType: $input->mimeType,
                    originalFilename: $input->originalFilename,
                    fileSizeBytes: $input->fileSizeBytes,
                    versionNumber: $versionNumber,
                );
            },
        );
    }

    /** @return array<string, mixed> */
    private function toAuditArray(VaultDocument $doc, string $sha256, int $versionNumber): array
    {
        return [
            'id'                => $doc->id,
            'status'            => $doc->status,
            'transaction_date'  => $doc->transactionDate,
            'amount_cents'      => $doc->amountCents,
            'counterparty_name' => $doc->counterpartyName,
            'category'          => $doc->category,
            'tags'              => $doc->tags,
            'file_sha256'       => $sha256,
            'version_number'    => $versionNumber,
            'date_uncertain'    => $doc->dateUncertain,
            'retention_expires_at' => $doc->retentionExpiresAt,
        ];
    }
}
