<?php

declare(strict_types=1);

namespace NeneVault\Organization\Resolution;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneVault\Organization\OrganizationRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves the current organization from the request and stores its ID
 * in a RequestScopedHolder for downstream repositories to read.
 *
 * Bypass paths (superadmin org management, health, auth) skip resolution
 * and pass through with org ID unset.
 *
 * Resolution order:
 *  1. strategy->resolve() → slug or custom domain identifier
 *  2. OrganizationRepository::findBySlug() → Organization
 *  3. If not found by slug, try findByCustomDomain()
 *  4. 404 if still not found
 */
final readonly class OrgResolverMiddleware implements MiddlewareInterface
{
    /**
     * Paths that bypass org resolution entirely.
     *
     * @var list<string>
     */
    private const BYPASS_PREFIXES = [
        // Fixed-demo seat page (#127): org-less at entry — it looks the demo
        // org up itself and mints the session the SPA then uses.
        '/demo/',
        '/health',
        '/admin/organizations',
        '/admin/auth/',
    ];

    /**
     * @param RequestScopedHolder<int> $orgId
     */
    public function __construct(
        private RequestScopedHolder $orgId,
        private OrganizationRepositoryInterface $repository,
        private ProblemDetailsResponseFactory $problemDetails,
        private OrgResolutionStrategyInterface $strategy,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        foreach (self::BYPASS_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $handler->handle($request);
            }
        }

        $identifier = $this->strategy->resolve($request);

        if ($identifier === null) {
            return $this->problemDetails->create(
                $request,
                'org-not-resolved',
                'Organization Not Resolved',
                404,
                'Could not determine the organization for this request. Check your TENANT_RESOLUTION configuration.',
            );
        }

        $org = $this->repository->findBySlug($identifier)
            ?? $this->repository->findByCustomDomain($identifier);

        if ($org === null) {
            return $this->problemDetails->create(
                $request,
                'org-not-found',
                'Organization Not Found',
                404,
                "No organization found for '{$identifier}'.",
            );
        }

        if (!$org->isActive) {
            return $this->problemDetails->create(
                $request,
                'org-inactive',
                'Organization Inactive',
                403,
                'This organization is currently inactive.',
            );
        }

        $this->orgId->set($org->id ?? 0);

        return $handler->handle(
            $request->withAttribute('nene2.org.id', $org->id)
                     ->withAttribute('nene2.org.slug', $org->slug),
        );
    }
}
