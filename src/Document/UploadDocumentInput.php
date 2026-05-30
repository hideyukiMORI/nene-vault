<?php

declare(strict_types=1);

namespace NeneVault\Document;

final readonly class UploadDocumentInput
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public int $organizationId,
        public string $tmpPath,
        public string $originalFilename,
        public string $mimeType,
        public int $fileSizeBytes,
        public string $counterpartyName,
        public string $category,
        public ?string $transactionDate = null,
        public ?int $amountCents = null,
        public array $tags = [],
        public string $source = 'web_upload',
        public bool $confirmDuplicate = false,
        public ?int $actorUserId = null,
    ) {
    }
}
