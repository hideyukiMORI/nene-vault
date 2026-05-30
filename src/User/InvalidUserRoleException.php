<?php

declare(strict_types=1);

namespace NeneVault\User;

use RuntimeException;

final class InvalidUserRoleException extends RuntimeException
{
    public function __construct(string $role)
    {
        parent::__construct("Role '{$role}' cannot be assigned via the organization user API.");
    }
}
