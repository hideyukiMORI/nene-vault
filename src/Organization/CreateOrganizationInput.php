<?php

declare(strict_types=1);

namespace NeneVault\Organization;

final readonly class CreateOrganizationInput
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $plan = 'free',
        public bool $isActive = true,
        public ?string $externalId = null,
        public ?string $customDomain = null,
    ) {
    }
}
