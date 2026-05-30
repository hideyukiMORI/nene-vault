<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use NeneVault\VaultSettings\VaultSettingsSeederInterface;

final readonly class CreateOrganizationUseCase implements CreateOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private VaultSettingsSeederInterface $settingsSeeder,
    ) {
    }

    public function execute(CreateOrganizationInput $input): CreateOrganizationOutput
    {
        $existing = $this->organizations->findBySlug($input->slug);

        if ($existing !== null) {
            throw new OrganizationSlugConflictException($input->slug);
        }

        $id = $this->organizations->save(new Organization(
            name: $input->name,
            slug: $input->slug,
            plan: $input->plan,
            isActive: $input->isActive,
            externalId: $input->externalId,
            customDomain: $input->customDomain,
        ));

        // Seed default vault settings for the new organization
        $this->settingsSeeder->seed($id);

        $org = $this->organizations->findById($id);
        assert($org !== null);

        return new CreateOrganizationOutput(
            id: $org->id ?? $id,
            name: $org->name,
            slug: $org->slug,
            plan: $org->plan,
            isActive: $org->isActive,
            externalId: $org->externalId,
            customDomain: $org->customDomain,
            createdAt: $org->createdAt ?? '',
        );
    }
}
