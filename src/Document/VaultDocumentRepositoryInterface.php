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
     * Update editable metadata fields. File bytes are never touched.
     *
     * @param list<string> $tags
     */
    public function updateMetadata(
        string $id,
        int $organizationId,
        ?string $transactionDate,
        ?int $amountCents,
        string $counterpartyName,
        string $category,
        array $tags,
        bool $dateUncertain,
    ): void;

    public function void(string $id, int $organizationId, int $voidedBy, string $voidReason, ?string $voidNote): void;

    public function restore(string $id, int $organizationId): void;

    /**
     * Search documents, joining each to its current version's file metadata.
     *
     * @return list<array{0: VaultDocument, 1: DocumentVersion}>
     */
    public function search(DocumentSearchCriteria $criteria): array;

    public function countByCriteria(DocumentSearchCriteria $criteria): int;
}
