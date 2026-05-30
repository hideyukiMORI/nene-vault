<?php

declare(strict_types=1);

namespace NeneVault\User;

interface DeleteUserUseCaseInterface
{
    /**
     * @throws UserNotFoundException
     * @throws CannotDeleteSelfException
     */
    public function execute(int $id, int $organizationId, ?int $actorUserId): void;
}
