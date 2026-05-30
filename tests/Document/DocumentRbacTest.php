<?php

declare(strict_types=1);

namespace NeneVault\Tests\Document;

use NeneVault\Tests\Support\ApiTestCase;
use Nyholm\Psr7Server\ServerRequestCreator;

/**
 * Role-Based Access Control and tenant isolation tests.
 *
 * All tokens use the env-resolved org (test-org) so CapabilityMiddleware passes.
 * Different roles are tested by varying the 'role' claim in the JWT.
 *
 * Role matrix:
 *   Role      Upload  MetadataEdit  Void   Restore  Search/Get
 *   admin     ✅      ✅            ✅     ✅       ✅
 *   member    ✅      ✅            ✗ 403  ✗ 403    ✅
 *   viewer    ✗ 403   ✗ 403        ✗ 403  ✗ 403    ✅
 *
 * Tenant isolation:
 *   A token carrying a foreign org_id (≠ resolved org) is refused 403 by
 *   CapabilityMiddleware — this is the enforcement boundary for cross-tenant access.
 */
final class DocumentRbacTest extends ApiTestCase
{
    private static string $adminToken  = '';
    private static string $memberToken = '';
    private static string $viewerToken = '';
    private static string $foreignToken = '';  // wrong org_id → always 403
    private static int    $orgId       = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        // All tokens must match the env-resolved org.
        self::$orgId = self::ensureOrg('test-org');

        self::$adminToken  = self::issueToken('admin', self::$orgId, userId: 30);
        self::$memberToken = self::issueToken('member', self::$orgId, userId: 31);
        self::$viewerToken = self::issueToken('viewer', self::$orgId, userId: 32);
        // A token claiming a different org_id — simulates a user from another tenant.
        self::$foreignToken = self::issueToken('admin', 999999, userId: 33);
    }

    // ── viewer can search / get ──────────────────────────────────────────────

    public function test_viewer_can_search_documents(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents', self::$viewerToken),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_viewer_can_get_document_by_id(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'ViewerGet Co', '2026-01-10', '100');

        $response = $this->handler()->handle(
            $this->request('GET', "/admin/vault/documents/{$id}", self::$viewerToken),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── viewer cannot mutate ─────────────────────────────────────────────────

    public function test_viewer_cannot_upload(): void
    {
        $psr17   = $this->psr17();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $tmp     = $this->makeTempPdf('viewer upload');
        $file    = $this->uploadedFile($tmp);

        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$viewerToken],
        )
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody(['counterparty_name' => 'X', 'category' => 'other']);

        $response = $this->handler()->handle($request);
        @unlink($tmp);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_viewer_cannot_edit_metadata(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'ViewerEdit Co', '2026-01-11', '200');

        $response = $this->handler()->handle(
            $this->request(
                'PATCH',
                "/admin/vault/documents/{$id}/metadata",
                self::$viewerToken,
                ['counterparty_name' => 'Hacked'],
            ),
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_viewer_cannot_void(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'ViewerVoid Co', '2026-01-12', '300');

        $response = $this->handler()->handle(
            $this->request(
                'POST',
                "/admin/vault/documents/{$id}/void",
                self::$viewerToken,
                ['void_reason' => 'test'],
            ),
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    // ── member can upload and edit ───────────────────────────────────────────

    public function test_member_can_upload(): void
    {
        $psr17   = $this->psr17();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $tmp     = $this->makeTempPdf('member upload');
        $file    = $this->uploadedFile($tmp);

        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$memberToken],
        )
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody([
                'counterparty_name' => 'Member Upload Co',
                'category'          => 'receipt',
                'transaction_date'  => '2026-02-01',
                'amount_cents'      => '500',
            ]);

        $response = $this->handler()->handle($request);
        @unlink($tmp);

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
    }

    public function test_member_can_edit_metadata(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$memberToken, 'MemberEdit Co', '2026-02-02', '600');

        $response = $this->handler()->handle(
            $this->request(
                'PATCH',
                "/admin/vault/documents/{$id}/metadata",
                self::$memberToken,
                ['counterparty_name' => 'MemberEdit Updated', 'category' => 'receipt'],
            ),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(
            'MemberEdit Updated',
            json_decode((string) $response->getBody(), true)['counterparty_name'],
        );
    }

    public function test_member_cannot_void(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'MemberVoid Co', '2026-02-03', '700');

        $response = $this->handler()->handle(
            $this->request(
                'POST',
                "/admin/vault/documents/{$id}/void",
                self::$memberToken,
                ['void_reason' => 'test'],
            ),
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_member_cannot_restore(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'MemberRestore Co', '2026-02-04', '800');
        $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'setup']),
        );

        $response = $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/restore", self::$memberToken),
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    // ── admin can void and restore ───────────────────────────────────────────

    public function test_admin_can_void_and_restore(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'AdminVoidRestore Co', '2026-03-01', '9000');

        $void = $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'testing']),
        );
        $this->assertSame(200, $void->getStatusCode());
        $this->assertSame('voided', json_decode((string) $void->getBody(), true)['status']);

        $restore = $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/restore", self::$adminToken),
        );
        $this->assertSame(200, $restore->getStatusCode());
        $this->assertSame('active', json_decode((string) $restore->getBody(), true)['status']);
    }

    // ── category and voided search filters ──────────────────────────────────

    public function test_search_by_category(): void
    {
        $this->uploadDoc($this->handler(), self::$adminToken, 'CatFilter Co', '2026-04-01', '11000', 'contract');

        $response = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents?category=contract&counterparty_name=CatFilter', self::$adminToken),
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThanOrEqual(1, $body['total']);
        foreach ($body['items'] as $item) {
            $this->assertSame('contract', $item['category']);
        }
    }

    public function test_voided_documents_hidden_by_default(): void
    {
        $marker = 'VoidedHidden-' . uniqid();
        $id     = $this->uploadDoc($this->handler(), self::$adminToken, $marker, '2026-04-02', '200');
        $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'hidden test']),
        );

        $body = json_decode(
            (string) $this->handler()->handle(
                $this->request('GET', "/admin/vault/documents?counterparty_name={$marker}", self::$adminToken),
            )->getBody(),
            true,
        );

        $ids = array_column($body['items'], 'id');
        $this->assertNotContains($id, $ids, 'Voided document must not appear without include_voided');
    }

    public function test_include_voided_shows_voided_documents(): void
    {
        $marker = 'VoidedIncluded-' . uniqid();
        $id     = $this->uploadDoc($this->handler(), self::$adminToken, $marker, '2026-04-03', '300');
        $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$adminToken, ['void_reason' => 'include test']),
        );

        $body = json_decode(
            (string) $this->handler()->handle(
                $this->request('GET', "/admin/vault/documents?counterparty_name={$marker}&include_voided=true", self::$adminToken),
            )->getBody(),
            true,
        );

        $ids = array_column($body['items'], 'id');
        $this->assertContains($id, $ids);
    }

    // ── tenant isolation: foreign org_id is refused ──────────────────────────

    /**
     * A JWT carrying a different org_id is refused by CapabilityMiddleware (403).
     * This is the system-level enforcement boundary for cross-tenant access.
     */
    public function test_foreign_token_cannot_search_documents(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents', self::$foreignToken),
        );

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('org-access-denied', basename($body['type']));
    }

    public function test_foreign_token_cannot_get_document(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'IsolationGet Co', '2026-05-01', '1');

        $response = $this->handler()->handle(
            $this->request('GET', "/admin/vault/documents/{$id}", self::$foreignToken),
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_foreign_token_cannot_upload(): void
    {
        $psr17   = $this->psr17();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $tmp     = $this->makeTempPdf('foreign upload');
        $file    = $this->uploadedFile($tmp);

        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$foreignToken],
        )
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody(['counterparty_name' => 'Evil Co', 'category' => 'other']);

        $response = $this->handler()->handle($request);
        @unlink($tmp);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_foreign_token_cannot_void_document(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'IsolationVoid Co', '2026-05-02', '2');

        $response = $this->handler()->handle(
            $this->request('POST', "/admin/vault/documents/{$id}/void", self::$foreignToken, ['void_reason' => 'attack']),
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_foreign_token_cannot_edit_metadata(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'IsolationEdit Co', '2026-05-03', '3');

        $response = $this->handler()->handle(
            $this->request('PATCH', "/admin/vault/documents/{$id}/metadata", self::$foreignToken, ['counterparty_name' => 'Hacked']),
        );

        $this->assertSame(403, $response->getStatusCode());
    }
}
