<?php

declare(strict_types=1);

namespace NeneVault\Tests\Document;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * Void/Restore state-machine boundary tests.
 *
 * State transitions:
 *   active --void--> voided --restore--> active
 *
 * Invalid transitions tested:
 *   active  --restore--> error (can't restore active)
 *   voided  --void-->    error (can't re-void)
 */
final class DocumentLifecycleBoundaryTest extends ApiTestCase
{
    private static string $adminToken  = '';
    private static string $memberToken = '';
    private static string $viewerToken = '';
    private static int    $orgId       = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId        = self::ensureOrg('test-org');
        self::$adminToken   = self::issueToken('admin', self::$orgId, userId: 130);
        self::$memberToken  = self::issueToken('member', self::$orgId, userId: 131);
        self::$viewerToken  = self::issueToken('viewer', self::$orgId, userId: 132);
    }

    // ── Void ─────────────────────────────────────────────────────────────────

    public function test_void_active_document_succeeds(): void
    {
        $id      = $this->uploadDoc($this->handler(), self::$adminToken, 'VoidMe');
        $handler = $this->handler();

        $resp = $handler->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, [
                'void_reason' => 'Registered by mistake',
            ]),
        );

        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertSame('voided', $body['status']);
        $this->assertSame('Registered by mistake', $body['void_reason']);
        $this->assertNotNull($body['voided_at']);
    }

    public function test_void_requires_reason(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'VoidNoReason');
        $resp = $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, [
                'void_note' => 'only a note, no reason',
            ]),
        );
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_void_accepts_optional_void_note(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'VoidNote');
        $resp = $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, [
                'void_reason' => 'Incorrect amount',
                'void_note'   => 'Will re-upload with correct amount after confirmation from vendor.',
            ]),
        );
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_cannot_re_void_already_voided_document(): void
    {
        $id      = $this->uploadDoc($this->handler(), self::$adminToken, 'ReVoid');
        $handler = $this->handler();

        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'First void']));

        $resp = $handler->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'Second void attempt']),
        );

        $this->assertSame(409, $resp->getStatusCode(), 'Re-voiding a voided document must return 409');
    }

    public function test_re_void_leaves_single_void_audit_event(): void
    {
        $id      = $this->uploadDoc($this->handler(), self::$adminToken, 'PreserveReason');
        $handler = $this->handler();

        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'Original reason']));
        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'Overwrite attempt']));

        $history = $handler->handle($this->request('GET', "/admin/vault/documents/{$id}/history", self::$adminToken));
        $events  = array_filter(
            json_decode((string) $history->getBody(), true)['audit_events'],
            static fn ($e) => $e['action'] === 'document.voided',
        );
        $this->assertCount(1, $events, 'Re-void is rejected, so exactly one void event must exist');
    }

    public function test_void_nonexistent_document_returns_404(): void
    {
        $resp = $this->handler()->handle(
            $this->request('POST', '/admin/vault/documents/00000000000000000000000000/void', self::$adminToken, [
                'void_reason' => 'Test',
            ]),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    // ── Restore ───────────────────────────────────────────────────────────────

    public function test_restore_voided_document_succeeds(): void
    {
        $id      = $this->uploadDoc($this->handler(), self::$adminToken, 'RestoreMe');
        $handler = $this->handler();

        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'Will restore']));

        $resp = $handler->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/restore", self::$adminToken),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertSame('active', $body['status']);
        $this->assertNull($body['voided_at']);
    }

    public function test_cannot_restore_active_document(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'RestoreActive');
        $resp = $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/restore", self::$adminToken),
        );
        $this->assertSame(409, $resp->getStatusCode(), 'Restoring an active document must return 409');
    }

    public function test_restore_then_void_cycle(): void
    {
        $id      = $this->uploadDoc($this->handler(), self::$adminToken, 'VoidRestoreCycle');
        $handler = $this->handler();

        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'Cycle test 1']));
        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/restore", self::$adminToken));
        $resp = $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'Cycle test 2']));

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('voided', json_decode((string) $resp->getBody(), true)['status']);
    }

    // ── RBAC ─────────────────────────────────────────────────────────────────

    public function test_member_cannot_void(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'MemberVoid');
        $resp = $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$memberToken, ['void_reason' => 'Test']),
        );
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_member_cannot_restore(): void
    {
        $id      = $this->uploadDoc($this->handler(), self::$adminToken, 'MemberRestore');
        $handler = $this->handler();

        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'Setup']));

        $resp = $handler->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/restore", self::$memberToken),
        );
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_viewer_cannot_void(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'ViewerVoid');
        $resp = $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$viewerToken, ['void_reason' => 'Test']),
        );
        $this->assertSame(403, $resp->getStatusCode());
    }

    // ── Audit trail ───────────────────────────────────────────────────────────

    public function test_void_restore_both_appear_in_history(): void
    {
        $id      = $this->uploadDoc($this->handler(), self::$adminToken, 'LifecycleAudit');
        $handler = $this->handler();

        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'Audit test']));
        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/restore", self::$adminToken));

        $history = $handler->handle($this->request('GET', "/admin/vault/documents/{$id}/history", self::$adminToken));
        $events  = array_column(json_decode((string) $history->getBody(), true)['audit_events'], 'action');

        $this->assertContains('document.uploaded', $events);
        $this->assertContains('document.voided', $events);
        $this->assertContains('document.restored', $events);
    }
}
