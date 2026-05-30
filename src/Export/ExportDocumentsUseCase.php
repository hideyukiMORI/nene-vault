<?php

declare(strict_types=1);

namespace NeneVault\Export;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Document\DocumentSearchCriteria;
use NeneVault\Document\VaultDocument;
use NeneVault\Document\VaultDocumentRepositoryInterface;
use NeneVault\DocumentVersion\DocumentVersion;

final readonly class ExportDocumentsUseCase implements ExportDocumentsUseCaseInterface
{
    /** Hard cap to keep a single export bounded. */
    private const MAX_DOCUMENTS = 10000;

    /** @var list<string> */
    private const MANIFEST_HEADER = [
        'document_id',
        'version',
        'transaction_date',
        'amount_cents',
        'counterparty_name',
        'category',
        'file_sha256',
        'uploaded_at',
        'voided_at',
    ];

    public function __construct(
        private VaultDocumentRepositoryInterface $documents,
        private AuditRecorderInterface $audit,
    ) {
    }

    public function execute(ExportDocumentsInput $input): string
    {
        $criteria = new DocumentSearchCriteria(
            organizationId: $input->organizationId,
            transactionDateFrom: $input->transactionDateFrom,
            transactionDateTo: $input->transactionDateTo,
            counterpartyName: $input->counterpartyName,
            includeVoided: $input->includeVoided,
            limit: self::MAX_DOCUMENTS,
            offset: 0,
        );

        $rows = $this->documents->search($criteria);

        $csv = $this->buildCsv($rows);

        // Audit each included document (read-only export; §10 tax audit response)
        $filter = [
            'transaction_date_from' => $input->transactionDateFrom,
            'transaction_date_to' => $input->transactionDateTo,
            'counterparty_name' => $input->counterpartyName,
            'include_voided' => $input->includeVoided,
        ];

        foreach ($rows as [$document, $version]) {
            $this->audit->record(
                action: AuditAction::DOCUMENT_EXPORTED,
                entityType: 'vault_document',
                entityId: $document->id,
                actorUserId: $input->actorUserId,
                organizationId: $input->organizationId,
                beforeJson: null,
                afterJson: null,
                metadataJson: ['export_filter' => $filter],
            );
        }

        return $csv;
    }

    /**
     * @param list<array{0: VaultDocument, 1: DocumentVersion}> $rows
     */
    private function buildCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);

        fputcsv($handle, self::MANIFEST_HEADER, escape: '');

        foreach ($rows as [$document, $version]) {
            fputcsv($handle, [
                $document->id,
                (string) $version->versionNumber,
                $document->transactionDate ?? '',
                $document->amountCents !== null ? (string) $document->amountCents : '',
                $document->counterpartyName,
                $document->category,
                $version->fileSha256,
                $document->uploadedAt ?? '',
                $document->voidedAt ?? '',
            ], escape: '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        assert($csv !== false);

        return $csv;
    }
}
