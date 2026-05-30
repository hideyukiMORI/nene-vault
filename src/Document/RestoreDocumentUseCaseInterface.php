<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\DocumentVersion\DocumentVersion;

interface RestoreDocumentUseCaseInterface
{
    /**
     * @return array{0: VaultDocument, 1: DocumentVersion}
     * @throws VaultDocumentNotFoundException
     */
    public function execute(string $documentId, int $organizationId, ?int $actorUserId): array;
}
