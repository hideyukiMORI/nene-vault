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
use NeneVault\VaultSettings\VaultSettingsRepositoryInterface;
use Symfony\Component\Uid\Ulid;

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
        // 1. MIME allowlist (compliance §2.1, backend-standards §5) — the
        // client-declared media type is a cheap first gate.
        if (!in_array($input->mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new MimeTypeNotAllowedException($input->mimeType);
        }

        // 1b. Content sniffing (QA VLT-B7-01): the declared media type is
        // client-controlled — an .exe can claim `application/pdf` and slip
        // through the allowlist above. Verify the actual magic bytes match an
        // allowed type; reject the declaration/content mismatch at intake rather
        // than relying only on the download-side nosniff+attachment mitigation.
        $sniffed = $this->sniffMimeType($input->tmpPath);
        if ($sniffed === null || !in_array($sniffed, self::ALLOWED_MIME_TYPES, true)) {
            throw new MimeTypeNotAllowedException($input->mimeType, $sniffed ?? 'unknown');
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
                $documentId = (string) new Ulid();
                $versionNumber = 1;
                $relativePath = $this->storage->store(
                    $input->tmpPath,
                    $input->organizationId,
                    $documentId,
                    $versionNumber,
                    $input->originalFilename,
                );

                $now = date('Y-m-d H:i:s');
                $versionId = (string) new Ulid();

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

    /**
     * Detect the media type from a file's leading magic bytes, restricted to the
     * three accepted formats. Returns the detected type, or null when the header
     * matches none of them (e.g. an executable, SVG, or truncated file). This is
     * content-based, so it cannot be spoofed by a client-declared media type.
     */
    private function sniffMimeType(string $tmpPath): ?string
    {
        $handle = @fopen($tmpPath, 'rb');
        if ($handle === false) {
            return null;
        }

        $header = fread($handle, 8);
        fclose($handle);

        if ($header === false || $header === '') {
            return null;
        }

        if (str_starts_with($header, '%PDF-')) {
            return 'application/pdf';
        }
        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($header, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        return null;
    }
}
