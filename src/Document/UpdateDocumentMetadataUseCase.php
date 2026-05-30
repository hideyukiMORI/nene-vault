<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;

final readonly class UpdateDocumentMetadataUseCase implements UpdateDocumentMetadataUseCaseInterface
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
    public function execute(UpdateDocumentMetadataInput $input): array
    {
        $document = $this->documents->findById($input->documentId, $input->organizationId);

        if ($document === null) {
            throw new VaultDocumentNotFoundException($input->documentId);
        }

        // Capture before-state metadata for audit (§3.2)
        $beforeJson = $this->metadataSnapshot($document);

        $dateUncertain = $input->transactionDate === null;

        $this->documents->updateMetadata(
            $input->documentId,
            $input->organizationId,
            $input->transactionDate,
            $input->amountCents,
            $input->counterpartyName,
            $input->category,
            $input->tags,
            $dateUncertain,
        );

        $updated = $this->documents->findById($input->documentId, $input->organizationId);
        assert($updated !== null);

        $version = $this->versions->findById($updated->currentVersionId, $input->organizationId);
        assert($version !== null);

        $this->audit->record(
            action: AuditAction::DOCUMENT_METADATA_CHANGED,
            entityType: 'vault_document',
            entityId: $input->documentId,
            actorUserId: $input->actorUserId,
            organizationId: $input->organizationId,
            beforeJson: $beforeJson,
            afterJson: $this->metadataSnapshot($updated),
        );

        return [$updated, $version];
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
