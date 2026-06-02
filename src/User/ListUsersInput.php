<?php

declare(strict_types=1);

namespace NeneVault\User;

final readonly class ListUsersInput
{
    public function __construct(
        public int $organizationId,
        public int $limit,
        public int $offset,
    ) {
    }
}
