<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;

final readonly class RestoreDocumentUseCase implements RestoreDocumentUseCaseInterface
{
    public function __construct(
        private VaultDocumentRepositoryInterface $documents,
        private DocumentVersionRepositoryInterface $versions,
        private AuditRecorderInterface $audit,
    ) {
    }

    /**
     * @return array{0: VaultDocument, 1: \NeneVault\DocumentVersion\DocumentVersion}
     */
    public function execute(string $documentId, int $organizationId, ?int $actorUserId): array
    {
        $document = $this->documents->findById($documentId, $organizationId);

        if ($document === null) {
            throw new VaultDocumentNotFoundException($documentId);
        }

        if ($document->status !== 'voided') {
            throw new InvalidDocumentStateException($documentId, $document->status, 'restored');
        }

        $beforeJson = ['status' => $document->status];

        $this->documents->restore($documentId, $organizationId);

        $updated = $this->documents->findById($documentId, $organizationId);
        assert($updated !== null);

        $version = $this->versions->findById($updated->currentVersionId, $organizationId);
        assert($version !== null);

        $this->audit->record(
            action: AuditAction::DOCUMENT_RESTORED,
            entityType: 'vault_document',
            entityId: $documentId,
            actorUserId: $actorUserId,
            organizationId: $organizationId,
            beforeJson: $beforeJson,
            afterJson: ['status' => $updated->status],
        );

        return [$updated, $version];
    }
}
