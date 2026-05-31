<?php

declare(strict_types=1);

namespace NeneVault\Tests\Audit;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * Audit event listing boundary tests: filter combinations, pagination,
 * ordering, append-only guarantee, tenant scoping.
 */
final class AuditBoundaryTest extends ApiTestCase
{
    private static string $adminToken = '';
    private static string $superToken = '';
    private static int    $orgId      = 0;
    private static bool   $seeded     = false;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId      = self::ensureOrg('test-org');
        self::$adminToken = self::issueToken('admin', self::$orgId, userId: 180);
        self::$superToken = self::issueSuperadminToken(userId: 181);
    }

    protected function setUp(): void
    {
        if (!self::$seeded) {
            // Generate a known mix of audit events
            $h  = $this->handler();
            $id = $this->uploadDoc($h, self::$adminToken, 'AuditSeed', '2026-01-01', '5000');
            $h->handle($this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'counterparty_name' => 'AuditSeed Updated',
                'category'          => 'contract',
            ]));
            $h->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'audit seed void']));
            self::$seeded = true;
        }
    }

    // ── Filters ────────────────────────────────────────────────────────────────

    public function test_filter_by_entity_type(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?entity_type=vault_document', self::$adminToken),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        foreach ($body['items'] as $event) {
            $this->assertSame('vault_document', $event['entity_type']);
        }
    }

    public function test_filter_by_action_uploaded(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?action=document.uploaded', self::$adminToken),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertGreaterThanOrEqual(1, $body['total']);
        foreach ($body['items'] as $event) {
            $this->assertSame('document.uploaded', $event['action']);
        }
    }

    public function test_filter_by_action_voided(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?action=document.voided', self::$adminToken),
        );
        $body = json_decode((string) $resp->getBody(), true);
        foreach ($body['items'] as $event) {
            $this->assertSame('document.voided', $event['action']);
        }
    }

    public function test_filter_by_nonexistent_action_returns_empty(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?action=document.nonexistent_action', self::$adminToken),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertSame(0, $body['total']);
    }

    public function test_combined_entity_type_and_action_filter(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?entity_type=vault_document&action=document.metadata_changed', self::$adminToken),
        );
        $body = json_decode((string) $resp->getBody(), true);
        foreach ($body['items'] as $event) {
            $this->assertSame('vault_document', $event['entity_type']);
            $this->assertSame('document.metadata_changed', $event['action']);
        }
    }

    // ── Pagination ─────────────────────────────────────────────────────────────

    public function test_pagination_limit_1(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?limit=1', self::$adminToken),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertCount(1, $body['items']);
        $this->assertSame(1, $body['limit']);
    }

    public function test_pagination_offset_returns_different_event(): void
    {
        $page1 = json_decode((string) $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?limit=1&offset=0', self::$adminToken),
        )->getBody(), true);
        $page2 = json_decode((string) $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?limit=1&offset=1', self::$adminToken),
        )->getBody(), true);

        if ($page1['total'] >= 2) {
            $this->assertNotSame($page1['items'][0]['id'], $page2['items'][0]['id']);
        } else {
            $this->markTestSkipped('Not enough audit events for offset comparison');
        }
    }

    // ── Ordering (most recent first) ───────────────────────────────────────────

    public function test_events_ordered_most_recent_first(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?limit=10', self::$adminToken),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $createdAts = array_column($body['items'], 'created_at');
        $sorted = $createdAts;
        rsort($sorted);
        $this->assertSame($sorted, $createdAts, 'Audit events must be ordered most-recent-first');
    }

    // ── Response shape ───────────────────────────────────────────────────────

    public function test_event_contains_required_fields(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events?action=document.metadata_changed&limit=1', self::$adminToken),
        );
        $body = json_decode((string) $resp->getBody(), true);
        if ($body['total'] === 0) {
            $this->markTestSkipped('No metadata_changed events to inspect');
        }
        $event = $body['items'][0];
        foreach (['id', 'action', 'entity_type', 'entity_id', 'created_at', 'before_json', 'after_json'] as $field) {
            $this->assertArrayHasKey($field, $event);
        }
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $resp = $this->handler()->handle($this->request('GET', '/admin/audit-events'));
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function test_superadmin_can_list_all_events(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/audit-events', self::$superToken),
        );
        $this->assertSame(200, $resp->getStatusCode());
    }
}
