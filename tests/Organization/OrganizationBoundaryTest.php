<?php

declare(strict_types=1);

namespace NeneVault\Tests\Organization;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * Organization boundary tests: required fields, slug format/uniqueness,
 * superadmin-only access, 404 on unknown.
 */
final class OrganizationBoundaryTest extends ApiTestCase
{
    private static string $superToken = '';
    private static string $adminToken = '';
    private static int    $orgId      = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId      = self::ensureOrg('test-org');
        self::$superToken = self::issueSuperadminToken(userId: 160);
        self::$adminToken = self::issueToken('admin', self::$orgId, userId: 161);
    }

    // ── Validation ─────────────────────────────────────────────────────────────

    public function test_create_missing_name_returns_422(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, ['slug' => 'no-name-' . uniqid()]),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_missing_slug_returns_422(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, ['name' => 'No Slug Co']),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_uppercase_slug_rejected_422(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, ['name' => 'X', 'slug' => 'Invalid-Slug']),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_slug_with_spaces_rejected_422(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, ['name' => 'X', 'slug' => 'has spaces']),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_slug_with_underscore_rejected_422(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, ['name' => 'X', 'slug' => 'under_score']),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_create_valid_kebab_slug_accepted(): void
    {
        $slug = 'valid-kebab-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, ['name' => 'Valid Co', 'slug' => $slug]),
        );
        $this->assertSame(201, $resp->getStatusCode(), (string) $resp->getBody());
        $this->assertSame($slug, json_decode((string) $resp->getBody(), true)['slug']);
    }

    public function test_create_duplicate_slug_returns_409(): void
    {
        $slug = 'dup-org-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $first = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, ['name' => 'First', 'slug' => $slug]),
        );
        $this->assertSame(201, $first->getStatusCode());

        $second = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, ['name' => 'Second', 'slug' => $slug]),
        );
        $this->assertSame(409, $second->getStatusCode());
    }

    public function test_create_seeds_vault_settings(): void
    {
        $slug = 'seed-vs-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$superToken, ['name' => 'Seed VS', 'slug' => $slug]),
        );
        $this->assertSame(201, $resp->getStatusCode());

        // Settings row should exist for the new org (verified via DB)
        $newOrgId = (int) json_decode((string) $resp->getBody(), true)['id'];
        $stmt = self::pdo()->query("SELECT COUNT(*) AS c FROM vault_settings WHERE organization_id = {$newOrgId}");
        assert($stmt !== false);
        $this->assertSame(1, (int) $stmt->fetch()['c']);
    }

    // ── RBAC ─────────────────────────────────────────────────────────────────

    public function test_admin_cannot_create_organization(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/organizations', self::$adminToken, ['name' => 'X', 'slug' => 'admin-attempt-' . uniqid()]),
        );
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_admin_cannot_list_organizations(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/organizations', self::$adminToken),
        );
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_unauthenticated_returns_401(): void
    {
        $resp = $this->handler()->handle($this->request('GET', '/admin/organizations'));
        $this->assertSame(401, $resp->getStatusCode());
    }

    // ── 404 ───────────────────────────────────────────────────────────────────

    public function test_get_unknown_organization_returns_404(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/organizations/99999999', self::$superToken),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function test_update_unknown_organization_returns_404(): void
    {
        // name + slug are both required by the handler; supply valid values so the
        // request passes validation and reaches the not-found check.
        $resp = $this->handler()->handle(
            $this->request('PATCH', '/admin/organizations/99999999', self::$superToken, ['name' => 'Ghost', 'slug' => 'ghost-org']),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function test_update_missing_name_returns_422(): void
    {
        $resp = $this->handler()->handle(
            $this->request('PATCH', '/admin/organizations/' . self::$orgId, self::$superToken, ['slug' => 'x']),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_delete_unknown_organization_returns_404(): void
    {
        $resp = $this->handler()->handle(
            $this->request('DELETE', '/admin/organizations/99999999', self::$superToken),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }
}
