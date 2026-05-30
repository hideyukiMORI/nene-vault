<?php

declare(strict_types=1);

namespace NeneVault\DocumentVersion;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoDocumentVersionRepository implements DocumentVersionRepositoryInterface
{
    private const COLUMNS = '
        id, vault_document_id, organization_id, version_number, file_path,
        file_sha256, mime_type, original_filename, file_size_bytes, source,
        uploaded_at, uploaded_by
    ';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function save(DocumentVersion $version): void
    {
        $this->query->execute(
            'INSERT INTO document_versions
                (id, vault_document_id, organization_id, version_number, file_path,
                 file_sha256, mime_type, original_filename, file_size_bytes, source,
                 uploaded_at, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $version->id,
                $version->vaultDocumentId,
                $version->organizationId,
                $version->versionNumber,
                $version->filePath,
                $version->fileSha256,
                $version->mimeType,
                $version->originalFilename,
                $version->fileSizeBytes,
                $version->source,
                $version->uploadedAt ?? date('Y-m-d H:i:s'),
                $version->uploadedBy,
            ],
        );
    }

    public function findById(string $id, int $organizationId): ?DocumentVersion
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM document_versions WHERE id = ? AND organization_id = ?',
            [$id, $organizationId],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<DocumentVersion> */
    public function listByDocumentId(string $vaultDocumentId, int $organizationId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM document_versions
             WHERE vault_document_id = ? AND organization_id = ?
             ORDER BY version_number ASC',
            [$vaultDocumentId, $organizationId],
        );

        return array_map($this->mapRow(...), $rows);
    }

    public function existsBySha256(string $fileSha256, int $organizationId): bool
    {
        $row = $this->query->fetchOne(
            'SELECT 1 FROM document_versions WHERE file_sha256 = ? AND organization_id = ? LIMIT 1',
            [$fileSha256, $organizationId],
        );

        return $row !== null;
    }

    public function nextVersionNumber(string $vaultDocumentId, int $organizationId): int
    {
        $row = $this->query->fetchOne(
            'SELECT MAX(version_number) AS max_version FROM document_versions
             WHERE vault_document_id = ? AND organization_id = ?',
            [$vaultDocumentId, $organizationId],
        );

        $max = $row !== null && $row['max_version'] !== null ? (int) $row['max_version'] : 0;

        return $max + 1;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): DocumentVersion
    {
        return new DocumentVersion(
            id: (string) $row['id'],
            vaultDocumentId: (string) $row['vault_document_id'],
            organizationId: (int) $row['organization_id'],
            versionNumber: (int) $row['version_number'],
            filePath: (string) $row['file_path'],
            fileSha256: (string) $row['file_sha256'],
            mimeType: (string) $row['mime_type'],
            originalFilename: (string) $row['original_filename'],
            fileSizeBytes: (int) $row['file_size_bytes'],
            source: (string) $row['source'],
            uploadedAt: isset($row['uploaded_at']) ? (string) $row['uploaded_at'] : null,
            uploadedBy: isset($row['uploaded_by']) ? (int) $row['uploaded_by'] : null,
        );
    }
}
