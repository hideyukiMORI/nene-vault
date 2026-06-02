<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;

final readonly class UpdateDocumentMetadataUseCase implements UpdateDocumentMetadataUseCaseInterface
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
    public function execute(UpdateDocumentMetadataInput $input): array
    {
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($input): array {
                $documents = ($this->documentRepository)($executor);
                $versions = ($this->versionRepository)($executor);
                $audit = ($this->auditRecorder)($executor);

                $document = $documents->findById($input->documentId, $input->organizationId);

                if ($document === null) {
                    throw new VaultDocumentNotFoundException($input->documentId);
                }

                // Capture before-state metadata for audit (§3.2)
                $beforeJson = $this->metadataSnapshot($document);

                $dateUncertain = $input->transactionDate === null;

                $documents->updateMetadata(
                    $input->documentId,
                    $input->organizationId,
                    $input->transactionDate,
                    $input->amountCents,
                    $input->counterpartyName,
                    $input->category,
                    $input->tags,
                    $dateUncertain,
                );

                $updated = $documents->findById($input->documentId, $input->organizationId);
                assert($updated !== null);

                $version = $versions->findById($updated->currentVersionId, $input->organizationId);
                assert($version !== null);

                $audit->record(
                    action: AuditAction::DOCUMENT_METADATA_CHANGED,
                    entityType: 'vault_document',
                    entityId: $input->documentId,
                    actorUserId: $input->actorUserId,
                    organizationId: $input->organizationId,
                    beforeJson: $beforeJson,
                    afterJson: $this->metadataSnapshot($updated),
                );

                return [$updated, $version];
            },
        );
    }

    /** @return array<string, mixed> */
    private function metadataSnapshot(VaultDocument $doc): array
    {
        return [
            'transaction_date'  => $doc->transactionDate,
            'amount_cents'      => $doc->amountCents,
            'counterparty_name' => $doc->counterpartyName,
            'category'          => $doc->category,
            'tags'              => $doc->tags,
            'date_uncertain'    => $doc->dateUncertain,
        ];
    }
}
