<?php

declare(strict_types=1);

namespace NeneVault\Export;

final readonly class ExportDocumentsInput
{
    public function __construct(
        public int $organizationId,
        public ?string $transactionDateFrom = null,
        public ?string $transactionDateTo = null,
        public ?string $counterpartyName = null,
        public bool $includeVoided = false,
        public ?int $actorUserId = null,
        public string $format = 'csv',
    ) {
    }
}
