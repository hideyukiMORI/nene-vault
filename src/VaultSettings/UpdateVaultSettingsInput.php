<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

final readonly class UpdateVaultSettingsInput
{
    public function __construct(
        public int $organizationId,
        public int $retentionYears,
        public ?string $storagePathOverride,
        public ?string $invoiceApiBaseUrl,
        public ?string $clearApiBaseUrl,
        public ?int $actorUserId,
    ) {
    }
}
