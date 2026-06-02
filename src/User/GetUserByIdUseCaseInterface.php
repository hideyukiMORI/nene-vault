<?php

declare(strict_types=1);

namespace NeneVault\User;

use NeneVault\Auth\User;

interface GetUserByIdUseCaseInterface
{
    /**
     * @throws UserNotFoundException when the user does not exist or is not in the organization
     */
    public function execute(int $id, int $organizationId): User;
}
