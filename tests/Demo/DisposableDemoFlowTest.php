<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * End-to-end coverage of the disposable-org demo (#141) through the REAL
 * request pipeline (routing → auth → claim-based org resolution → capability):
 * `GET /demo/standard` provisions a fresh org, seeds it, and seats the visitor
 * as its admin; the minted token's `org_id` claim then scopes every API call
 * to the disposable org even though the env strategy points at `test-org`.
 */
final class DisposableDemoFlowTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::ensureOrg('test-org');
    }

    /** @return array{token: string, orgId: int, userId: int, email: string, role: string} */
    private function startDemo(): array
    {
        $response = $this->handler()->handle($this->request('GET', '/demo/standard'));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $html = (string) $response->getBody();
        $this->assertStringContainsString("localStorage.setItem('nene_vault_token', JSON.stringify(", $html);
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));

        $this->assertSame(1, preg_match('/JSON\.stringify\((\{.*?\})\)/s', $html, $m));
        $session = json_decode($m[1] ?? '', true);
        $this->assertIsArray($session);

        return [
            'token' => (string) $session['token'],
            'orgId' => (int) $session['orgId'],
            'userId' => (int) $session['userId'],
            'email' => (string) $session['email'],
            'role' => (string) $session['role'],
        ];
    }

    public function test_start_provisions_seeds_and_seats_an_admin(): void
    {
        $seat = $this->startDemo();

        $this->assertSame('admin', $seat['role']);
        $this->assertStringStartsWith('demo-admin@demo-', $seat['email']);

        // The disposable org exists with the demo slug prefix.
        $stmt = self::pdo()->query("SELECT slug FROM organizations WHERE id = {$seat['orgId']}");
        $this->assertNotFalse($stmt);
        $slug = (string) $stmt->fetch()['slug'];
        $this->assertStringStartsWith('demo-', $slug);

        // The minted token lands in the disposable org through the real
        // pipeline: 20 seeded documents (19 active + 1 left voided).
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents?include_voided=true&limit=50', $seat['token']),
        );
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(20, $body['items']);
    }

    public function test_revisiting_the_link_provisions_a_brand_new_org(): void
    {
        $first = $this->startDemo();
        $second = $this->startDemo();

        $this->assertNotSame($first['orgId'], $second['orgId']);
        $this->assertNotSame($first['email'], $second['email']);
    }

    public function test_demo_org_is_isolated_from_the_fixed_org(): void
    {
        $fixedOrgId = self::ensureOrg('test-org');
        $fixedAdmin = self::issueToken('admin', $fixedOrgId, userId: 900);
        $marker = 'FixedOrgDoc-' . uniqid();
        $this->uploadDoc($this->handler(), $fixedAdmin, $marker);

        $seat = $this->startDemo();
        $response = $this->handler()->handle(
            $this->request('GET', "/admin/vault/documents?counterparty_name={$marker}", $seat['token']),
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(0, $body['items'], 'the demo seat must never see fixed-org documents');
    }

    public function test_the_showcase_write_path_works_upload_lands_with_sha256_and_audit(): void
    {
        $seat = $this->startDemo();

        $marker = 'DemoUpload-' . uniqid();
        $documentId = $this->uploadDoc($this->handler(), $seat['token'], $marker);

        $detail = json_decode(
            (string) $this->handler()->handle(
                $this->request('GET', "/admin/vault/documents/{$documentId}", $seat['token']),
            )->getBody(),
            true,
        );
        $this->assertIsArray($detail);
        $this->assertSame($marker, $detail['counterparty_name']);

        // The upload's audit event lives inside the disposable org.
        $audit = json_decode(
            (string) $this->handler()->handle(
                $this->request('GET', '/admin/audit-events?limit=50', $seat['token']),
            )->getBody(),
            true,
        );
        $this->assertIsArray($audit);
        $actions = array_column($audit['items'], 'action');
        $this->assertContains('document.uploaded', $actions);
    }

    public function test_demo_guided_reaches_the_fixed_seat_handler_not_the_template_parser(): void
    {
        // Static routes outrank parameterized ones: /demo/guided must land on
        // SeatFixedDemoHandler (fail-close 404 here — no demo viewer is seeded
        // in the test DB — with the handler's bare problem body), never on
        // /demo/{template} (whose 404 carries an "Unknown demo template" detail).
        $response = $this->handler()->handle($this->request('GET', '/demo/guided'));

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayNotHasKey('detail', $body);
    }

    public function test_unknown_template_is_404(): void
    {
        $response = $this->handler()->handle($this->request('GET', '/demo/nonexistent'));

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertStringContainsString('Unknown demo template', (string) ($body['detail'] ?? ''));
    }
}
