<?php

declare(strict_types=1);

namespace NeneVault\Tests\User;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use NeneVault\Http\RuntimeContainerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class UserApiTest extends TestCase
{
    private static ?ContainerInterface $container = null;
    private static int $orgId = 0;
    private static string $token = '';

    public static function setUpBeforeClass(): void
    {
        self::$container = (new RuntimeContainerFactory(dirname(__DIR__, 2)))->create();

        $conn = self::$container->get(DatabaseConnectionFactoryInterface::class);
        assert($conn instanceof DatabaseConnectionFactoryInterface);
        $pdo = $conn->create();

        $pdo->exec("INSERT OR IGNORE INTO organizations
            (name, slug, plan, is_active, created_at, updated_at)
            VALUES ('Test Org', 'test-org', 'free', 1, datetime('now'), datetime('now'))");
        $stmt = $pdo->query("SELECT id FROM organizations WHERE slug = 'test-org'");
        assert($stmt !== false);
        self::$orgId = (int) $stmt->fetch()['id'];

        $issuer = self::$container->get(TokenIssuerInterface::class);
        assert($issuer instanceof TokenIssuerInterface);
        self::$token = $issuer->issue([
            'sub' => 'admin@example.com',
            'user_id' => 1000,
            'role' => 'admin',
            'org_id' => self::$orgId,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);
    }

    public function test_create_list_update_delete_user(): void
    {
        $handler = $this->handler();
        $email = 'member-' . uniqid() . '@example.com';

        // Create
        $create = $handler->handle($this->request('POST', '/admin/users', json: [
            'email' => $email,
            'password' => 'changeme123',
            'role' => 'member',
        ]));
        $this->assertSame(201, $create->getStatusCode(), (string) $create->getBody());
        $created = json_decode((string) $create->getBody(), true);
        $this->assertSame($email, $created['email']);
        $this->assertSame('member', $created['role']);
        $this->assertSame(self::$orgId, $created['organization_id']);
        $this->assertArrayNotHasKey('password_hash', $created);
        $userId = $created['id'];

        // List — created user present (use high limit to avoid pagination hiding the user)
        $list = $handler->handle($this->request('GET', '/admin/users?limit=100'));
        $this->assertSame(200, $list->getStatusCode());
        $listBody = json_decode((string) $list->getBody(), true);
        $this->assertContains($userId, array_column($listBody['items'], 'id'));

        // Update — change role to viewer
        $update = $handler->handle($this->request('PATCH', '/admin/users/' . $userId, json: ['role' => 'viewer']));
        $this->assertSame(200, $update->getStatusCode(), (string) $update->getBody());
        $this->assertSame('viewer', json_decode((string) $update->getBody(), true)['role']);

        // Delete
        $delete = $handler->handle($this->request('DELETE', '/admin/users/' . $userId));
        $this->assertSame(204, $delete->getStatusCode());

        // Get after delete → 404
        $get = $handler->handle($this->request('GET', '/admin/users/' . $userId));
        $this->assertSame(404, $get->getStatusCode());
    }

    public function test_create_rejects_superadmin_role(): void
    {
        $response = $this->handler()->handle($this->request('POST', '/admin/users', json: [
            'email' => 'super-' . uniqid() . '@example.com',
            'password' => 'changeme123',
            'role' => 'superadmin',
        ]));

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_create_rejects_duplicate_email(): void
    {
        $handler = $this->handler();
        $email = 'dup-' . uniqid() . '@example.com';
        $payload = ['email' => $email, 'password' => 'changeme123', 'role' => 'member'];

        $handler->handle($this->request('POST', '/admin/users', json: $payload));
        $second = $handler->handle($this->request('POST', '/admin/users', json: $payload));

        $this->assertSame(409, $second->getStatusCode());
    }

    public function test_cannot_delete_self(): void
    {
        $handler = $this->handler();
        $container = self::$container;
        assert($container !== null);
        $conn = $container->get(DatabaseConnectionFactoryInterface::class);
        assert($conn instanceof DatabaseConnectionFactoryInterface);
        $pdo = $conn->create();
        $pdo->exec("INSERT OR IGNORE INTO users (id, email, password_hash, role, organization_id, status, created_at, updated_at)
            VALUES (1000, 'self@example.com', 'x', 'admin', " . self::$orgId . ", 'active', datetime('now'), datetime('now'))");

        // JWT user_id is 1000 — deleting id 1000 is self
        $response = $handler->handle($this->request('DELETE', '/admin/users/1000'));

        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->handler()->handle($this->request('GET', '/admin/users', auth: false));
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── helpers ──

    private function handler(): RequestHandlerInterface
    {
        $container = self::$container;
        assert($container !== null);
        $h = $container->get(RequestHandlerInterface::class);
        assert($h instanceof RequestHandlerInterface);

        return $h;
    }

    /** @param array<string, mixed>|null $json */
    private function request(string $method, string $uri, bool $auth = true, ?array $json = null): ServerRequestInterface
    {
        $container = self::$container;
        assert($container !== null);
        $psr17 = $container->get(Psr17Factory::class);
        assert($psr17 instanceof Psr17Factory);

        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);

        $headers = ['Host' => 'localhost'];
        if ($auth) {
            $headers['Authorization'] = 'Bearer ' . self::$token;
        }

        $queryParams = [];
        $queryString = parse_url($uri, PHP_URL_QUERY);
        if (is_string($queryString)) {
            parse_str($queryString, $queryParams);
        }

        $body = null;
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
            $body = $psr17->createStream(json_encode($json, JSON_THROW_ON_ERROR));
        }

        return $creator->fromArrays(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            headers: $headers,
            get: $queryParams,
            body: $body,
        );
    }
}
