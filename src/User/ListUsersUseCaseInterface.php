<?php

declare(strict_types=1);

namespace NeneVault\User;

interface ListUsersUseCaseInterface
{
    public function execute(ListUsersInput $input): ListUsersOutput;
}
