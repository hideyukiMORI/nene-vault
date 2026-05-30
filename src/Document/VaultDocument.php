<?php

declare(strict_types=1);

namespace NeneVault\Document;

final readonly class VaultDocument
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public int $organizationId,
        public string $currentVersionId,
        public string $status,
        public ?string $transactionDate,
        public ?int $amountCents,
        public string $counterpartyName,
        public string $category,
        public array $tags,
        public bool $dateUncertain,
        public bool $isMetadataConfirmed,
        public int $retentionYears,
        public string $retentionExpiresAt,
        public ?string $uploadedAt = null,
        public ?int $uploadedBy = null,
        public ?string $voidedAt = null,
        public ?int $voidedBy = null,
        public ?string $voidReason = null,
        public ?string $voidNote = null,
    ) {
    }
}
