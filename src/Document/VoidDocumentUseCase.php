<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;

final readonly class VoidDocumentUseCase implements VoidDocumentUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): VaultDocumentRepositoryInterface   $documentRepository
     * @param Closure(DatabaseQueryExecutorInterface): DocumentVersionRepositoryInterface $versionRepository
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $documentRepository,
        private Closure $versionRepository,
        private AuditRecorderFactoryInterface $auditRecorderFactory,
    ) {
    }

    /**
     * @return array{0: VaultDocument, 1: \NeneVault\DocumentVersion\DocumentVersion}
     */
    public function execute(
        string $documentId,
        int $organizationId,
        string $voidReason,
        ?string $voidNote,
        ?int $actorUserId,
    ): array {
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($documentId, $organizationId, $voidReason, $voidNote, $actorUserId): array {
                $documents = ($this->documentRepository)($executor);
                $versions = ($this->versionRepository)($executor);
                $audit = $this->auditRecorderFactory->forExecutor($executor);

                $document = $documents->findById($documentId, $organizationId);

                if ($document === null) {
                    throw new VaultDocumentNotFoundException($documentId);
                }

                if ($document->status !== 'active') {
                    throw new InvalidDocumentStateException($documentId, $document->status, 'voided');
                }

                $beforeJson = ['status' => $document->status];

                $documents->void($documentId, $organizationId, $actorUserId ?? 0, $voidReason, $voidNote);

                $updated = $documents->findById($documentId, $organizationId);
                assert($updated !== null);

                $version = $versions->findById($updated->currentVersionId, $organizationId);
                assert($version !== null);

                $audit->record(new AuditEvent(
                    action: AuditAction::DOCUMENT_VOIDED,
                    entityType: 'vault_document',
                    entityId: $documentId,
                    actorId: $actorUserId,
                    organizationId: $organizationId,
                    before: $beforeJson,
                    after: ['status' => $updated->status],
                    metadata: ['void_reason' => $voidReason, 'void_note' => $voidNote],
                ));

                return [$updated, $version];
            },
        );
    }
}
