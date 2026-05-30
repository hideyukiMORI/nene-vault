<?php

declare(strict_types=1);

namespace NeneVault\Document;

interface VaultDocumentRepositoryInterface
{
    public function save(VaultDocument $document): void;

    public function findById(string $id, int $organizationId): ?VaultDocument;

    public function updateCurrentVersion(string $id, int $organizationId, string $currentVersionId): void;
}
