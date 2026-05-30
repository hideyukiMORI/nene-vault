<?php

declare(strict_types=1);

namespace NeneVault\Tests\Organization;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * HTTP-level tests for the Organization CRUD API.
 *
 * Boundaries verified:
 *   - superadmin can list / get / create / update / delete
 *   - admin (org-scoped) is refused on every org management endpoint (403)
 *   - unauthenticated requests are refused (401)
 *   - duplicate slug → 409
 *   - unknown id → 404
 */
final class OrganizationApiTest extends ApiTestCase
{
    private static string $superToken = '';
    private static string $adminToken = '';

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();

        $orgId = self::ensureOrg('test-org');

        self::$superToken = self::issueSuperadminToken();
        self::$adminToken = self::issueToken('admin', $orgId, userId: 2);
    }

    // ── superadmin CRUD ──────────────────────────────────────────────────────

    public function test_superadmin_can_list_organizations(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/organizations', self::$superToken),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('items', $body);
        $this->assertArrayHasKey('total', $body);
    }

    public function test_superadmin_can_create_organization(): void
    {
        $slug = 'new-org-' . uniqid();
        $response = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, [
                'name' => 'New Org',
                'slug' => $slug,
            ]),
        );

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame($slug, $body['slug']);
        $this->assertSame('New Org', $body['name']);
    }

    public function test_create_organization_seeds_vault_settings(): void
    {
        $slug = 'seeded-' . uniqid();
        $create = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, [
                'name' => 'Seeded Org',
                'slug' => $slug,
            ]),
        );
        $this->assertSame(201, $create->getStatusCode());
        $orgId = json_decode((string) $create->getBody(), true)['id'];

        // vault settings should have been seeded with the default retention
        $pdo  = self::pdo();
        $stmt = $pdo->query("SELECT retention_years FROM vault_settings WHERE organization_id = {$orgId}");
        assert($stmt !== false);
        $row = $stmt->fetch();
        $this->assertNotFalse($row, 'vault_settings row must be seeded on org creation');
        $this->assertGreaterThanOrEqual(7, (int) $row['retention_years']);
    }

    public function test_superadmin_can_get_organization_by_id(): void
    {
        $pdo  = self::pdo();
        $stmt = $pdo->query("SELECT id FROM organizations WHERE slug = 'test-org'");
        assert($stmt !== false);
        $orgId = (int) $stmt->fetch()['id'];

        $response = $this->handler()->handle(
            $this->request('GET', "/admin/organizations/{$orgId}", self::$superToken),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame($orgId, json_decode((string) $response->getBody(), true)['id']);
    }

    public function test_superadmin_can_update_organization(): void
    {
        $slug  = 'upd-' . uniqid();
        $create = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, [
                'name' => 'Before',
                'slug' => $slug,
            ]),
        );
        $orgId = json_decode((string) $create->getBody(), true)['id'];

        $update = $this->handler()->handle(
            $this->request('PATCH', "/admin/organizations/{$orgId}", self::$superToken, [
                'name' => 'After',
                'slug' => $slug,
            ]),
        );

        $this->assertSame(200, $update->getStatusCode(), (string) $update->getBody());
        $this->assertSame('After', json_decode((string) $update->getBody(), true)['name']);
    }

    public function test_superadmin_can_delete_organization(): void
    {
        $slug  = 'del-' . uniqid();
        $create = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, [
                'name' => 'ToDelete',
                'slug' => $slug,
            ]),
        );
        $orgId = json_decode((string) $create->getBody(), true)['id'];

        $delete = $this->handler()->handle(
            $this->request('DELETE', "/admin/organizations/{$orgId}", self::$superToken),
        );
        $this->assertSame(204, $delete->getStatusCode());

        // Confirm gone
        $get = $this->handler()->handle(
            $this->request('GET', "/admin/organizations/{$orgId}", self::$superToken),
        );
        $this->assertSame(404, $get->getStatusCode());
    }

    // ── slug conflict ────────────────────────────────────────────────────────

    public function test_create_with_duplicate_slug_returns_409(): void
    {
        $slug = 'dup-' . uniqid();
        $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, [
                'name' => 'First',
                'slug' => $slug,
            ]),
        );

        $second = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, [
                'name' => 'Second',
                'slug' => $slug,
            ]),
        );

        $this->assertSame(409, $second->getStatusCode());
    }

    // ── not found ────────────────────────────────────────────────────────────

    public function test_get_nonexistent_organization_returns_404(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/organizations/999999', self::$superToken),
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_delete_nonexistent_organization_returns_404(): void
    {
        $response = $this->handler()->handle(
            $this->request('DELETE', '/admin/organizations/999999', self::$superToken),
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    // ── role boundary: admin must be refused ─────────────────────────────────

    public function test_admin_cannot_list_organizations(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/organizations', self::$adminToken),
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_admin_cannot_create_organization(): void
    {
        $response = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$adminToken, [
                'name' => 'X',
                'slug' => 'x-' . uniqid(),
            ]),
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    // ── auth boundary ────────────────────────────────────────────────────────

    public function test_unauthenticated_list_returns_401(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/organizations'),
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_unauthenticated_create_returns_401(): void
    {
        $response = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', null, ['name' => 'X', 'slug' => 'x']),
        );

        $this->assertSame(401, $response->getStatusCode());
    }
}
