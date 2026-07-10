<?php

declare(strict_types=1);

namespace NeneVault\Tests\Document;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * Version download and history boundary tests: wrong IDs, tenant isolation,
 * SHA-256 header, Content-Disposition.
 */
final class DocumentVersionBoundaryTest extends ApiTestCase
{
    private static string $adminToken   = '';
    private static string $foreignToken = '';
    private static int    $orgId        = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId        = self::ensureOrg('test-org');
        self::$adminToken   = self::issueToken('admin', self::$orgId, userId: 140);
        // A token claiming an org that does not exist: under claim-based tenant
        // resolution (#141) it fails closed with 404 org-not-found.
        self::$foreignToken = self::issueToken('admin', 999998, userId: 141);
    }

    // ── Download ──────────────────────────────────────────────────────────────

    public function test_download_returns_correct_content_type_for_pdf(): void
    {
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$adminToken, 'DlPdf', '2026-01-01', '1000');

        [$docId, $versionId] = $this->getVersionId($handler, $id);

        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$docId}/versions/{$versionId}/download", self::$adminToken),
        );

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertStringContainsString('application/pdf', $resp->getHeaderLine('Content-Type'));
    }

    public function test_download_content_disposition_is_attachment(): void
    {
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$adminToken, 'DlDisposition', '2026-01-01', '1000');

        [$docId, $versionId] = $this->getVersionId($handler, $id);

        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$docId}/versions/{$versionId}/download", self::$adminToken),
        );

        $this->assertStringContainsString('attachment', $resp->getHeaderLine('Content-Disposition'));
    }

    public function test_download_unknown_version_returns_404(): void
    {
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$adminToken, 'DlUnknownV', '2026-01-01', '1000');

        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$id}/versions/00000000000000000000000000/download", self::$adminToken),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function test_download_wrong_document_id_returns_404(): void
    {
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$adminToken, 'DlWrongDoc', '2026-01-01', '1000');

        [$docId, $versionId] = $this->getVersionId($handler, $id);

        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/00000000000000000000000000/versions/{$versionId}/download", self::$adminToken),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function test_download_foreign_token_returns_404(): void
    {
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$adminToken, 'DlForeign', '2026-01-01', '1000');

        [$docId, $versionId] = $this->getVersionId($handler, $id);

        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$docId}/versions/{$versionId}/download", self::$foreignToken),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function test_download_unauthenticated_returns_401(): void
    {
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$adminToken, 'DlUnauth', '2026-01-01', '1000');

        [$docId, $versionId] = $this->getVersionId($handler, $id);

        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$docId}/versions/{$versionId}/download"),
        );
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function test_download_body_starts_with_pdf_magic(): void
    {
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$adminToken, 'DlContent', '2026-01-01', '1000');

        [$docId, $versionId] = $this->getVersionId($handler, $id);

        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$docId}/versions/{$versionId}/download", self::$adminToken),
        );
        $this->assertStringStartsWith('%PDF', (string) $resp->getBody());
    }

    // ── History ───────────────────────────────────────────────────────────────

    public function test_history_returns_version_list(): void
    {
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$adminToken, 'HistVer', '2026-01-01', '1000');

        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$id}/history", self::$adminToken),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertArrayHasKey('versions', $body);
        $this->assertArrayHasKey('audit_events', $body);
        $this->assertCount(1, $body['versions']);
        $this->assertSame(1, $body['versions'][0]['version_number']);
    }

    public function test_history_nonexistent_document_returns_404(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents/00000000000000000000000000/history', self::$adminToken),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function test_history_foreign_token_returns_404(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'HistForeign', '2026-01-01', '1000');
        $resp = $this->handler()->handle(
            $this->request('GET', "/admin/vault/documents/{$id}/history", self::$foreignToken),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    // ── Get by ID ─────────────────────────────────────────────────────────────

    public function test_get_nonexistent_document_returns_404(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents/00000000000000000000000000', self::$adminToken),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function test_get_document_foreign_token_returns_404(): void
    {
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'GetForeign', '2026-01-01', '1000');
        $resp = $this->handler()->handle(
            $this->request('GET', "/admin/vault/documents/{$id}", self::$foreignToken),
        );
        $this->assertSame(404, $resp->getStatusCode());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array{string, string} [documentId, versionId] */
    private function getVersionId(\Psr\Http\Server\RequestHandlerInterface $handler, string $docId): array
    {
        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$docId}/history", self::$adminToken),
        );
        $body      = json_decode((string) $resp->getBody(), true);
        $versionId = (string) $body['versions'][0]['id'];

        return [$docId, $versionId];
    }
}
