<?php

declare(strict_types=1);

namespace NeneVault\Organization;

final readonly class UpdateOrganizationInput
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $plan,
        public bool $isActive,
        public ?string $externalId,
        public ?string $customDomain,
    ) {
    }
}
