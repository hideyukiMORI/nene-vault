<?php

declare(strict_types=1);

namespace NeneVault\Organization;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(int $id): void
    {
        $this->organizations->delete($id);
    }
}
