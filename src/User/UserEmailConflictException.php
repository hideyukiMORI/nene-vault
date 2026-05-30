<?php

declare(strict_types=1);

namespace NeneVault\User;

use RuntimeException;

final class UserEmailConflictException extends RuntimeException
{
    public function __construct(string $email)
    {
        parent::__construct("A user with email '{$email}' already exists.");
    }
}
