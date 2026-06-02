<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

interface GetVaultSettingsUseCaseInterface
{
    /**
     * Returns the organization's vault settings, or default settings when none
     * have been persisted yet.
     */
    public function execute(int $organizationId): VaultSettings;
}
