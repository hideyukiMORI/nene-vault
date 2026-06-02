<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;

final readonly class RestoreDocumentUseCase implements RestoreDocumentUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): VaultDocumentRepositoryInterface   $documentRepository
     * @param Closure(DatabaseQueryExecutorInterface): DocumentVersionRepositoryInterface $versionRepository
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface             $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $documentRepository,
        private Closure $versionRepository,
        private Closure $auditRecorder,
    ) {
    }

    /**
     * @return array{0: VaultDocument, 1: \NeneVault\DocumentVersion\DocumentVersion}
     */
    public function execute(string $documentId, int $organizationId, ?int $actorUserId): array
    {
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($documentId, $organizationId, $actorUserId): array {
                $documents = ($this->documentRepository)($executor);
                $versions = ($this->versionRepository)($executor);
                $audit = ($this->auditRecorder)($executor);

                $document = $documents->findById($documentId, $organizationId);

                if ($document === null) {
                    throw new VaultDocumentNotFoundException($documentId);
                }

                if ($document->status !== 'voided') {
                    throw new InvalidDocumentStateException($documentId, $document->status, 'restored');
                }

                $beforeJson = ['status' => $document->status];

                $documents->restore($documentId, $organizationId);

                $updated = $documents->findById($documentId, $organizationId);
                assert($updated !== null);

                $version = $versions->findById($updated->currentVersionId, $organizationId);
                assert($version !== null);

                $audit->record(
                    action: AuditAction::DOCUMENT_RESTORED,
                    entityType: 'vault_document',
                    entityId: $documentId,
                    actorUserId: $actorUserId,
                    organizationId: $organizationId,
                    beforeJson: $beforeJson,
                    afterJson: ['status' => $updated->status],
                );

                return [$updated, $version];
            },
        );
    }
}
