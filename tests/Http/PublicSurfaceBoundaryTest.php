<?php

declare(strict_types=1);

namespace NeneVault\Tests\Http;

use NeneVault\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins the unauthenticated public surface (#157): with the NENE2
 * BearerTokenMiddleware in blocklist mode, EVERY path requires a bearer token
 * except the explicit `excludedPaths` list in RuntimeServiceProvider.
 *
 * If a route is added and this test starts failing, that is the point: opening
 * a path is a deliberate edit to the exclusion list, not a routing accident.
 */
final class PublicSurfaceBoundaryTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::ensureOrg('test-org');
    }

    // ── Open paths (the whole public surface) ──────────────────────────────────

    public function test_health_is_open(): void
    {
        $resp = $this->handler()->handle($this->request('GET', '/health'));
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_login_is_open(): void
    {
        // 422 (validation), not 401: the request reached the handler unauthenticated.
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => 'x@example.com']),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_demo_standard_is_open(): void
    {
        // Reaches the demo handler unauthenticated (any non-401 outcome:
        // throttle 429 / seat 200 / fail-close 404 depending on env state).
        $resp = $this->handler()->handle($this->request('GET', '/demo/standard'));
        $this->assertNotSame(401, $resp->getStatusCode());
    }

    public function test_demo_guided_is_open(): void
    {
        $resp = $this->handler()->handle($this->request('GET', '/demo/guided'));
        $this->assertNotSame(401, $resp->getStatusCode());
    }

    // ── Everything else requires a token ───────────────────────────────────────

    /** @return iterable<string, array{string, string}> */
    public static function protectedPathProvider(): iterable
    {
        yield 'documents list' => ['GET', '/admin/vault/documents'];
        yield 'document create' => ['POST', '/admin/vault/documents'];
        yield 'users list' => ['GET', '/admin/users'];
        yield 'organizations list' => ['GET', '/admin/organizations'];
        yield 'audit events' => ['GET', '/admin/audit-events'];
        yield 'settings' => ['GET', '/admin/vault/settings'];
        yield 'export' => ['GET', '/admin/vault/export'];
        yield 'auth sibling path (not the login route)' => ['GET', '/admin/auth/logout'];
        yield 'unknown admin path' => ['GET', '/admin/does-not-exist'];
        yield 'unknown demo template' => ['GET', '/demo/bogus'];
        yield 'root' => ['GET', '/'];
        yield 'arbitrary path' => ['GET', '/anything'];
    }

    #[DataProvider('protectedPathProvider')]
    public function test_everything_else_requires_a_bearer_token(string $method, string $path): void
    {
        $resp = $this->handler()->handle($this->request($method, $path));

        $this->assertSame(401, $resp->getStatusCode(), "{$method} {$path} must be 401 when unauthenticated");
        $this->assertSame(
            'https://nene-vault.dev/problems/unauthorized',
            json_decode((string) $resp->getBody(), true)['type'] ?? null,
        );
    }
}
