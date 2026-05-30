<?php

declare(strict_types=1);

namespace NeneVault\Document;

final readonly class DocumentSearchCriteria
{
    public function __construct(
        public int $organizationId,
        public ?string $transactionDateFrom = null,
        public ?string $transactionDateTo = null,
        public ?int $amountMinCents = null,
        public ?int $amountMaxCents = null,
        public ?string $counterpartyName = null,
        public ?string $category = null,
        public bool $includeVoided = false,
        public int $limit = 20,
        public int $offset = 0,
    ) {
    }
}
