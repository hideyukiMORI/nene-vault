<?php

declare(strict_types=1);

namespace NeneVault\Tests\User;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * User create/update validation boundary tests: email format, password length,
 * role enum, self-delete guard, pagination, RBAC.
 */
final class UserValidationBoundaryTest extends ApiTestCase
{
    private static string $adminToken  = '';
    private static string $memberToken = '';
    private static int    $orgId       = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId       = self::ensureOrg('test-org');
        self::$adminToken  = self::issueToken('admin', self::$orgId, userId: 170);
        self::$memberToken = self::issueToken('member', self::$orgId, userId: 171);
    }

    // ── Email validation ───────────────────────────────────────────────────────

    public function test_create_invalid_email_returns_422(): void
    {
        $resp = $this->create(['email' => 'not-an-email', 'password' => 'changeme123', 'role' => 'member']);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_empty_email_returns_422(): void
    {
        $resp = $this->create(['email' => '', 'password' => 'changeme123', 'role' => 'member']);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_valid_email_accepted(): void
    {
        $resp = $this->create(['email' => 'valid-' . uniqid() . '@example.com', 'password' => 'changeme123', 'role' => 'member']);
        $this->assertSame(201, $resp->getStatusCode(), (string) $resp->getBody());
    }

    // ── Password validation ──────────────────────────────────────────────────

    public function test_create_password_7_chars_rejected_422(): void
    {
        $resp = $this->create(['email' => 'pw7-' . uniqid() . '@example.com', 'password' => '1234567', 'role' => 'member']);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_password_8_chars_accepted(): void
    {
        $resp = $this->create(['email' => 'pw8-' . uniqid() . '@example.com', 'password' => '12345678', 'role' => 'member']);
        $this->assertSame(201, $resp->getStatusCode());
    }

    public function test_create_empty_password_returns_422(): void
    {
        $resp = $this->create(['email' => 'nopw-' . uniqid() . '@example.com', 'password' => '', 'role' => 'member']);
        $this->assertSame(422, $resp->getStatusCode());
    }

    // ── Role validation ────────────────────────────────────────────────────────

    public function test_create_each_valid_role_accepted(): void
    {
        foreach (['admin', 'member', 'viewer'] as $role) {
            $resp = $this->create(['email' => "{$role}-" . uniqid() . '@example.com', 'password' => 'changeme123', 'role' => $role]);
            $this->assertSame(201, $resp->getStatusCode(), "Role '{$role}' must be accepted: " . (string) $resp->getBody());
        }
    }

    public function test_create_superadmin_role_rejected_422(): void
    {
        $resp = $this->create(['email' => 'super-' . uniqid() . '@example.com', 'password' => 'changeme123', 'role' => 'superadmin']);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_unknown_role_rejected_422(): void
    {
        $resp = $this->create(['email' => 'unknown-' . uniqid() . '@example.com', 'password' => 'changeme123', 'role' => 'wizard']);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_missing_role_returns_422(): void
    {
        $resp = $this->create(['email' => 'norole-' . uniqid() . '@example.com', 'password' => 'changeme123']);
        $this->assertSame(422, $resp->getStatusCode());
    }

    // ── Duplicate email ────────────────────────────────────────────────────────

    public function test_create_duplicate_email_returns_409(): void
    {
        $email = 'dup-' . uniqid() . '@example.com';
        $this->assertSame(201, $this->create(['email' => $email, 'password' => 'changeme123', 'role' => 'member'])->getStatusCode());
        $this->assertSame(409, $this->create(['email' => $email, 'password' => 'changeme123', 'role' => 'member'])->getStatusCode());
    }

    // ── Self-delete guard ──────────────────────────────────────────────────────

    public function test_admin_cannot_delete_self(): void
    {
        // Create an admin, then issue a token for that admin and have it delete itself.
        $email = 'selfadmin-' . uniqid() . '@example.com';
        $created = $this->create(['email' => $email, 'password' => 'changeme123', 'role' => 'admin']);
        $this->assertSame(201, $created->getStatusCode());
        $userId = json_decode((string) $created->getBody(), true)['id'];

        $selfToken = self::issueToken('admin', self::$orgId, userId: (int) $userId);
        $resp = $this->handler()->handle(
            $this->request('DELETE', "/admin/users/{$userId}", $selfToken),
        );
        $this->assertSame(409, $resp->getStatusCode(), 'Deleting self must be rejected with 409 Conflict');
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_update_role_to_viewer(): void
    {
        $email   = 'upd-' . uniqid() . '@example.com';
        $created = $this->create(['email' => $email, 'password' => 'changeme123', 'role' => 'member']);
        $userId  = json_decode((string) $created->getBody(), true)['id'];

        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/users/{$userId}", self::$adminToken, ['role' => 'viewer']),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('viewer', json_decode((string) $resp->getBody(), true)['role']);
    }

    public function test_update_unknown_user_returns_404(): void
    {
        $resp = $this->handler()->handle(
            $this->request('PATCH', '/admin/users/99999999', self::$adminToken, ['role' => 'viewer']),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    // ── Pagination ─────────────────────────────────────────────────────────────

    public function test_list_pagination_limit_and_total(): void
    {
        // Ensure at least 2 users exist
        $this->create(['email' => 'pag1-' . uniqid() . '@example.com', 'password' => 'changeme123', 'role' => 'member']);
        $this->create(['email' => 'pag2-' . uniqid() . '@example.com', 'password' => 'changeme123', 'role' => 'member']);

        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/users?limit=1&offset=0', self::$adminToken),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertCount(1, $body['items']);
        $this->assertGreaterThanOrEqual(2, $body['total']);
    }

    // ── RBAC ─────────────────────────────────────────────────────────────────

    public function test_member_cannot_create_user(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/users', self::$memberToken, ['email' => 'x-' . uniqid() . '@example.com', 'password' => 'changeme123', 'role' => 'member']),
        );
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_unauthenticated_returns_401(): void
    {
        $resp = $this->handler()->handle($this->request('GET', '/admin/users'));
        $this->assertSame(401, $resp->getStatusCode());
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $body */
    private function create(array $body): \Psr\Http\Message\ResponseInterface
    {
        return $this->handler()->handle(
            $this->request('POST', '/admin/users', self::$adminToken, $body),
        );
    }
}
