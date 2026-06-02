<?php

declare(strict_types=1);

namespace NeneVault\User;

use NeneVault\Auth\UserRepositoryInterface;

final readonly class ListUsersUseCase implements ListUsersUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(ListUsersInput $input): ListUsersOutput
    {
        return new ListUsersOutput(
            items: $this->users->listByOrganizationId($input->organizationId, $input->limit, $input->offset),
            total: $this->users->countByOrganizationId($input->organizationId),
            limit: $input->limit,
            offset: $input->offset,
        );
    }
}
