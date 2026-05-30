<?php

declare(strict_types=1);

namespace NeneVault\Document;

final readonly class UploadDocumentOutput
{
    public function __construct(
        public VaultDocument $document,
        public string $fileSha256,
        public string $mimeType,
        public string $originalFilename,
        public int $fileSizeBytes,
        public int $versionNumber,
    ) {
    }
}
