<?php

declare(strict_types=1);

namespace NeneVault\Organization;

final readonly class ListOrganizationsInput
{
    public function __construct(
        public int $limit = 20,
        public int $offset = 0,
    ) {
    }
}
