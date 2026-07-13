<?php

declare(strict_types=1);

namespace NeneVault\Tests\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use NeneVault\Auth\CapabilityMiddleware;
use NeneVault\Tests\Support\SpyRequestHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Capability enforcement + organization-scope binding.
 *
 * The org-scope check runs for every authenticated, org-scoped request
 * regardless of whether the route maps to a capability — so an admin route with
 * no capability mapping stays tenant-bound rather than skipping the check
 * (defense-in-depth, security assessment 2026-07-14; the class of gap that let a
 * JWT be replayed cross-tenant on unmapped routes in a sibling product).
 */
final class CapabilityMiddlewareTest extends TestCase
{
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    private function middleware(): CapabilityMiddleware
    {
        return new CapabilityMiddleware(
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-vault.dev/problems/'),
        );
    }

    /**
     * @param array<string, mixed>|null $claims
     */
    private function request(
        string $method,
        string $path,
        ?array $claims = null,
        ?int $resolvedOrgId = null,
    ): ServerRequestInterface {
        $request = $this->psr17->createServerRequest($method, $path);

        if ($claims !== null) {
            $request = $request->withAttribute('nene2.auth.claims', $claims);
        }

        if ($resolvedOrgId !== null) {
            $request = $request->withAttribute('nene2.org.id', $resolvedOrgId);
        }

        return $request;
    }

    public function test_unauthenticated_request_passes_through(): void
    {
        $handler = new SpyRequestHandler($this->psr17);

        $response = $this->middleware()->process(
            $this->request('GET', '/admin/vault/documents'),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->captured);
    }

    public function test_capability_is_enforced_on_a_mapped_route(): void
    {
        $handler = new SpyRequestHandler($this->psr17);

        // Viewer cannot upload (POST /admin/vault/documents → UploadDocument).
        $response = $this->middleware()->process(
            $this->request('POST', '/admin/vault/documents', ['role' => 'viewer', 'org' => 1], resolvedOrgId: 1),
            $handler,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertNull($handler->captured);
        self::assertSame('forbidden', $this->problemType($response));
    }

    public function test_cross_org_is_denied_on_a_mapped_route(): void
    {
        $handler = new SpyRequestHandler($this->psr17);

        $response = $this->middleware()->process(
            $this->request('GET', '/admin/vault/documents', ['role' => 'admin', 'org' => 1], resolvedOrgId: 2),
            $handler,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertNull($handler->captured);
        self::assertSame('org-access-denied', $this->problemType($response));
    }

    /**
     * The regression that matters: a route with NO capability mapping must still
     * be org-bound. Before the 2026-07-14 hardening the middleware returned early
     * for unmapped routes and skipped the org-scope check entirely.
     */
    public function test_unmapped_route_still_denies_cross_org(): void
    {
        $handler = new SpyRequestHandler($this->psr17);

        $response = $this->middleware()->process(
            // `/admin/vault/reports` maps to no capability in CapabilityResolver.
            $this->request('GET', '/admin/vault/reports', ['role' => 'admin', 'org' => 1], resolvedOrgId: 2),
            $handler,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertNull($handler->captured, 'cross-org request must not reach the handler on an unmapped route');
        self::assertSame('org-access-denied', $this->problemType($response));
    }

    public function test_unmapped_route_passes_when_org_matches(): void
    {
        $handler = new SpyRequestHandler($this->psr17);

        $response = $this->middleware()->process(
            $this->request('GET', '/admin/vault/reports', ['role' => 'admin', 'org' => 1], resolvedOrgId: 1),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->captured);
    }

    public function test_superadmin_bypasses_org_binding(): void
    {
        $handler = new SpyRequestHandler($this->psr17);

        // Superadmin on an unmapped route with a resolved org that is not theirs.
        $response = $this->middleware()->process(
            $this->request('GET', '/admin/vault/reports', ['role' => 'superadmin', 'org' => null], resolvedOrgId: 2),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->captured);
    }

    private function problemType(\Psr\Http\Message\ResponseInterface $response): string
    {
        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);

        return basename((string) $body['type']);
    }
}
