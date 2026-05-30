<?php

declare(strict_types=1);

namespace NeneVault\Organization;

final readonly class ListOrganizationsUseCase implements ListOrganizationsUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(ListOrganizationsInput $input): ListOrganizationsOutput
    {
        return new ListOrganizationsOutput(
            items: $this->organizations->findAll($input->limit, $input->offset),
            total: $this->organizations->count(),
            limit: $input->limit,
            offset: $input->offset,
        );
    }
}
