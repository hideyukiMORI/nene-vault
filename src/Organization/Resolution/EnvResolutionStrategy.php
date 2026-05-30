<?php

declare(strict_types=1);

namespace NeneVault\Organization\Resolution;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves org slug from the ORG_SLUG environment variable.
 *
 * For local development and single-server deployments.
 * Returns null when ORG_SLUG is not set.
 */
final readonly class EnvResolutionStrategy implements OrgResolutionStrategyInterface
{
    public function __construct(
        private ?string $orgSlug,
    ) {
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        return ($this->orgSlug !== null && $this->orgSlug !== '') ? $this->orgSlug : null;
    }
}
