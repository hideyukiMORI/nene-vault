<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

final readonly class GetVaultSettingsUseCase implements GetVaultSettingsUseCaseInterface
{
    public function __construct(
        private VaultSettingsRepositoryInterface $settings,
    ) {
    }

    public function execute(int $organizationId): VaultSettings
    {
        return $this->settings->findByOrganizationId($organizationId)
            ?? new VaultSettings(organizationId: $organizationId);
    }
}
