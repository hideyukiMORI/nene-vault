<?php

declare(strict_types=1);

namespace NeneVault\Document;

interface DownloadDocumentVersionUseCaseInterface
{
    /**
     * @return array{absolute_path: string, mime_type: string, filename: string}
     * @throws VaultDocumentNotFoundException
     * @throws FileIntegrityException
     */
    public function execute(string $documentId, string $versionId, int $organizationId): array;
}
