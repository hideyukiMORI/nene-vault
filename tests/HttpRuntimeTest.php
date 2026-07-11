<?php

declare(strict_types=1);

namespace NeneVault\Tests;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use NeneVault\Http\RuntimeContainerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpRuntimeTest extends TestCase
{
    private static ?ContainerInterface $container = null;

    public static function setUpBeforeClass(): void
    {
        self::$container = (new RuntimeContainerFactory(dirname(__DIR__)))->create();

        // Seed the default test organization (ORG_SLUG=test-org from phpunit.xml.dist)
        $conn = self::$container->get(DatabaseConnectionFactoryInterface::class);
        assert($conn instanceof DatabaseConnectionFactoryInterface);
        $pdo = $conn->create();
        $pdo->exec("INSERT OR IGNORE INTO organizations
            (name, slug, plan, is_active, created_at, updated_at)
            VALUES ('Test Org', 'test-org', 'free', 1, datetime('now'), datetime('now'))");
    }

    public function test_health_returns_200(): void
    {
        $container = self::$container;
        assert($container !== null);

        [$handler, $creator] = $this->makeHandlerAndCreator($container);
        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health'],
        );

        $response = $handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertSame('ok', $body['status']);
        // Framework-provided /health with the DatabaseHealthCheck wired (#163).
        $this->assertSame('ok', $body['checks']['database'] ?? null);
    }

    public function test_unknown_route_returns_404(): void
    {
        $container = self::$container;
        assert($container !== null);

        [$handler, $creator] = $this->makeHandlerAndCreator($container);
        // Blocklist auth (#157): unauthenticated unknown paths are 401 at the
        // middleware (pinned by PublicSurfaceBoundaryTest), so the router's
        // 404 for unknown routes is asserted behind a valid superadmin token.
        $issuer = $container->get(TokenIssuerInterface::class);
        assert($issuer instanceof TokenIssuerInterface);
        $token = $issuer->issue([
            'sub'  => 9999,
            'role' => 'superadmin',
            'org'  => null,
            'iat'  => time(),
            'exp'  => time() + 3600,
        ]);

        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/auth/no-such-endpoint'],
            headers: ['Authorization' => "Bearer {$token}"],
        );

        $response = $handler->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_admin_route_without_token_returns_401(): void
    {
        $container = self::$container;
        assert($container !== null);

        [$handler, $creator] = $this->makeHandlerAndCreator($container);
        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/vault/settings', 'HTTP_HOST' => 'localhost'],
        );

        $response = $handler->handle($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $container = self::$container;
        assert($container !== null);

        [$handler, $creator] = $this->makeHandlerAndCreator($container);
        $body = json_encode(['email' => 'nobody@example.com', 'password' => 'wrong']);
        assert($body !== false);

        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/auth/login', 'CONTENT_TYPE' => 'application/json'],
            body: $body,
        );

        $response = $handler->handle($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    /** @return array{RequestHandlerInterface, ServerRequestCreator} */
    private function makeHandlerAndCreator(ContainerInterface $container): array
    {
        $psr17 = $container->get(Psr17Factory::class);
        assert($psr17 instanceof Psr17Factory);
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $handler = $container->get(RequestHandlerInterface::class);
        assert($handler instanceof RequestHandlerInterface);

        return [$handler, $creator];
    }
}
