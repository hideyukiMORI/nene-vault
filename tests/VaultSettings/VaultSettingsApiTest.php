<?php

declare(strict_types=1);

namespace NeneVault\Tests\VaultSettings;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * HTTP-level tests for GET / PATCH /admin/vault/settings.
 *
 * Boundaries:
 *   - admin can read and update settings for their org
 *   - update is reflected on next GET
 *   - an audit event is recorded on update
 *   - validation: retention_years < 7 → 422
 *   - unauthenticated → 401
 */
final class VaultSettingsApiTest extends ApiTestCase
{
    private static string $token = '';
    private static int    $orgId = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        // Must use the env-resolved org (ORG_SLUG=test-org) so CapabilityMiddleware passes.
        self::$orgId = self::ensureOrg('test-org');
        self::$token = self::issueToken('admin', self::$orgId, userId: 10);
    }

    // ── happy path ───────────────────────────────────────────────────────────

    public function test_get_vault_settings_returns_defaults(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/vault/settings', self::$token),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('retention_years', $body);
        $this->assertSame(self::$orgId, $body['organization_id']);
        $this->assertGreaterThanOrEqual(7, $body['retention_years']);
    }

    public function test_update_retention_years(): void
    {
        $response = $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/settings', self::$token, [
                'retention_years' => 12,
            ]),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(12, json_decode((string) $response->getBody(), true)['retention_years']);
    }

    public function test_update_is_persisted(): void
    {
        $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/settings', self::$token, ['retention_years' => 15]),
        );

        $get = $this->handler()->handle(
            $this->request('GET', '/admin/vault/settings', self::$token),
        );
        $this->assertSame(15, json_decode((string) $get->getBody(), true)['retention_years']);
    }

    public function test_update_records_audit_event(): void
    {
        $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/settings', self::$token, ['retention_years' => 10]),
        );

        $pdo  = self::pdo();
        $stmt = $pdo->query(
            "SELECT * FROM audit_events
             WHERE entity_type = 'vault_settings' AND organization_id = " . self::$orgId . '
             ORDER BY id DESC LIMIT 1',
        );
        assert($stmt !== false);
        $event = $stmt->fetch();
        $this->assertNotFalse($event, 'An audit event must be recorded on settings update');
        $this->assertSame('vault_settings.changed', $event['action']);
    }

    public function test_update_storage_path_override(): void
    {
        $response = $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/settings', self::$token, [
                'storage_path_override' => '/tmp/vault-test',
            ]),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(
            '/tmp/vault-test',
            json_decode((string) $response->getBody(), true)['storage_path_override'],
        );
    }

    public function test_update_clears_storage_path_override(): void
    {
        $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/settings', self::$token, [
                'storage_path_override' => null,
            ]),
        );

        $get = $this->handler()->handle(
            $this->request('GET', '/admin/vault/settings', self::$token),
        );
        $this->assertNull(json_decode((string) $get->getBody(), true)['storage_path_override']);
    }

    // ── validation boundary ──────────────────────────────────────────────────

    public function test_retention_years_below_minimum_returns_422(): void
    {
        $response = $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/settings', self::$token, [
                'retention_years' => 3,
            ]),
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    // ── auth boundary ────────────────────────────────────────────────────────

    public function test_unauthenticated_get_returns_401(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/vault/settings'),
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_unauthenticated_patch_returns_401(): void
    {
        $response = $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/settings', null, ['retention_years' => 10]),
        );

        $this->assertSame(401, $response->getStatusCode());
    }
}
