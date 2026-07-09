<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Demo\DemoConfig;
use NeneVault\Auth\User;
use NeneVault\Auth\UserRepositoryInterface;
use NeneVault\Demo\SeatFixedDemoHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class SeatFixedDemoHandlerTest extends TestCase
{
    private function handler(bool $demoMode, ?User $user): SeatFixedDemoHandler
    {
        $users = new class ($user) implements UserRepositoryInterface {
            public function __construct(private readonly ?User $user)
            {
            }

            public function findById(int $id): ?User
            {
                return $this->user;
            }

            public function findByEmail(string $email): ?User
            {
                return $this->user;
            }

            public function listByOrganizationId(int $organizationId, int $limit, int $offset): array
            {
                return [];
            }

            public function countByOrganizationId(int $organizationId): int
            {
                return 0;
            }

            public function create(string $email, string $passwordHash, string $role, ?int $organizationId): User
            {
                throw new \LogicException('unused');
            }

            public function updatePassword(int $id, string $passwordHash): void
            {
            }

            public function updateStatus(int $id, string $status): void
            {
            }

            public function updateRole(int $id, string $role): void
            {
            }

            public function updateEmail(int $id, string $email): void
            {
            }

            public function storeInviteToken(int $id, string $tokenHash, int $expiresAt): void
            {
            }

            public function findByInviteToken(string $tokenHash): ?User
            {
                return null;
            }

            public function clearInviteToken(int $id): void
            {
            }

            public function storePasswordResetToken(int $id, string $tokenHash, int $expiresAt): void
            {
            }

            public function findByPasswordResetToken(string $tokenHash): ?User
            {
                return null;
            }

            public function clearPasswordResetToken(int $id): void
            {
            }

            public function delete(int $id): void
            {
            }
        };

        return new SeatFixedDemoHandler(
            new DemoConfig(demoMode: $demoMode),
            $users,
            new LocalBearerTokenVerifier('seat-test-secret'),
            new Psr17Factory(),
            new \Nene2\Http\UtcClock(),
        );
    }

    private function request(): \Psr\Http\Message\ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/demo/standard');
    }

    private function demoAdmin(): User
    {
        return new User(
            id: 7,
            email: 'demo-admin@nene-vault.dev',
            passwordHash: 'x',
            role: 'admin',
            organizationId: 2,
        );
    }

    public function test_fail_close_when_demo_mode_is_off(): void
    {
        $response = $this->handler(false, $this->demoAdmin())->handle($this->request());

        self::assertSame(404, $response->getStatusCode());
    }

    public function test_fail_close_when_the_demo_admin_is_absent(): void
    {
        self::assertSame(404, $this->handler(true, null)->handle($this->request())->getStatusCode());
    }

    public function test_fail_close_for_a_non_admin_or_orgless_account(): void
    {
        $viewer = new User(id: 8, email: 'demo-admin@nene-vault.dev', passwordHash: 'x', role: 'viewer', organizationId: 2);
        self::assertSame(404, $this->handler(true, $viewer)->handle($this->request())->getStatusCode());

        $orgless = new User(id: 9, email: 'demo-admin@nene-vault.dev', passwordHash: 'x', role: 'admin', organizationId: null);
        self::assertSame(404, $this->handler(true, $orgless)->handle($this->request())->getStatusCode());
    }

    public function test_seat_page_stores_the_auth_session_and_lands_in_the_spa(): void
    {
        $response = $this->handler(true, $this->demoAdmin())->handle($this->request());

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString("localStorage.setItem('nene_vault_token', JSON.stringify(", $html);
        self::assertStringContainsString("location.replace('/')", $html);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));

        // The embedded session carries the SPA's AuthSession shape and a
        // verifiable 24 h token with LoginUseCase-identical claims.
        self::assertSame(1, preg_match('/JSON\.stringify\((\{.*?\})\)/s', $html, $m));
        $session = json_decode($m[1] ?? '', true);
        self::assertIsArray($session);
        self::assertSame(7, $session['userId']);
        self::assertSame('demo-admin@nene-vault.dev', $session['email']);
        self::assertSame('admin', $session['role']);
        self::assertSame(2, $session['orgId']);

        $claims = (new LocalBearerTokenVerifier('seat-test-secret'))->verify((string) $session['token']);
        self::assertSame(7, $claims['user_id']);
        self::assertSame(2, $claims['org_id']);
        self::assertSame('admin', $claims['role']);
        self::assertEqualsWithDelta(86400, (int) $claims['exp'] - (int) $claims['iat'], 5);
    }

    public function test_page_specific_csp_matches_the_script_nonce(): void
    {
        $response = $this->handler(true, $this->demoAdmin())->handle($this->request());

        $csp = $response->getHeaderLine('Content-Security-Policy');
        self::assertStringContainsString("default-src 'none'", $csp);
        self::assertSame(1, preg_match("/script-src 'nonce-([^']+)'/", $csp, $m));
        $nonce = $m[1] ?? '';
        self::assertNotSame('', $nonce);
        self::assertStringContainsString('<script nonce="' . $nonce . '">', (string) $response->getBody());
    }
}
