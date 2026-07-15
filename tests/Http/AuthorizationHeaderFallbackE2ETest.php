<?php

declare(strict_types=1);

namespace NeneVault\Tests\Http;

use Nene2\Auth\TokenIssuerInterface;
use NeneVault\Http\RuntimeContainerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * End-to-end proof that the opt-in X-Authorization fallback receiver (NENE2 #1558 /
 * ADR 0019) is wired into this product's runtime pipeline.
 *
 * The admin SPA mirrors the token into `X-Authorization: Bearer <token>` so that
 * shared hosting (HETEML-type Tier A) — where an upstream proxy strips the standard
 * `Authorization` header before PHP sees it — can still authenticate (#118).
 * `RuntimeServiceProvider` now enables the receiver via
 * `enableAuthorizationHeaderFallback: true`, replacing the product's former bespoke
 * `NeneVault\Http\AuthorizationHeaderFallback` (deleted in #209) with the framework's
 * `AuthorizationHeaderFallbackMiddleware`, which restores `Authorization` from the
 * mirror (only when `Authorization` is absent/empty) at the head of the auth stage,
 * before the bearer auth middleware runs.
 *
 * `GET /admin/organizations` is bearer-protected (superadmin capability) but bypasses
 * org resolution (`OrgResolverMiddleware::BYPASS_PREFIXES`), so these assertions
 * isolate the credential-restoration behaviour with no seeded tenant.
 *
 * The tests fail if the opt-in flag is removed from RuntimeServiceProvider: a
 * mirror-only request would then never restore `Authorization` and would be
 * rejected as `missing_token`.
 */
final class AuthorizationHeaderFallbackE2ETest extends TestCase
{
    private const PROTECTED_PATH = '/admin/organizations';

    private static ?ContainerInterface $container = null;

    public static function setUpBeforeClass(): void
    {
        self::$container = (new RuntimeContainerFactory(dirname(__DIR__, 2)))->create();
    }

    /**
     * The mirror end-to-end proof: a valid bearer token supplied ONLY in the
     * `X-Authorization` header (no standard `Authorization`) is restored by the
     * fallback receiver and accepted by the bearer auth stage — the request passes
     * authentication.
     *
     * The bearer middleware is the only thing that issues a `WWW-Authenticate`
     * challenge; its absence proves authentication succeeded (any further error
     * response here would be downstream of auth, which is out of scope for the
     * transport-level mirror proof).
     */
    public function test_valid_token_in_mirror_only_passes_authentication(): void
    {
        [$handler, $creator] = $this->makeHandlerAndCreator();
        $token = $this->issueSuperadminToken();

        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => self::PROTECTED_PATH, 'HTTP_HOST' => 'localhost'],
            headers: ['X-Authorization' => 'Bearer ' . $token],
        );

        $response = $handler->handle($request);

        self::assertSame(
            '',
            $response->getHeaderLine('WWW-Authenticate'),
            'A valid token mirrored only into X-Authorization must pass the bearer auth stage (no challenge issued).',
        );
    }

    /**
     * The auth stage actually receives the mirrored credential: an INVALID token
     * in `X-Authorization` only is rejected as `invalid_token` (the bearer
     * middleware saw a token), NOT `missing_token` — which is only possible if the
     * fallback receiver restored `Authorization` from the mirror before auth ran.
     */
    public function test_invalid_token_in_mirror_only_reaches_bearer_stage_as_invalid_not_missing(): void
    {
        [$handler, $creator] = $this->makeHandlerAndCreator();

        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => self::PROTECTED_PATH, 'HTTP_HOST' => 'localhost'],
            headers: ['X-Authorization' => 'Bearer not-a-real-token'],
        );

        $response = $handler->handle($request);

        self::assertSame(401, $response->getStatusCode());
        $wwwAuth = $response->getHeaderLine('WWW-Authenticate');
        self::assertStringContainsString('error="invalid_token"', $wwwAuth);
        self::assertStringNotContainsString('error="missing_token"', $wwwAuth);
    }

    /**
     * Baseline / control: with NO credential in either header, the auth stage
     * reports `missing_token`. This is the response a mirror-only request would get
     * if the opt-in fallback were disabled.
     */
    public function test_no_credential_yields_missing_token(): void
    {
        [$handler, $creator] = $this->makeHandlerAndCreator();

        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => self::PROTECTED_PATH, 'HTTP_HOST' => 'localhost'],
        );

        $response = $handler->handle($request);

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString(
            'error="missing_token"',
            $response->getHeaderLine('WWW-Authenticate'),
        );
    }

    /**
     * The standard header still wins when both are present (byte-for-byte behaviour
     * unchanged on hosting that delivers `Authorization`): a valid standard token
     * authenticates even when an invalid mirror is also sent. If the receiver wrongly
     * preferred the mirror, the bearer stage would reject the invalid token with an
     * `invalid_token` challenge; its absence proves standard-header precedence.
     */
    public function test_standard_authorization_header_takes_precedence_over_mirror(): void
    {
        [$handler, $creator] = $this->makeHandlerAndCreator();
        $token = $this->issueSuperadminToken();

        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => self::PROTECTED_PATH, 'HTTP_HOST' => 'localhost'],
            headers: [
                'Authorization' => 'Bearer ' . $token,
                'X-Authorization' => 'Bearer not-a-real-token',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame('', $response->getHeaderLine('WWW-Authenticate'));
    }

    /** @return array{RequestHandlerInterface, ServerRequestCreator} */
    private function makeHandlerAndCreator(): array
    {
        $container = self::$container;
        assert($container !== null);

        $psr17 = $container->get(Psr17Factory::class);
        assert($psr17 instanceof Psr17Factory);
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $handler = $container->get(RequestHandlerInterface::class);
        assert($handler instanceof RequestHandlerInterface);

        return [$handler, $creator];
    }

    private function issueSuperadminToken(): string
    {
        $container = self::$container;
        assert($container !== null);

        $issuer = $container->get(TokenIssuerInterface::class);
        assert($issuer instanceof TokenIssuerInterface);

        return $issuer->issue([
            'sub'  => 9999,
            'role' => 'superadmin',
            'org'  => null,
            'iat'  => time(),
            'exp'  => time() + 3600,
        ]);
    }
}
