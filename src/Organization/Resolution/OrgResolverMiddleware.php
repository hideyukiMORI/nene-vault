<?php

declare(strict_types=1);

namespace NeneVault\Organization\Resolution;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneVault\Organization\Organization;
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
 * Resolution order (#141):
 *  1. Verified bearer claims (`nene2.auth.claims`, set by the NENE2 BearerTokenMiddleware,
 *     which runs before this middleware): an integer `org` claim resolves the
 *     org by ID. The claim is signed, so the authenticated user's own tenant
 *     always wins over the host — this is what lets a disposable demo org work
 *     on a single-domain deployment where the host strategy would resolve the
 *     fixed ORG_SLUG org. A claim naming a missing org (e.g. a demo org already
 *     swept) fails closed with 404. Superadmin tokens carry `org: null` and
 *     fall through to the strategy, preserving cross-tenant behaviour.
 *  2. strategy->resolve() → slug or custom domain identifier
 *     (env / subdomain / custom_domain — unchanged, and still the only path
 *     for unauthenticated requests).
 *  3. OrganizationRepository::findBySlug() → Organization
 *  4. If not found by slug, try findByCustomDomain()
 *  5. 404 if still not found
 */
final readonly class OrgResolverMiddleware implements MiddlewareInterface
{
    /**
     * Paths that bypass org resolution entirely.
     *
     * @var list<string>
     */
    private const BYPASS_PREFIXES = [
        // Demo entry routes (#127, #141): org-less at entry — the disposable
        // start handler creates its own org; the fixed seat page looks the
        // demo org up itself and mints the session the SPA then uses.
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

        $claimOrgId = $this->claimOrgId($request);

        if ($claimOrgId !== null) {
            $org = $this->repository->findById($claimOrgId);

            if ($org === null) {
                return $this->problemDetails->create(
                    $request,
                    'org-not-found',
                    'Organization Not Found',
                    404,
                    'The organization this session belongs to no longer exists.',
                );
            }

            return $this->continueWith($request, $handler, $org);
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

        return $this->continueWith($request, $handler, $org);
    }

    /**
     * Integer `org` claim from the verified bearer token (fleet-standard
     * schema, #150), or null when the request is unauthenticated or the token
     * carries no tenant (superadmin).
     */
    private function claimOrgId(ServerRequestInterface $request): ?int
    {
        $claims = $request->getAttribute('nene2.auth.claims');

        if (!is_array($claims) || !isset($claims['org']) || !is_int($claims['org'])) {
            return null;
        }

        return $claims['org'];
    }

    private function continueWith(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        Organization $org,
    ): ResponseInterface {
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
