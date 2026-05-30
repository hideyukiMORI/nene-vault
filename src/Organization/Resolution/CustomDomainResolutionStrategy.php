<?php

declare(strict_types=1);

namespace NeneVault\Organization\Resolution;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves org by the full request host, looked up against the custom_domain column.
 *
 * For white-label / bring-your-own-domain deployments.
 */
final readonly class CustomDomainResolutionStrategy implements OrgResolutionStrategyInterface
{
    public function resolve(ServerRequestInterface $request): ?string
    {
        $host = $request->getUri()->getHost();

        if ($host === '') {
            return null;
        }

        // Strip port if present
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        return $host;
    }
}
