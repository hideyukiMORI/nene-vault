<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

final readonly class VaultSettings
{
    public function __construct(
        public int $organizationId,
        public int $retentionYears = 10,
        public ?string $storagePathOverride = null,
        public ?string $invoiceApiBaseUrl = null,
        public ?string $invoiceApiToken = null,
        public ?string $clearApiBaseUrl = null,
        public ?string $clearApiToken = null,
        public ?string $updatedAt = null,
        public ?int $updatedBy = null,
    ) {
    }
}
