<?php

declare(strict_types=1);

namespace NeneVault\User;

use NeneVault\Auth\User;
use NeneVault\Auth\UserRepositoryInterface;

final readonly class GetUserByIdUseCase implements GetUserByIdUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function execute(int $id, int $organizationId): User
    {
        $user = $this->users->findById($id);

        // Org-scoped: only users in the resolved organization are visible
        if ($user === null || $user->organizationId !== $organizationId) {
            throw new UserNotFoundException($id);
        }

        return $user;
    }
}
