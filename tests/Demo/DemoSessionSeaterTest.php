<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Demo\DemoConfig;
use Nene2\Demo\ProvisionedDemoOrg;
use NeneVault\Demo\DemoProvisionRegistry;
use NeneVault\Demo\DemoSessionSeater;
use NeneVault\Tests\Support\FixedClock;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * The disposable-org seat page (#141): mints an admin token whose `org_id`
 * claim drives the claim-based tenant resolution, parks the SPA's
 * `AuthSession` JSON in sessionStorage under the exact key/shape a login
 * persists, and locks the page down with a nonce'd per-response CSP.
 */
final class DemoSessionSeaterTest extends TestCase
{
    private function seat(?DemoProvisionRegistry $registry = null): ResponseInterface
    {
        $registry ??= new DemoProvisionRegistry();

        $seater = new DemoSessionSeater(
            new DemoConfig(demoMode: true, ttlHours: 3),
            $registry,
            new LocalBearerTokenVerifier('seat-test-secret'),
            new Psr17Factory(),
            // Real "now": the verifier checks exp against wall-clock time.
            new FixedClock(gmdate('c')),
        );

        return $seater->seatAndRedirect(
            (new Psr17Factory())->createServerRequest('GET', '/demo/standard'),
            new ProvisionedDemoOrg(orgId: 42, slug: 'demo-abc123', adminUserId: 77),
        );
    }

    public function test_seat_page_stores_an_admin_auth_session_for_the_disposable_org(): void
    {
        $registry = new DemoProvisionRegistry();
        $registry->register(42, 77, 'demo-admin@demo-abc123.nene-vault.dev');

        $response = $this->seat($registry);

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString("sessionStorage.setItem('nene_vault_token', JSON.stringify(", $html);
        self::assertStringContainsString("location.replace('/')", $html);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));

        self::assertSame(1, preg_match('/JSON\.stringify\((\{.*?\})\)/s', $html, $m));
        $session = json_decode($m[1] ?? '', true);
        self::assertIsArray($session);
        self::assertSame(77, $session['userId']);
        self::assertSame('demo-admin@demo-abc123.nene-vault.dev', $session['email']);
        self::assertSame('admin', $session['role']);
        self::assertSame(42, $session['orgId']);

        $claims = (new LocalBearerTokenVerifier('seat-test-secret'))->verify((string) $session['token']);
        self::assertSame(77, $claims['sub']);
        self::assertSame(42, $claims['org']);
        self::assertSame('admin', $claims['role']);
        // Token TTL matches the demo org TTL (3 h), not the 1 h login TTL — the
        // disposable org lives 3 h and the seat must survive the whole session.
        self::assertSame(3 * 3600, (int) $claims['exp'] - (int) $claims['iat']);
    }

    public function test_falls_back_to_the_deterministic_admin_email_without_a_registry_entry(): void
    {
        $html = (string) $this->seat()->getBody();

        self::assertSame(1, preg_match('/JSON\.stringify\((\{.*?\})\)/s', $html, $m));
        $session = json_decode($m[1] ?? '', true);
        self::assertIsArray($session);
        self::assertSame('demo-admin@demo-abc123.nene-vault.dev', $session['email']);
    }

    public function test_page_specific_csp_matches_the_script_nonce(): void
    {
        $response = $this->seat();

        $csp = $response->getHeaderLine('Content-Security-Policy');
        self::assertStringContainsString("default-src 'none'", $csp);
        self::assertSame(1, preg_match("/script-src 'nonce-([^']+)'/", $csp, $m));
        $nonce = $m[1] ?? '';
        self::assertNotSame('', $nonce);
        self::assertStringContainsString('<script nonce="' . $nonce . '">', (string) $response->getBody());
    }
}
