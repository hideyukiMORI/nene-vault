<?php

declare(strict_types=1);

namespace NeneVault\Export;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Document\DocumentSearchCriteria;
use NeneVault\Document\VaultDocument;
use NeneVault\Document\VaultDocumentRepositoryInterface;
use NeneVault\DocumentVersion\DocumentStorageInterface;
use NeneVault\DocumentVersion\DocumentVersion;
use ZipArchive;

final readonly class ExportDocumentsUseCase implements ExportDocumentsUseCaseInterface
{
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
        private DocumentStorageInterface $storage,
        private AuditRecorderInterface $audit,
    ) {
    }

    public function execute(ExportDocumentsInput $input): ExportDocumentsOutput
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

        $payload = $input->format === 'zip'
            ? $this->buildZip($rows)
            : $this->buildCsv($rows);

        $filter = [
            'transaction_date_from' => $input->transactionDateFrom,
            'transaction_date_to'   => $input->transactionDateTo,
            'counterparty_name'     => $input->counterpartyName,
            'include_voided'        => $input->includeVoided,
            'format'                => $input->format,
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

        return new ExportDocumentsOutput(
            format: $input->format,
            payload: $payload,
            documentCount: count($rows),
        );
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

    /**
     * Builds a ZIP archive containing manifest.csv and all document files.
     *
     * ZIP structure:
     *   manifest.csv
     *   files/{document_id}/v{version_number}/{original_filename}
     *
     * @param list<array{0: VaultDocument, 1: DocumentVersion}> $rows
     */
    private function buildZip(array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vault_zip_');
        assert($tmp !== false);

        $zip = new ZipArchive();
        $opened = $zip->open($tmp, ZipArchive::OVERWRITE);
        assert($opened === true);

        $zip->addFromString('manifest.csv', $this->buildCsv($rows));

        foreach ($rows as [$document, $version]) {
            if (!$this->storage->exists($version->filePath)) {
                continue;
            }

            $fileContents = $this->storage->readContents($version->filePath);
            $zipEntry = sprintf(
                'files/%s/v%d/%s',
                $document->id,
                $version->versionNumber,
                basename($version->filePath),
            );
            $zip->addFromString($zipEntry, $fileContents);
        }

        $zip->close();

        $bytes = file_get_contents($tmp);
        @unlink($tmp);
        assert($bytes !== false);

        return $bytes;
    }
}
