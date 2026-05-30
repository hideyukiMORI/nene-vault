<?php

declare(strict_types=1);

namespace NeneVault\DocumentVersion;

interface DocumentVersionRepositoryInterface
{
    public function save(DocumentVersion $version): void;

    public function findById(string $id, int $organizationId): ?DocumentVersion;

    /** @return list<DocumentVersion> */
    public function listByDocumentId(string $vaultDocumentId, int $organizationId): array;

    public function existsBySha256(string $fileSha256, int $organizationId): bool;

    public function nextVersionNumber(string $vaultDocumentId, int $organizationId): int;
}
