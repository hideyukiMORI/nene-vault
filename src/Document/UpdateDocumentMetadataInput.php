<?php

declare(strict_types=1);

namespace NeneVault\Document;

final readonly class UpdateDocumentMetadataInput
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $documentId,
        public int $organizationId,
        public ?string $transactionDate,
        public ?int $amountCents,
        public string $counterpartyName,
        public string $category,
        public array $tags,
        public ?int $actorUserId = null,
    ) {
    }
}
