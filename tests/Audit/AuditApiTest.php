<?php

declare(strict_types=1);

namespace NeneVault\Tests\Audit;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * HTTP-level tests for GET /admin/audit-events.
 *
 * Boundaries:
 *   - returns paginated list with correct shape
 *   - filters by action, entity_type, entity_id
 *   - superadmin sees all events; org admin sees own org
 *   - pagination: limit / offset
 *   - unauthenticated → 401
 */
final class AuditApiTest extends ApiTestCase
{
    private static string $adminToken = '';
    private static string $superToken = '';
    private static int    $orgId      = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        // Must use the env-resolved org (ORG_SLUG=test-org) so CapabilityMiddleware passes.
        self::$orgId      = self::ensureOrg('test-org');
        self::$adminToken = self::issueToken('admin', self::$orgId, userId: 20);
        self::$superToken = self::issueSuperadminToken(userId: 21);
    }

    // ── happy path ───────────────────────────────────────────────────────────

    public function test_returns_paginated_list(): void
    {
        // Trigger at least one event
        $this->uploadDoc($this->handler(), self::$adminToken, 'Audit List Co', '2026-02-01', '5000');

        $response = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events', self::$adminToken),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('items', $body);
        $this->assertArrayHasKey('total', $body);
        $this->assertArrayHasKey('limit', $body);
        $this->assertArrayHasKey('offset', $body);
        $this->assertGreaterThan(0, $body['total']);
    }

    public function test_filter_by_action(): void
    {
        $this->uploadDoc($this->handler(), self::$adminToken, 'Filter Action Co', '2026-03-01', '1000');

        $response = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?action=document.uploaded', self::$adminToken),
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotEmpty($body['items'], 'Must have at least one document.uploaded event');
        foreach ($body['items'] as $event) {
            $this->assertSame('document.uploaded', $event['action']);
        }
    }

    public function test_filter_by_entity_type(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?entity_type=vault_document', self::$adminToken),
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        foreach ($body['items'] as $event) {
            $this->assertSame('vault_document', $event['entity_type']);
        }
    }

    public function test_filter_by_entity_id(): void
    {
        $docId = $this->uploadDoc(
            $this->handler(),
            self::$adminToken,
            'Entity Filter Co',
            '2026-04-01',
            '2000',
        );

        $response = $this->handler()->handle(
            $this->request(
                'GET',
                "/admin/audit-events?entity_type=vault_document&entity_id={$docId}",
                self::$adminToken,
            ),
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThanOrEqual(1, $body['total'], 'Upload must generate at least one audit event');
        foreach ($body['items'] as $event) {
            $this->assertSame($docId, $event['entity_id']);
        }
    }

    public function test_pagination_limit_and_offset(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->uploadDoc($this->handler(), self::$adminToken, "Pager Co {$i}", '2026-05-01', '100');
        }

        $page = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?limit=2&offset=0', self::$adminToken),
        );
        $this->assertSame(200, $page->getStatusCode());
        $body = json_decode((string) $page->getBody(), true);
        $this->assertCount(2, $body['items']);
        $this->assertSame(2, $body['limit']);
        $this->assertSame(0, $body['offset']);
    }

    public function test_superadmin_can_list_all_events(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events', self::$superToken),
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThan(0, $body['total']);
    }

    // ── event content ────────────────────────────────────────────────────────

    public function test_upload_event_has_correct_fields(): void
    {
        $docId = $this->uploadDoc(
            $this->handler(),
            self::$adminToken,
            'Field Check Co',
            '2026-06-01',
            '9999',
        );

        $response = $this->handler()->handle(
            $this->request(
                'GET',
                "/admin/audit-events?entity_id={$docId}&action=document.uploaded",
                self::$adminToken,
            ),
        );

        $body  = json_decode((string) $response->getBody(), true);
        $this->assertNotEmpty($body['items'], 'Must find the upload event');
        $event = $body['items'][0];
        $this->assertSame('document.uploaded', $event['action']);
        $this->assertSame('vault_document', $event['entity_type']);
        $this->assertSame($docId, $event['entity_id']);
        $this->assertArrayHasKey('created_at', $event);
    }

    // ── auth boundary ────────────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events'),
        );

        $this->assertSame(401, $response->getStatusCode());
    }
}
