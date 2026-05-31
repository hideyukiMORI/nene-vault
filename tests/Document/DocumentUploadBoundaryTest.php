<?php

declare(strict_types=1);

namespace NeneVault\Tests\Document;

use NeneVault\Tests\Support\ApiTestCase;
use Nyholm\Psr7Server\ServerRequestCreator;

/**
 * Upload boundary tests: MIME validation, file size, duplicate detection,
 * date_uncertain flag, and multipart edge cases.
 */
final class DocumentUploadBoundaryTest extends ApiTestCase
{
    private static string $adminToken  = '';
    private static string $memberToken = '';
    private static int    $orgId       = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId       = self::ensureOrg('test-org');
        self::$adminToken  = self::issueToken('admin', self::$orgId, userId: 100);
        self::$memberToken = self::issueToken('member', self::$orgId, userId: 101);
    }

    // ── MIME type ────────────────────────────────────────────────────────────

    public function test_pdf_accepted(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$adminToken, 'PDF Co', '2026-01-01', '1000');
        $this->assertNotEmpty($id);
    }

    public function test_jpeg_accepted(): void
    {
        $tmp = $this->makeTempPdf('jpeg-content');
        $req = $this->buildUploadRequest(self::$adminToken, $tmp, 'receipt.jpg', 'image/jpeg', 'JPEG Vendor', '2026-01-01', '500');
        $resp = $this->handler()->handle($req);
        $this->assertSame(201, $resp->getStatusCode(), (string) $resp->getBody());
        @unlink($tmp);
    }

    public function test_png_accepted(): void
    {
        $tmp = $this->makeTempPdf('png-content');
        $req = $this->buildUploadRequest(self::$adminToken, $tmp, 'scan.png', 'image/png', 'PNG Vendor', '2026-01-01', '500');
        $resp = $this->handler()->handle($req);
        $this->assertSame(201, $resp->getStatusCode(), (string) $resp->getBody());
        @unlink($tmp);
    }

    public function test_text_plain_rejected_415(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vault_t_');
        assert($tmp !== false);
        file_put_contents($tmp, 'not a pdf');

        $req = $this->buildUploadRequest(self::$adminToken, $tmp, 'note.txt', 'text/plain', 'Vendor', '2026-01-01', '0');
        $resp = $this->handler()->handle($req);
        $this->assertSame(415, $resp->getStatusCode());
        @unlink($tmp);
    }

    public function test_docx_rejected_415(): void
    {
        $tmp = $this->makeTempPdf('docx-mock');
        $req = $this->buildUploadRequest(self::$adminToken, $tmp, 'contract.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Vendor', '2026-01-01', '0');
        $resp = $this->handler()->handle($req);
        $this->assertSame(415, $resp->getStatusCode());
        @unlink($tmp);
    }

    // ── Metadata fields ───────────────────────────────────────────────────────

    public function test_upload_without_transaction_date_sets_date_uncertain(): void
    {
        $handler = $this->handler();
        $psr17   = $this->psr17();
        $tmp     = $this->makeTempPdf('no-date');

        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $req = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$adminToken],
        )
            ->withUploadedFiles(['file' => $this->uploadedFile($tmp, 'doc.pdf', 'application/pdf')])
            ->withParsedBody([
                'counterparty_name' => 'NoDate Vendor',
                'category'          => 'receipt',
                // no transaction_date
            ]);

        $resp = $handler->handle($req);
        $this->assertSame(201, $resp->getStatusCode(), (string) $resp->getBody());

        $body = json_decode((string) $resp->getBody(), true);
        $this->assertTrue($body['date_uncertain'], 'date_uncertain must be true when transaction_date is omitted');
        $this->assertNotNull($body['retention_expires_at'], 'retention_expires_at must be set even when date is uncertain');
        @unlink($tmp);
    }

    public function test_upload_null_amount_cents_accepted(): void
    {
        $psr17   = $this->psr17();
        $tmp     = $this->makeTempPdf('null-amount');
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);

        $req = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$adminToken],
        )
            ->withUploadedFiles(['file' => $this->uploadedFile($tmp)])
            ->withParsedBody([
                'counterparty_name' => 'Null Amount Vendor',
                'category'          => 'contract',
                'transaction_date'  => '2026-03-01',
                // no amount_cents
            ]);

        $resp = $this->handler()->handle($req);
        $this->assertSame(201, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertNull($body['amount_cents']);
        @unlink($tmp);
    }

    public function test_upload_stores_tags(): void
    {
        $psr17   = $this->psr17();
        $tmp     = $this->makeTempPdf('tagged');
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);

        $req = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$adminToken],
        )
            ->withUploadedFiles(['file' => $this->uploadedFile($tmp)])
            ->withParsedBody([
                'counterparty_name' => 'Tagged Vendor',
                'category'          => 'invoice_received',
                'transaction_date'  => '2026-04-01',
                'tags'              => 'q2,important,audit',
            ]);

        $resp = $this->handler()->handle($req);
        $this->assertSame(201, $resp->getStatusCode());
        $tags = json_decode((string) $resp->getBody(), true)['tags'];
        $this->assertContains('q2', $tags);
        $this->assertContains('important', $tags);
        $this->assertContains('audit', $tags);
        @unlink($tmp);
    }

    public function test_upload_missing_counterparty_returns_422(): void
    {
        $psr17   = $this->psr17();
        $tmp     = $this->makeTempPdf('no-cp');
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);

        $req = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$adminToken],
        )
            ->withUploadedFiles(['file' => $this->uploadedFile($tmp)])
            ->withParsedBody(['category' => 'invoice_received']);

        $resp = $this->handler()->handle($req);
        $this->assertSame(422, $resp->getStatusCode());
        @unlink($tmp);
    }

    // ── Duplicate detection ───────────────────────────────────────────────────

    public function test_duplicate_sha256_rejected_unless_confirmed(): void
    {
        $handler = $this->handler();
        $tmp     = $this->makeTempPdf('dup-test-' . uniqid());
        $content = (string) file_get_contents($tmp);

        // First upload — succeeds
        $req1 = $this->buildUploadRequest(self::$adminToken, $tmp, 'doc.pdf', 'application/pdf', 'Dup Vendor', '2026-01-01', '100');
        $resp1 = $handler->handle($req1);
        $this->assertSame(201, $resp1->getStatusCode());

        // Rebuild tmp with same content for second upload
        $tmp2 = tempnam(sys_get_temp_dir(), 'vault_dup_');
        assert($tmp2 !== false);
        file_put_contents($tmp2, $content);

        $req2 = $this->buildUploadRequest(self::$adminToken, $tmp2, 'doc.pdf', 'application/pdf', 'Dup Vendor', '2026-01-01', '100');
        $resp2 = $handler->handle($req2);
        $this->assertSame(409, $resp2->getStatusCode(), 'Duplicate SHA-256 must return 409');

        @unlink($tmp);
        @unlink($tmp2);
    }

    public function test_duplicate_accepted_with_confirm_flag(): void
    {
        $handler = $this->handler();
        $psr17   = $this->psr17();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $content = "%PDF-1.4\nconfirm-dup-" . bin2hex(random_bytes(4)) . "\n";

        foreach ([false, true] as $second) {
            $tmp = tempnam(sys_get_temp_dir(), 'vault_cd_');
            assert($tmp !== false);
            file_put_contents($tmp, $content);

            $body = [
                'counterparty_name' => 'Confirm Dup',
                'category'          => 'invoice_received',
                'transaction_date'  => '2026-02-01',
            ];

            if ($second) {
                $body['confirm_duplicate'] = '1';
            }

            $req = $creator->fromArrays(
                server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
                headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$adminToken],
            )
                ->withUploadedFiles(['file' => $this->uploadedFile($tmp)])
                ->withParsedBody($body);

            $resp = $handler->handle($req);

            if (!$second) {
                $this->assertSame(201, $resp->getStatusCode(), 'First upload must succeed');
            } else {
                $this->assertSame(201, $resp->getStatusCode(), 'Duplicate with confirm_duplicate=1 must succeed');
            }

            @unlink($tmp);
        }
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_unauthenticated_upload_returns_401(): void
    {
        $tmp = $this->makeTempPdf('unauth');
        $req = $this->buildUploadRequest(null, $tmp, 'doc.pdf', 'application/pdf', 'Vendor', '2026-01-01', '0');
        $resp = $this->handler()->handle($req);
        $this->assertSame(401, $resp->getStatusCode());
        @unlink($tmp);
    }

    public function test_member_can_upload(): void
    {
        $id = $this->uploadDoc($this->handler(), self::$memberToken, 'Member Upload', '2026-01-01', '1000');
        $this->assertNotEmpty($id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @param array<string, string> $bodyFields */
    private function buildUploadRequest(
        ?string $token,
        string $tmpPath,
        string $filename,
        string $mime,
        string $counterparty,
        string $date,
        string $amount,
        array $bodyFields = [],
    ): \Psr\Http\Message\ServerRequestInterface {
        $psr17   = $this->psr17();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);

        $headers = ['Host' => 'localhost'];
        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: $headers,
        )
            ->withUploadedFiles(['file' => $this->uploadedFile($tmpPath, $filename, $mime)])
            ->withParsedBody(array_merge([
                'counterparty_name' => $counterparty,
                'category'          => 'invoice_received',
                'transaction_date'  => $date,
                'amount_cents'      => $amount,
            ], $bodyFields));
    }
}
