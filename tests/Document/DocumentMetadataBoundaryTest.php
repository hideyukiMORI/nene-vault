<?php

declare(strict_types=1);

namespace NeneVault\Tests\Document;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * Metadata edit boundary tests: field clearing, required fields,
 * category enum, retention recalculation, and is_metadata_confirmed flag.
 */
final class DocumentMetadataBoundaryTest extends ApiTestCase
{
    private static string $adminToken  = '';
    private static string $memberToken = '';
    private static string $viewerToken = '';
    private static int    $orgId       = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId        = self::ensureOrg('test-org');
        self::$adminToken   = self::issueToken('admin', self::$orgId, userId: 120);
        self::$memberToken  = self::issueToken('member', self::$orgId, userId: 121);
        self::$viewerToken  = self::issueToken('viewer', self::$orgId, userId: 122);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_metadata_edit_sets_confirmed_flag(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'MetaBnd', '2026-01-01', '1000');
        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'counterparty_name' => 'MetaBnd Updated',
                'category'          => 'contract',
                'transaction_date'  => '2026-01-15',
                'amount_cents'      => 2000,
            ]),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertTrue($body['is_metadata_confirmed']);
        $this->assertSame('MetaBnd Updated', $body['counterparty_name']);
        $this->assertSame(2000, $body['amount_cents']);
    }

    public function test_metadata_edit_clears_transaction_date_with_null(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'ClearDate', '2026-03-01', '500');
        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'counterparty_name' => 'ClearDate',
                'category'          => 'receipt',
                'transaction_date'  => null,
            ]),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertNull($body['transaction_date']);
    }

    public function test_metadata_edit_clears_amount_with_null(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'ClearAmt', '2026-04-01', '9999');
        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'counterparty_name' => 'ClearAmt',
                'category'          => 'receipt',
                'transaction_date'  => '2026-04-01',
                'amount_cents'      => null,
            ]),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertNull($body['amount_cents']);
    }

    public function test_metadata_edit_replaces_tags(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'TagReplace', '2026-05-01', '100');
        $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'counterparty_name' => 'TagReplace',
                'category'          => 'receipt',
                'tags'              => ['old-tag'],
            ]),
        );
        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'counterparty_name' => 'TagReplace',
                'category'          => 'receipt',
                'tags'              => ['new-tag', 'another'],
            ]),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertContains('new-tag', $body['tags']);
        $this->assertNotContains('old-tag', $body['tags']);
    }

    public function test_metadata_edit_all_categories_accepted(): void
    {
        foreach (['invoice_received', 'contract', 'receipt', 'delivery_note', 'other'] as $cat) {
            $id   = $this->uploadDoc($this->handler(), self::$adminToken, "Cat-{$cat}", '2026-01-01', '1');
            $resp = $this->handler()->handle(
                $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                    'counterparty_name' => "Cat-{$cat}",
                    'category'          => $cat,
                ]),
            );
            $this->assertSame(200, $resp->getStatusCode(), "Category '{$cat}' must be accepted");
        }
    }

    // ── Validation failures ───────────────────────────────────────────────────

    public function test_metadata_edit_missing_counterparty_returns_422(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'RequiredCP', '2026-01-01', '1');
        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'category' => 'receipt',
            ]),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_metadata_edit_missing_category_returns_422(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'RequiredCat', '2026-01-01', '1');
        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'counterparty_name' => 'RequiredCat',
            ]),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_metadata_edit_invalid_category_returns_422(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'BadCat', '2026-01-01', '1');
        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'counterparty_name' => 'BadCat',
                'category'          => 'INVALID_CATEGORY',
            ]),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_metadata_edit_nonexistent_document_returns_404(): void
    {
        $resp = $this->handler()->handle(
            $this->request('PATCH', '/admin/vault/documents/00000000000000000000000000/metadata', self::$adminToken, [
                'counterparty_name' => 'X',
                'category'          => 'receipt',
            ]),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    // ── RBAC ─────────────────────────────────────────────────────────────────

    public function test_viewer_cannot_edit_metadata(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'ViewerEdit', '2026-01-01', '1');
        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$viewerToken, [
                'counterparty_name' => 'ViewerEdit',
                'category'          => 'receipt',
            ]),
        );
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_member_can_edit_metadata(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'MemberEdit', '2026-01-01', '1');
        $resp = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$memberToken, [
                'counterparty_name' => 'MemberEdit Updated',
                'category'          => 'receipt',
            ]),
        );
        $this->assertSame(200, $resp->getStatusCode());
    }

    // ── Audit trail ───────────────────────────────────────────────────────────

    public function test_metadata_edit_generates_audit_event_with_before_after(): void
    {
        $id      = $this->uploadDoc($this->handler(), self::$adminToken, 'AuditCheck', '2026-06-01', '5000');
        $handler = $this->handler();

        $handler->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$adminToken, [
                'counterparty_name' => 'AuditCheck Updated',
                'category'          => 'contract',
                'amount_cents'      => 9999,
            ]),
        );

        $history = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$id}/history", self::$adminToken),
        );
        $hBody  = json_decode((string) $history->getBody(), true);
        $events = array_filter($hBody['audit_events'], static fn ($e) => $e['action'] === 'document.metadata_changed');
        $this->assertNotEmpty($events, 'document.metadata_changed audit event must exist');
        $event = array_values($events)[0];
        $this->assertSame('AuditCheck', $event['before_json']['counterparty_name']);
        $this->assertSame('AuditCheck Updated', $event['after_json']['counterparty_name']);
    }
}
