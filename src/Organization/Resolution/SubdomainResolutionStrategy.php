<?php

declare(strict_types=1);

namespace NeneVault\Organization\Resolution;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves org slug from subdomain: org1.nene-vault.example.com → "org1".
 *
 * Configure BASE_DOMAIN=nene-vault.example.com in .env.
 * Requests to the bare base domain (no subdomain) return null.
 */
final readonly class SubdomainResolutionStrategy implements OrgResolutionStrategyInterface
{
    public function __construct(
        private string $baseDomain,
    ) {
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        $host = $request->getUri()->getHost();

        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        $baseParts = explode('.', $this->baseDomain);
        $hostParts = explode('.', $host);

        if (count($hostParts) <= count($baseParts)) {
            return null;
        }

        $tail = array_slice($hostParts, -count($baseParts));

        if ($tail !== $baseParts) {
            return null;
        }

        return $hostParts[0];
    }
}
