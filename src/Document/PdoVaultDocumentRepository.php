<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneVault\DocumentVersion\DocumentVersion;

final readonly class PdoVaultDocumentRepository implements VaultDocumentRepositoryInterface
{
    private const COLUMNS = '
        id, organization_id, current_version_id, status, transaction_date,
        amount_cents, counterparty_name, category, tags, date_uncertain,
        is_metadata_confirmed, retention_years, retention_expires_at,
        uploaded_at, uploaded_by, voided_at, voided_by, void_reason, void_note
    ';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function save(VaultDocument $document): void
    {
        $this->query->execute(
            'INSERT INTO vault_documents
                (id, organization_id, current_version_id, status, transaction_date,
                 amount_cents, counterparty_name, category, tags, date_uncertain,
                 is_metadata_confirmed, retention_years, retention_expires_at,
                 uploaded_at, uploaded_by, voided_at, voided_by, void_reason, void_note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $document->id,
                $document->organizationId,
                $document->currentVersionId,
                $document->status,
                $document->transactionDate,
                $document->amountCents,
                $document->counterpartyName,
                $document->category,
                json_encode($document->tags, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $document->dateUncertain ? 1 : 0,
                $document->isMetadataConfirmed ? 1 : 0,
                $document->retentionYears,
                $document->retentionExpiresAt,
                $document->uploadedAt ?? date('Y-m-d H:i:s'),
                $document->uploadedBy,
                $document->voidedAt,
                $document->voidedBy,
                $document->voidReason,
                $document->voidNote,
            ],
        );
    }

    public function findById(string $id, int $organizationId): ?VaultDocument
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM vault_documents WHERE id = ? AND organization_id = ?',
            [$id, $organizationId],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function updateCurrentVersion(string $id, int $organizationId, string $currentVersionId): void
    {
        $this->query->execute(
            'UPDATE vault_documents SET current_version_id = ? WHERE id = ? AND organization_id = ?',
            [$currentVersionId, $id, $organizationId],
        );
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
        $this->query->execute(
            'UPDATE vault_documents
             SET transaction_date = ?, amount_cents = ?, counterparty_name = ?,
                 category = ?, tags = ?, date_uncertain = ?, is_metadata_confirmed = 1
             WHERE id = ? AND organization_id = ?',
            [
                $transactionDate,
                $amountCents,
                $counterpartyName,
                $category,
                json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $dateUncertain ? 1 : 0,
                $id,
                $organizationId,
            ],
        );
    }

    public function void(string $id, int $organizationId, int $voidedBy, string $voidReason, ?string $voidNote): void
    {
        $this->query->execute(
            "UPDATE vault_documents
             SET status = 'voided', voided_at = ?, voided_by = ?, void_reason = ?, void_note = ?
             WHERE id = ? AND organization_id = ?",
            [date('Y-m-d H:i:s'), $voidedBy, $voidReason, $voidNote, $id, $organizationId],
        );
    }

    public function restore(string $id, int $organizationId): void
    {
        $this->query->execute(
            "UPDATE vault_documents
             SET status = 'active', voided_at = NULL, voided_by = NULL, void_reason = NULL, void_note = NULL
             WHERE id = ? AND organization_id = ?",
            [$id, $organizationId],
        );
    }

    /**
     * @return list<array{0: VaultDocument, 1: DocumentVersion}>
     */
    public function search(DocumentSearchCriteria $criteria): array
    {
        [$where, $params] = $this->buildSearchWhere($criteria);

        $docCols = implode(', ', array_map(static fn (string $c) => 'd.' . trim($c), explode(',', self::COLUMNS)));

        $sql = 'SELECT ' . $docCols . ',
                v.id AS v_id, v.version_number AS v_version_number,
                v.file_path AS v_file_path, v.file_sha256 AS v_file_sha256,
                v.mime_type AS v_mime_type,
                v.original_filename AS v_original_filename, v.file_size_bytes AS v_file_size_bytes,
                v.source AS v_source, v.uploaded_at AS v_uploaded_at, v.uploaded_by AS v_uploaded_by
            FROM vault_documents d
            INNER JOIN document_versions v ON v.id = d.current_version_id AND v.organization_id = d.organization_id'
            . $where
            . ' ORDER BY d.transaction_date DESC, d.id DESC LIMIT ? OFFSET ?';

        $rows = $this->query->fetchAll($sql, [...$params, $criteria->limit, $criteria->offset]);

        return array_map(
            fn (array $row) => [$this->mapRow($row), $this->mapVersionRow($row)],
            $rows,
        );
    }

    public function countByCriteria(DocumentSearchCriteria $criteria): int
    {
        [$where, $params] = $this->buildSearchWhere($criteria);

        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM vault_documents d' . $where,
            $params,
        );

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    /**
     * @return array{string, list<mixed>}
     */
    private function buildSearchWhere(DocumentSearchCriteria $criteria): array
    {
        $conditions = ['d.organization_id = ?'];
        $params = [$criteria->organizationId];

        if (!$criteria->includeVoided) {
            $conditions[] = "d.status = 'active'";
        }

        if ($criteria->transactionDateFrom !== null) {
            $conditions[] = 'd.transaction_date >= ?';
            $params[] = $criteria->transactionDateFrom;
        }

        if ($criteria->transactionDateTo !== null) {
            $conditions[] = 'd.transaction_date <= ?';
            $params[] = $criteria->transactionDateTo;
        }

        if ($criteria->amountMinCents !== null) {
            $conditions[] = 'd.amount_cents >= ?';
            $params[] = $criteria->amountMinCents;
        }

        if ($criteria->amountMaxCents !== null) {
            $conditions[] = 'd.amount_cents <= ?';
            $params[] = $criteria->amountMaxCents;
        }

        if ($criteria->counterpartyName !== null && $criteria->counterpartyName !== '') {
            $conditions[] = 'd.counterparty_name LIKE ?';
            $params[] = '%' . $criteria->counterpartyName . '%';
        }

        if ($criteria->category !== null && $criteria->category !== '') {
            $conditions[] = 'd.category = ?';
            $params[] = $criteria->category;
        }

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /** @param array<string, mixed> $row */
    private function mapVersionRow(array $row): DocumentVersion
    {
        return new DocumentVersion(
            id: (string) $row['v_id'],
            vaultDocumentId: (string) $row['id'],
            organizationId: (int) $row['organization_id'],
            versionNumber: (int) $row['v_version_number'],
            filePath: (string) ($row['v_file_path'] ?? ''),
            fileSha256: (string) $row['v_file_sha256'],
            mimeType: (string) $row['v_mime_type'],
            originalFilename: (string) $row['v_original_filename'],
            fileSizeBytes: (int) $row['v_file_size_bytes'],
            source: (string) $row['v_source'],
            uploadedAt: isset($row['v_uploaded_at']) ? (string) $row['v_uploaded_at'] : null,
            uploadedBy: isset($row['v_uploaded_by']) ? (int) $row['v_uploaded_by'] : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): VaultDocument
    {
        $tags = isset($row['tags']) && is_string($row['tags'])
            ? json_decode($row['tags'], true, 512, JSON_THROW_ON_ERROR)
            : [];

        return new VaultDocument(
            id: (string) $row['id'],
            organizationId: (int) $row['organization_id'],
            currentVersionId: (string) $row['current_version_id'],
            status: (string) $row['status'],
            transactionDate: isset($row['transaction_date']) ? (string) $row['transaction_date'] : null,
            amountCents: isset($row['amount_cents']) ? (int) $row['amount_cents'] : null,
            counterpartyName: (string) $row['counterparty_name'],
            category: (string) $row['category'],
            tags: is_array($tags) ? array_values(array_map('strval', $tags)) : [],
            dateUncertain: (bool) $row['date_uncertain'],
            isMetadataConfirmed: (bool) $row['is_metadata_confirmed'],
            retentionYears: (int) $row['retention_years'],
            retentionExpiresAt: (string) $row['retention_expires_at'],
            uploadedAt: isset($row['uploaded_at']) ? (string) $row['uploaded_at'] : null,
            uploadedBy: isset($row['uploaded_by']) ? (int) $row['uploaded_by'] : null,
            voidedAt: isset($row['voided_at']) ? (string) $row['voided_at'] : null,
            voidedBy: isset($row['voided_by']) ? (int) $row['voided_by'] : null,
            voidReason: isset($row['void_reason']) ? (string) $row['void_reason'] : null,
            voidNote: isset($row['void_note']) ? (string) $row['void_note'] : null,
        );
    }
}
