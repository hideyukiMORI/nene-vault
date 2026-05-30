<?php

declare(strict_types=1);

namespace NeneVault\User;

use RuntimeException;

final class UserNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("User with id {$id} was not found.");
    }
}
