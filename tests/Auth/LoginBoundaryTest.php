<?php

declare(strict_types=1);

namespace NeneVault\Tests\Auth;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * HTTP-level login boundary tests: missing fields, wrong password,
 * unknown email, successful token issuance.
 */
final class LoginBoundaryTest extends ApiTestCase
{
    private static int    $orgId = 0;
    private static string $email = '';
    private const PASSWORD = 'correct-horse-battery';

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId = self::ensureOrg('test-org');
        self::$email = 'login-bnd-' . uniqid() . '@example.com';

        // Insert a user with a known bcrypt hash.
        $hash = password_hash(self::PASSWORD, PASSWORD_BCRYPT);
        $pdo  = self::pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
             VALUES (?, ?, 'admin', ?, 'active', datetime('now'), datetime('now'))",
        );
        $stmt->execute([self::$email, $hash, self::$orgId]);
    }

    // ── Validation ─────────────────────────────────────────────────────────────

    public function test_login_missing_email_returns_422(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['password' => self::PASSWORD]),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_login_missing_password_returns_422(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => self::$email]),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_login_empty_body_returns_400(): void
    {
        // An empty JSON array is a malformed body (not an object) → 400 at parse time.
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, []),
        );
        $this->assertSame(400, $resp->getStatusCode());
    }

    // ── Credentials ────────────────────────────────────────────────────────────

    public function test_login_wrong_password_returns_401(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => self::$email, 'password' => 'wrong-password']),
        );
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function test_login_unknown_email_returns_401(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => 'nobody-' . uniqid() . '@example.com', 'password' => self::PASSWORD]),
        );
        $this->assertSame(401, $resp->getStatusCode());
    }

    // ── Success ────────────────────────────────────────────────────────────────

    public function test_login_success_returns_token(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => self::$email, 'password' => self::PASSWORD]),
        );
        $this->assertSame(200, $resp->getStatusCode(), (string) $resp->getBody());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertArrayHasKey('token', $body);
        $this->assertNotEmpty($body['token']);
        $this->assertSame(self::$email, $body['email']);
        $this->assertSame('admin', $body['role']);
        $this->assertSame(self::$orgId, $body['org_id']);
        $this->assertArrayHasKey('expires_at', $body);
    }

    public function test_login_response_excludes_password_hash(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => self::$email, 'password' => self::PASSWORD]),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertArrayNotHasKey('password_hash', $body);
        $this->assertArrayNotHasKey('password', $body);
    }

    // ── Throttle (#148) ────────────────────────────────────────────────────────

    public function test_login_locks_after_five_failures_and_returns_429(): void
    {
        // Dedicated identifier so the lock cannot leak into other tests
        // (the throttle keys on email + client IP).
        $email = 'throttled-' . uniqid() . '@example.com';
        $hash  = password_hash(self::PASSWORD, PASSWORD_BCRYPT);
        $stmt  = self::pdo()->prepare(
            "INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
             VALUES (?, ?, 'admin', ?, 'active', datetime('now'), datetime('now'))",
        );
        $stmt->execute([$email, $hash, self::$orgId]);

        for ($i = 0; $i < 5; $i++) {
            $resp = $this->handler()->handle(
                $this->request('POST', '/admin/auth/login', null, ['email' => $email, 'password' => 'wrong-password']),
            );
            $this->assertSame(401, $resp->getStatusCode(), 'attempt ' . ($i + 1) . ' must still be 401');
        }

        // 6th attempt — even with the CORRECT password — is locked out.
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => $email, 'password' => self::PASSWORD]),
        );
        $this->assertSame(429, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertArrayHasKey('retry_after_seconds', $body);
        $this->assertGreaterThan(0, $body['retry_after_seconds']);
    }

    public function test_successful_login_clears_failure_counter(): void
    {
        $email = 'clears-' . uniqid() . '@example.com';
        $hash  = password_hash(self::PASSWORD, PASSWORD_BCRYPT);
        $stmt  = self::pdo()->prepare(
            "INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
             VALUES (?, ?, 'admin', ?, 'active', datetime('now'), datetime('now'))",
        );
        $stmt->execute([$email, $hash, self::$orgId]);

        for ($i = 0; $i < 4; $i++) {
            $this->handler()->handle(
                $this->request('POST', '/admin/auth/login', null, ['email' => $email, 'password' => 'wrong-password']),
            );
        }

        $ok = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => $email, 'password' => self::PASSWORD]),
        );
        $this->assertSame(200, $ok->getStatusCode());

        // Counter reset: four more failures still do not lock.
        for ($i = 0; $i < 4; $i++) {
            $resp = $this->handler()->handle(
                $this->request('POST', '/admin/auth/login', null, ['email' => $email, 'password' => 'wrong-password']),
            );
            $this->assertSame(401, $resp->getStatusCode());
        }
    }

    public function test_login_rejects_non_active_user(): void
    {
        $email = 'invited-' . uniqid() . '@example.com';
        $hash  = password_hash(self::PASSWORD, PASSWORD_BCRYPT);
        $stmt  = self::pdo()->prepare(
            "INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
             VALUES (?, ?, 'member', ?, 'invited', datetime('now'), datetime('now'))",
        );
        $stmt->execute([$email, $hash, self::$orgId]);

        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => $email, 'password' => self::PASSWORD]),
        );
        $this->assertSame(401, $resp->getStatusCode(), 'A non-active user must not authenticate even with the right password (#150)');
    }

    public function test_issued_token_is_accepted_by_protected_route(): void
    {
        $login = $this->handler()->handle(
            $this->request('POST', '/admin/auth/login', null, ['email' => self::$email, 'password' => self::PASSWORD]),
        );
        $token = json_decode((string) $login->getBody(), true)['token'];

        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents', $token),
        );
        $this->assertSame(200, $resp->getStatusCode(), 'Token from login must authenticate against a protected route');
    }
}
