<?php

declare(strict_types=1);

namespace NeneVault\Tests\VaultSettings;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * VaultSettings boundary tests: retention_years range edges (7–99),
 * sibling-link URLs, storage path override, RBAC.
 */
final class VaultSettingsBoundaryTest extends ApiTestCase
{
    private static string $adminToken  = '';
    private static string $memberToken = '';
    private static string $viewerToken = '';
    private static int    $orgId       = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId       = self::ensureOrg('test-org');
        self::$adminToken  = self::issueToken('admin', self::$orgId, userId: 150);
        self::$memberToken = self::issueToken('member', self::$orgId, userId: 151);
        self::$viewerToken = self::issueToken('viewer', self::$orgId, userId: 152);
    }

    public static function tearDownAfterClass(): void
    {
        // This class mutates the shared test-org retention_years. Reset it to the
        // default (10) so it does not pollute other classes' retention calculations.
        $pdo = self::pdo();
        $pdo->exec('UPDATE vault_settings SET retention_years = 10 WHERE organization_id = ' . self::$orgId);

        parent::tearDownAfterClass();
    }

    // ── retention_years range edges ───────────────────────────────────────────

    public function test_retention_years_minimum_7_accepted(): void
    {
        $resp = $this->patch(['retention_years' => 7]);
        $this->assertSame(200, $resp->getStatusCode(), (string) $resp->getBody());
        $this->assertSame(7, json_decode((string) $resp->getBody(), true)['retention_years']);
    }

    public function test_retention_years_6_rejected_422(): void
    {
        $resp = $this->patch(['retention_years' => 6]);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_retention_years_0_rejected_422(): void
    {
        $resp = $this->patch(['retention_years' => 0]);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_retention_years_maximum_99_accepted(): void
    {
        $resp = $this->patch(['retention_years' => 99]);
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame(99, json_decode((string) $resp->getBody(), true)['retention_years']);
    }

    public function test_retention_years_100_rejected_422(): void
    {
        $resp = $this->patch(['retention_years' => 100]);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_retention_years_10_default_accepted(): void
    {
        $resp = $this->patch(['retention_years' => 10]);
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame(10, json_decode((string) $resp->getBody(), true)['retention_years']);
    }

    // ── sibling links + storage path ──────────────────────────────────────────

    public function test_storage_path_override_set_and_cleared(): void
    {
        $set = $this->patch(['retention_years' => 10, 'storage_path_override' => '/var/custom/vault']);
        $this->assertSame('/var/custom/vault', json_decode((string) $set->getBody(), true)['storage_path_override']);

        $clear = $this->patch(['retention_years' => 10, 'storage_path_override' => '']);
        $this->assertNull(json_decode((string) $clear->getBody(), true)['storage_path_override']);
    }

    public function test_invoice_and_clear_urls_persisted(): void
    {
        $resp = $this->patch([
            'retention_years'      => 10,
            'invoice_api_base_url' => 'https://invoice.example.com',
            'clear_api_base_url'   => 'https://clear.example.com',
        ]);
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertSame('https://invoice.example.com', $body['invoice_api_base_url']);
        $this->assertSame('https://clear.example.com', $body['clear_api_base_url']);
    }

    public function test_update_persists_to_next_get(): void
    {
        $this->patch(['retention_years' => 15]);
        $get  = $this->handler()->handle($this->request('GET', '/admin/vault/settings', self::$adminToken));
        $this->assertSame(15, json_decode((string) $get->getBody(), true)['retention_years']);
    }

    public function test_update_records_audit_event(): void
    {
        $this->patch(['retention_years' => 12]);
        $audit = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?entity_type=vault_settings&action=vault_settings.changed', self::$adminToken),
        );
        $body = json_decode((string) $audit->getBody(), true);
        $this->assertGreaterThanOrEqual(1, $body['total']);
    }

    // ── RBAC ─────────────────────────────────────────────────────────────────

    public function test_member_cannot_update_settings(): void
    {
        $resp = $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/settings', self::$memberToken, ['retention_years' => 10]),
        );
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_viewer_cannot_read_settings(): void
    {
        // Both GET and PATCH on /admin/vault/settings require ManageVaultSettings
        // (admin/superadmin only) — settings are an admin-only surface.
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/vault/settings', self::$viewerToken),
        );
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_unauthenticated_get_returns_401(): void
    {
        $resp = $this->handler()->handle($this->request('GET', '/admin/vault/settings'));
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function test_unauthenticated_patch_returns_401(): void
    {
        $resp = $this->handler()->handle($this->request('PATCH', '/admin/vault/settings', null, ['retention_years' => 10]));
        $this->assertSame(401, $resp->getStatusCode());
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $body */
    private function patch(array $body): \Psr\Http\Message\ResponseInterface
    {
        return $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/settings', self::$adminToken, $body),
        );
    }
}
