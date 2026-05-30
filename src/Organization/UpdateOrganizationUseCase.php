<?php

declare(strict_types=1);

namespace NeneVault\Organization;

final readonly class UpdateOrganizationUseCase implements UpdateOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(UpdateOrganizationInput $input): UpdateOrganizationOutput
    {
        $org = $this->organizations->findById($input->id);

        if ($org === null) {
            throw new OrganizationNotFoundException($input->id);
        }

        $updated = new Organization(
            name: $input->name,
            slug: $input->slug,
            plan: $input->plan,
            isActive: $input->isActive,
            id: $input->id,
            externalId: $input->externalId,
            customDomain: $input->customDomain,
        );

        $this->organizations->update($updated);

        $refreshed = $this->organizations->findById($input->id);
        assert($refreshed !== null);

        return new UpdateOrganizationOutput(
            id: $refreshed->id ?? $input->id,
            name: $refreshed->name,
            slug: $refreshed->slug,
            plan: $refreshed->plan,
            isActive: $refreshed->isActive,
            externalId: $refreshed->externalId,
            customDomain: $refreshed->customDomain,
            updatedAt: $refreshed->updatedAt ?? '',
        );
    }
}
