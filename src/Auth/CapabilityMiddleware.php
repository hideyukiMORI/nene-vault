<?php

declare(strict_types=1);

namespace NeneVault\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Enforces role-based capabilities and organization scoping on authenticated requests.
 *
 * Runs after the NENE2 BearerTokenMiddleware. Unauthenticated requests pass through unchanged.
 *
 * Organization scoping:
 *  - superadmin: no org check — operates across all organizations
 *  - admin/member/viewer: JWT `org` claim must match the resolved org ID (nene2.org.id attribute)
 */
final readonly class CapabilityMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $claims = $request->getAttribute('nene2.auth.claims');

        if (!is_array($claims)) {
            return $handler->handle($request);
        }

        $role = Role::tryFrom((string) ($claims['role'] ?? ''));

        // Organization scope binding runs for EVERY authenticated, org-scoped
        // request — independent of whether the route maps to a capability.
        // Superadmin (cross-org by design) bypasses; org-agnostic bypass routes
        // never set `nene2.org.id` so they are unaffected. Running this before
        // (and regardless of) the capability lookup keeps an admin route with no
        // capability mapping org-bound instead of skipping the check — the class
        // of gap that let a JWT be replayed cross-tenant on unmapped routes in a
        // sibling product (security assessment 2026-07-14, defense-in-depth).
        if ($role !== Role::Superadmin) {
            $resolvedOrgId = $request->getAttribute('nene2.org.id');

            if (is_int($resolvedOrgId)) {
                $jwtOrgId = isset($claims['org']) && is_int($claims['org'])
                    ? $claims['org']
                    : null;

                if ($jwtOrgId !== $resolvedOrgId) {
                    return $this->problemDetails->create(
                        $request,
                        'org-access-denied',
                        'Organization Access Denied',
                        403,
                        'Access to this organization is not permitted.',
                    );
                }
            }
        }

        $path = $request->getUri()->getPath() ?: '/';
        $required = CapabilityResolver::resolve($path, $request->getMethod());

        if ($required === null) {
            return $handler->handle($request);
        }

        if ($role === null || !$role->hasCapability($required)) {
            return $this->problemDetails->create(
                $request,
                'forbidden',
                'Forbidden',
                403,
                'You do not have permission to perform this action.',
            );
        }

        return $handler->handle($request);
    }
}
