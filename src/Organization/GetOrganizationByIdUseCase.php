<?php

declare(strict_types=1);

namespace NeneVault\Organization;

final readonly class GetOrganizationByIdUseCase implements GetOrganizationByIdUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(GetOrganizationByIdInput $input): GetOrganizationByIdOutput
    {
        $org = $this->organizations->findById($input->id);

        if ($org === null) {
            throw new OrganizationNotFoundException($input->id);
        }

        return new GetOrganizationByIdOutput(
            id: $org->id ?? $input->id,
            name: $org->name,
            slug: $org->slug,
            plan: $org->plan,
            isActive: $org->isActive,
            externalId: $org->externalId,
            customDomain: $org->customDomain,
            createdAt: $org->createdAt ?? '',
            updatedAt: $org->updatedAt ?? '',
        );
    }
}
