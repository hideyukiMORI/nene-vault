<?php

declare(strict_types=1);

namespace NeneVault\Tests\Document;

use NeneVault\Tests\Support\ApiTestCase;
use Nyholm\Psr7Server\ServerRequestCreator;

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

    public function test_download_content_disposition_rfc5987_for_japanese_filename(): void
    {
        // QA VLT-B7-03: a Japanese filename must be carried via RFC 5987
        // `filename*=UTF-8''…` (percent-encoded), with an ASCII `filename=`
        // fallback — not emitted as raw bytes that garble in the header.
        $handler = $this->handler();
        $psr17   = $this->psr17();
        $tmp     = $this->makeTempPdf('jp-name');

        $file = $psr17->createUploadedFile(
            $psr17->createStreamFromFile($tmp),
            (int) filesize($tmp),
            UPLOAD_ERR_OK,
            '請求書_4月.pdf',
            'application/pdf',
        );
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $up = $handler->handle(
            $creator->fromArrays(
                server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
                headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$adminToken],
            )
                ->withUploadedFiles(['file' => $file])
                ->withParsedBody([
                    'counterparty_name' => 'JpName',
                    'category'          => 'invoice_received',
                    'transaction_date'  => '2026-01-01',
                ]),
        );
        $this->assertSame(201, $up->getStatusCode(), (string) $up->getBody());
        @unlink($tmp);
        $docId = (string) json_decode((string) $up->getBody(), true)['id'];

        [$docId, $versionId] = $this->getVersionId($handler, $docId);
        $resp = $handler->handle(
            $this->request('GET', "/admin/vault/documents/{$docId}/versions/{$versionId}/download", self::$adminToken),
        );

        $cd = $resp->getHeaderLine('Content-Disposition');
        // RFC 5987 percent-encoding of the UTF-8 name (請 = %E8%AB%8B).
        $this->assertStringContainsString("filename*=UTF-8''", $cd);
        $this->assertStringContainsString('%E8%AB%8B', $cd);
        // ASCII fallback present (non-ASCII replaced with '_').
        $this->assertMatchesRegularExpression('/filename="[^"]*_[^"]*"/', $cd);
        // Header-injection safety: no raw CR/LF/quote leakage.
        $this->assertStringNotContainsString("\n", $cd);
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
