<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\DocumentVersion\DocumentVersion;

interface VaultDocumentRepositoryInterface
{
    public function save(VaultDocument $document): void;

    public function findById(string $id, int $organizationId): ?VaultDocument;

    public function updateCurrentVersion(string $id, int $organizationId, string $currentVersionId): void;

    /**
     * Search documents, joining each to its current version's file metadata.
     *
     * @return list<array{0: VaultDocument, 1: DocumentVersion}>
     */
    public function search(DocumentSearchCriteria $criteria): array;

    public function countByCriteria(DocumentSearchCriteria $criteria): int;
}
