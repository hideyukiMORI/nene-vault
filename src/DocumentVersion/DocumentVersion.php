<?php

declare(strict_types=1);

namespace NeneVault\DocumentVersion;

final readonly class DocumentVersion
{
    public function __construct(
        public string $id,
        public string $vaultDocumentId,
        public int $organizationId,
        public int $versionNumber,
        public string $filePath,
        public string $fileSha256,
        public string $mimeType,
        public string $originalFilename,
        public int $fileSizeBytes,
        public string $source,
        public ?string $uploadedAt = null,
        public ?int $uploadedBy = null,
    ) {
    }
}
