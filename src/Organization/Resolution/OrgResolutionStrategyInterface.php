<?php

declare(strict_types=1);

namespace NeneVault\Organization\Resolution;

use Psr\Http\Message\ServerRequestInterface;

interface OrgResolutionStrategyInterface
{
    /** Returns an org slug or custom domain identifier, or null when the strategy cannot resolve. */
    public function resolve(ServerRequestInterface $request): ?string;
}
