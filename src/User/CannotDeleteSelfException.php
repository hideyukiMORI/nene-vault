<?php

declare(strict_types=1);

namespace NeneVault\User;

use RuntimeException;

final class CannotDeleteSelfException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You cannot delete your own account.');
    }
}
