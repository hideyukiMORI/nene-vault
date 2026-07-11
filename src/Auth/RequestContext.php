<?php

declare(strict_types=1);

namespace NeneVault\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads authenticated-actor and tenant context from the request attributes set
 * by the auth and org-resolver middleware.
 *
 * Centralizes the `nene2.auth.claims` / `nene2.org.id` attribute keys and the
 * claim-shape guards that were previously copy-pasted into every handler.
 */
final class RequestContext
{
    private const CLAIMS_ATTRIBUTE = 'nene2.auth.claims';
    private const ORG_ATTRIBUTE = 'nene2.org.id';

    /**
     * Authenticated user id from the bearer-token claims (fleet-standard
     * `sub` = user id, #150), or null when the subject is absent or not a
     * user id (e.g. service tokens with a string subject).
     */
    public static function actorUserId(ServerRequestInterface $request): ?int
    {
        $claims = self::claims($request);

        return isset($claims['sub']) && is_int($claims['sub']) ? $claims['sub'] : null;
    }

    /**
     * Authenticated actor's role, or null when the claim is missing or unknown.
     */
    public static function role(ServerRequestInterface $request): ?Role
    {
        return Role::tryFrom((string) (self::claims($request)['role'] ?? ''));
    }

    /**
     * Resolved tenant id. Asserts the org-resolver middleware ran — callers that
     * require a tenant context use this on routes already scoped by that middleware.
     */
    public static function organizationId(ServerRequestInterface $request): int
    {
        $orgId = $request->getAttribute(self::ORG_ATTRIBUTE);
        assert(is_int($orgId));

        return $orgId;
    }

    /**
     * Resolved tenant id, or null when no tenant was resolved (e.g. superadmin
     * operating cross-tenant).
     */
    public static function organizationIdOrNull(ServerRequestInterface $request): ?int
    {
        $orgId = $request->getAttribute(self::ORG_ATTRIBUTE);

        return is_int($orgId) ? $orgId : null;
    }

    /** @return array<string, mixed> */
    private static function claims(ServerRequestInterface $request): array
    {
        $claims = $request->getAttribute(self::CLAIMS_ATTRIBUTE);

        return is_array($claims) ? $claims : [];
    }
}
