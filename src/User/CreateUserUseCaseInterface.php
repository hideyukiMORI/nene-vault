<?php

declare(strict_types=1);

namespace NeneVault\User;

use NeneVault\Auth\User;

interface CreateUserUseCaseInterface
{
    /**
     * @throws InvalidUserRoleException
     * @throws UserEmailConflictException
     */
    public function execute(
        string $email,
        string $password,
        string $role,
        int $organizationId,
        ?int $actorUserId,
    ): User;
}
