<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

interface VaultSettingsSeederInterface
{
    /** Creates default VaultSettings for a newly created organization. */
    public function seed(int $organizationId): void;
}
