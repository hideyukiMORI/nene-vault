<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

interface VaultSettingsRepositoryInterface
{
    public function findByOrganizationId(int $organizationId): ?VaultSettings;

    public function save(VaultSettings $settings): void;

    public function update(VaultSettings $settings): void;
}
