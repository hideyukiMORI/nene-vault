<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use RuntimeException;

final class OrganizationSlugConflictException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("An organization with slug '{$slug}' already exists.");
    }
}
