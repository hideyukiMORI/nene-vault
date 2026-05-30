<?php

declare(strict_types=1);

namespace NeneVault\User;

use NeneVault\Auth\User;

interface UpdateUserUseCaseInterface
{
    /**
     * @throws UserNotFoundException
     * @throws InvalidUserRoleException
     * @throws UserEmailConflictException
     */
    public function execute(
        int $id,
        int $organizationId,
        ?string $email,
        ?string $role,
        ?string $status,
        ?int $actorUserId,
    ): User;
}
