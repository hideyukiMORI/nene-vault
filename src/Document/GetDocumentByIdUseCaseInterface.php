<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\DocumentVersion\DocumentVersion;

interface GetDocumentByIdUseCaseInterface
{
    /**
     * @return array{0: VaultDocument, 1: DocumentVersion}
     * @throws VaultDocumentNotFoundException
     */
    public function execute(string $id, int $organizationId): array;
}
