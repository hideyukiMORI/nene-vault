<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Database\DatabaseQueryExecutorInterface;

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
