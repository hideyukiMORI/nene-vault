<?php

declare(strict_types=1);

namespace NeneVault\Tests\Export;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * Export boundary tests: CSV columns, empty export, viewer access, foreign token.
 */
final class ExportBoundaryTest extends ApiTestCase
{
    private static string $adminToken  = '';
    private static string $viewerToken = '';
    private static string $foreignToken = '';
    private static int    $orgId       = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId        = self::ensureOrg('test-org');
        self::$adminToken   = self::issueToken('admin', self::$orgId, userId: 50);
        self::$viewerToken  = self::issueToken('viewer', self::$orgId, userId: 52);
        self::$foreignToken = self::issueToken('admin', 999999, userId: 53);
    }

    // ── happy path ───────────────────────────────────────────────────────────

    public function test_export_returns_csv_with_correct_columns(): void
    {
        $marker = 'ExportBnd-' . uniqid();
        $this->uploadDoc($this->handler(), self::$adminToken, $marker, '2026-07-01', '50000');

        $response = $this->handler()->handle(
            $this->request('POST', '/admin/vault/export', self::$adminToken, [
                'counterparty_name' => $marker,
            ]),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        // CsvWriter emits a UTF-8 BOM by framework default; strip it before parsing.
        $csv  = (string) $response->getBody();
        $csv  = str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;
        $cols = str_getcsv(explode("\n", trim($csv))[0], separator: ',', enclosure: '"', escape: '');
        $this->assertContains('document_id', $cols);
        $this->assertContains('file_sha256', $cols);
        $this->assertContains('counterparty_name', $cols);
        $this->assertContains('amount_cents', $cols);
    }

    public function test_export_with_no_matching_docs_returns_header_only(): void
    {
        $response = $this->handler()->handle(
            $this->request('POST', '/admin/vault/export', self::$adminToken, [
                'counterparty_name' => 'NO_SUCH_VENDOR_' . uniqid(),
            ]),
        );

        $this->assertSame(200, $response->getStatusCode());
        $lines = array_values(array_filter(explode("\n", trim((string) $response->getBody()))));
        $this->assertCount(1, $lines, 'Empty export must return header row only');
    }

    public function test_export_csv_contains_uploaded_document(): void
    {
        $marker = 'CsvContent-' . uniqid();
        $this->uploadDoc($this->handler(), self::$adminToken, $marker, '2026-08-01', '77777');

        $response = $this->handler()->handle(
            $this->request('POST', '/admin/vault/export', self::$adminToken, [
                'counterparty_name' => $marker,
            ]),
        );

        $csv = (string) $response->getBody();
        $this->assertStringContainsString($marker, $csv);
        $this->assertStringContainsString('77777', $csv);
    }

    // ── zip format ───────────────────────────────────────────────────────────

    public function test_zip_export_returns_zip_content_type(): void
    {
        $marker = 'ZipBnd-' . uniqid();
        $this->uploadDoc($this->handler(), self::$adminToken, $marker, '2026-09-01', '11111');

        $response = $this->handler()->handle(
            $this->request('POST', '/admin/vault/export', self::$adminToken, [
                'format' => 'zip',
                'counterparty_name' => $marker,
            ]),
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertStringContainsString('application/zip', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('.zip', $response->getHeaderLine('Content-Disposition'));
    }

    public function test_zip_export_empty_result_contains_only_manifest(): void
    {
        $response = $this->handler()->handle(
            $this->request('POST', '/admin/vault/export', self::$adminToken, [
                'format' => 'zip',
                'counterparty_name' => 'NO_SUCH_VENDOR_ZIP_' . uniqid(),
            ]),
        );

        $this->assertSame(200, $response->getStatusCode());

        $tmpZip = tempnam(sys_get_temp_dir(), 'vault_bnd_zip_');
        assert($tmpZip !== false);
        file_put_contents($tmpZip, (string) $response->getBody());

        $zip = new \ZipArchive();
        $this->assertSame(true, $zip->open($tmpZip));
        $this->assertNotFalse($zip->locateName('manifest.csv'), 'manifest.csv must exist even in empty export');
        // No files/ entries expected
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $this->assertFalse(is_string($name) && str_starts_with($name, 'files/'), 'Empty export must have no files/');
        }
        $zip->close();
        @unlink($tmpZip);
    }

    // ── role boundary ────────────────────────────────────────────────────────

    public function test_viewer_cannot_export(): void
    {
        $this->assertSame(403, $this->handler()->handle(
            $this->request('POST', '/admin/vault/export', self::$viewerToken, []),
        )->getStatusCode());
    }

    // ── tenant isolation ─────────────────────────────────────────────────────

    /**
     * A JWT with a foreign org_id is refused by CapabilityMiddleware (403).
     * No cross-tenant data can be exported.
     */
    public function test_foreign_token_cannot_export(): void
    {
        $this->assertSame(403, $this->handler()->handle(
            $this->request('POST', '/admin/vault/export', self::$foreignToken, []),
        )->getStatusCode());
    }

    // ── auth boundary ────────────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->assertSame(401, $this->handler()->handle(
            $this->request('POST', '/admin/vault/export', null, []),
        )->getStatusCode());
    }
}
