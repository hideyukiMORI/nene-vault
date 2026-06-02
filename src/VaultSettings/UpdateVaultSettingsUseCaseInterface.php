<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

interface UpdateVaultSettingsUseCaseInterface
{
    /**
     * Persists the organization's vault settings (insert or update) and records
     * the change in the audit trail with before/after state.
     */
    public function execute(UpdateVaultSettingsInput $input): VaultSettings;
}
