<?php

declare(strict_types=1);

namespace NeneVault\Tests\User;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * Additional user API boundary tests.
 *
 * - GET /admin/users/{id}  (getUserById)
 * - GET /admin/users/{id} → 404 for unknown id
 * - Non-admin (member) gets 403 on all user management endpoints
 * - Pagination: limit / offset
 * - Auth: unauthenticated → 401
 */
final class UserBoundaryTest extends ApiTestCase
{
    private static string $adminToken  = '';
    private static string $memberToken = '';
    private static int    $orgId       = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId       = self::ensureOrg('test-org');
        self::$adminToken  = self::issueToken('admin', self::$orgId, userId: 40);
        self::$memberToken = self::issueToken('member', self::$orgId, userId: 41);
    }

    // ── GetUserById ──────────────────────────────────────────────────────────

    public function test_get_user_by_id(): void
    {
        $email  = 'getbyid-' . uniqid() . '@example.com';
        $create = $this->handler()->handle(
            $this->request('POST', '/admin/users', self::$adminToken, [
                'email'    => $email,
                'password' => 'changeme123',
                'role'     => 'member',
            ]),
        );
        $this->assertSame(201, $create->getStatusCode(), (string) $create->getBody());
        $userId = json_decode((string) $create->getBody(), true)['id'];

        $response = $this->handler()->handle(
            $this->request('GET', "/admin/users/{$userId}", self::$adminToken),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame($userId, $body['id']);
        $this->assertSame($email, $body['email']);
        $this->assertArrayNotHasKey('password_hash', $body);
    }

    public function test_get_nonexistent_user_returns_404(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/users/999999', self::$adminToken),
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    // ── list pagination ──────────────────────────────────────────────────────

    public function test_list_pagination(): void
    {
        foreach (range(1, 2) as $i) {
            $this->handler()->handle(
                $this->request('POST', '/admin/users', self::$adminToken, [
                    'email'    => "pager{$i}-" . uniqid() . '@example.com',
                    'password' => 'changeme123',
                    'role'     => 'viewer',
                ]),
            );
        }

        $page = $this->handler()->handle(
            $this->request('GET', '/admin/users?limit=1&offset=0', self::$adminToken),
        );
        $this->assertSame(200, $page->getStatusCode());
        $body = json_decode((string) $page->getBody(), true);
        $this->assertCount(1, $body['items']);
        $this->assertSame(1, $body['limit']);
    }

    // ── role boundary: non-admin must be refused ─────────────────────────────

    public function test_member_cannot_list_users(): void
    {
        $this->assertSame(403, $this->handler()->handle(
            $this->request('GET', '/admin/users', self::$memberToken),
        )->getStatusCode());
    }

    public function test_member_cannot_create_user(): void
    {
        $this->assertSame(403, $this->handler()->handle(
            $this->request('POST', '/admin/users', self::$memberToken, [
                'email'    => 'hacked-' . uniqid() . '@example.com',
                'password' => 'hacked123',
                'role'     => 'member',
            ]),
        )->getStatusCode());
    }

    public function test_member_cannot_delete_user(): void
    {
        $create = $this->handler()->handle(
            $this->request('POST', '/admin/users', self::$adminToken, [
                'email'    => 'del-bnd-' . uniqid() . '@example.com',
                'password' => 'changeme123',
                'role'     => 'viewer',
            ]),
        );
        $userId = json_decode((string) $create->getBody(), true)['id'];

        $this->assertSame(403, $this->handler()->handle(
            $this->request('DELETE', "/admin/users/{$userId}", self::$memberToken),
        )->getStatusCode());
    }

    // ── auth boundary ────────────────────────────────────────────────────────

    public function test_unauthenticated_get_by_id_returns_401(): void
    {
        $this->assertSame(401, $this->handler()->handle(
            $this->request('GET', '/admin/users/1'),
        )->getStatusCode());
    }
}
