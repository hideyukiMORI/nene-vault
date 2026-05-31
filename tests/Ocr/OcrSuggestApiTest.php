<?php

declare(strict_types=1);

namespace NeneVault\Tests\Ocr;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * HTTP-level tests for GET /admin/vault/documents/{id}/ocr-suggest.
 *
 * OCR failures and not-found cases are surfaced as Problem Details via the
 * registered domain exception handlers (no bespoke JSON error shape).
 */
final class OcrSuggestApiTest extends ApiTestCase
{
    private static string $adminToken = '';
    private static int    $orgId      = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId      = self::ensureOrg('test-org');
        self::$adminToken = self::issueToken('admin', self::$orgId, userId: 190);
    }

    public function test_unknown_document_returns_404_problem_details(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents/00000000000000000000000000/ocr-suggest', self::$adminToken),
        );

        $this->assertSame(404, $resp->getStatusCode());
        $this->assertStringContainsString('application/problem+json', $resp->getHeaderLine('Content-Type'));
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertStringContainsString('document-not-found', (string) $body['type']);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents/00000000000000000000000000/ocr-suggest'),
        );
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function test_ocr_failure_returns_422_problem_details_when_tesseract_unavailable(): void
    {
        // Upload a real document, then request OCR. In the test environment Tesseract
        // is typically not installed, so the extractor throws OcrException → 422
        // Problem Details. If Tesseract *is* present, the call succeeds (200); either
        // way the response must be a documented shape, never a 500.
        $id   = $this->uploadDoc($this->handler(), self::$adminToken, 'OcrProbe', '2026-01-01', '1000');
        $resp = $this->handler()->handle(
            $this->request('GET', "/admin/vault/documents/{$id}/ocr-suggest", self::$adminToken),
        );

        $status = $resp->getStatusCode();
        $this->assertContains($status, [200, 422], 'OCR endpoint must return 200 or a 422 Problem Details, never 500');

        if ($status === 422) {
            $this->assertStringContainsString('application/problem+json', $resp->getHeaderLine('Content-Type'));
            $body = json_decode((string) $resp->getBody(), true);
            $this->assertStringContainsString('ocr-failed', (string) $body['type']);
        }
    }
}
