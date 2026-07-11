<?php

declare(strict_types=1);

namespace NeneVault\Tests\Export;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use NeneVault\Http\RuntimeContainerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ExportApiTest extends TestCase
{
    private static ?ContainerInterface $container = null;
    private static string $token = '';

    public static function setUpBeforeClass(): void
    {
        self::$container = (new RuntimeContainerFactory(dirname(__DIR__, 2)))->create();

        $conn = self::$container->get(DatabaseConnectionFactoryInterface::class);
        assert($conn instanceof DatabaseConnectionFactoryInterface);
        $pdo = $conn->create();
        $pdo->exec("INSERT OR IGNORE INTO organizations
            (name, slug, plan, is_active, created_at, updated_at)
            VALUES ('Test Org', 'test-org', 'free', 1, datetime('now'), datetime('now'))");
        $stmt = $pdo->query("SELECT id FROM organizations WHERE slug = 'test-org'");
        assert($stmt !== false);
        $orgId = (int) $stmt->fetch()['id'];

        $issuer = self::$container->get(TokenIssuerInterface::class);
        assert($issuer instanceof TokenIssuerInterface);
        self::$token = $issuer->issue([
            'sub' => 1,
            'role' => 'admin',
            'org' => $orgId,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);
    }

    public function test_export_returns_manifest_csv(): void
    {
        $handler = $this->handler();

        // Upload a document to export
        $this->upload($handler, 'Manifest Vendor', '2026-07-01', '33000');

        $response = $handler->handle($this->jsonRequest('POST', '/admin/vault/export', [
            'transaction_date_from' => '2026-01-01',
            'transaction_date_to' => '2026-12-31',
            'counterparty_name' => 'Manifest Vendor',
        ]));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertStringContainsString('text/csv', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment', $response->getHeaderLine('Content-Disposition'));

        $csv = (string) $response->getBody();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        // Header columns (compliance §7/§10)
        $this->assertStringContainsString('document_id,version,transaction_date,amount_cents,counterparty_name,category,file_sha256,uploaded_at,voided_at', $lines[0]);
        // At least one data row with our vendor and amount
        $dataJoined = implode("\n", array_slice($lines, 1));
        $this->assertStringContainsString('Manifest Vendor', $dataJoined);
        $this->assertStringContainsString('33000', $dataJoined);
    }

    public function test_export_zip_contains_manifest_and_file(): void
    {
        $handler = $this->handler();
        $marker = 'ZipVendor-' . bin2hex(random_bytes(4));

        $this->upload($handler, $marker, '2026-09-01', '42000');

        $response = $handler->handle($this->jsonRequest('POST', '/admin/vault/export', [
            'format' => 'zip',
            'counterparty_name' => $marker,
        ]));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertStringContainsString('application/zip', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('.zip', $response->getHeaderLine('Content-Disposition'));

        // Write ZIP bytes to a temp file and inspect
        $zipBytes = (string) $response->getBody();
        $tmpZip = tempnam(sys_get_temp_dir(), 'vault_test_zip_');
        assert($tmpZip !== false);
        file_put_contents($tmpZip, $zipBytes);

        $zip = new \ZipArchive();
        $opened = $zip->open($tmpZip);
        $this->assertSame(true, $opened, 'ZIP should be openable');

        // manifest.csv must exist
        $manifestIndex = $zip->locateName('manifest.csv');
        $this->assertNotFalse($manifestIndex, 'manifest.csv must be present in ZIP');

        $csvContent = $zip->getFromIndex((int) $manifestIndex);
        $this->assertIsString($csvContent);
        $this->assertStringContainsString($marker, $csvContent);
        $this->assertStringContainsString('42000', $csvContent);

        // At least one document file under files/
        $fileFound = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && str_starts_with($name, 'files/')) {
                $fileFound = true;
                break;
            }
        }
        $this->assertTrue($fileFound, 'ZIP must contain at least one file under files/');

        $zip->close();
        @unlink($tmpZip);
    }

    public function test_export_csv_neutralizes_formula_injection_and_has_bom(): void
    {
        $handler = $this->handler();

        // Attacker-controlled counterparty text that would be a live formula when
        // the CSV is opened in Excel / LibreOffice / Google Sheets.
        $payload = '=cmd|/c calc!A1-' . bin2hex(random_bytes(4));

        $this->upload($handler, $payload, '2026-11-01', '15000');

        $response = $handler->handle($this->jsonRequest('POST', '/admin/vault/export', [
            'transaction_date_from' => '2026-11-01',
            'transaction_date_to' => '2026-11-30',
            'counterparty_name' => $payload,
        ]));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $csv = (string) $response->getBody();

        // Framework default: UTF-8 BOM (Excel-JP safe).
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        // The raw formula must never appear as a live cell; it is neutralised with
        // a leading single quote so the spreadsheet renders it as text.
        $this->assertStringNotContainsString(",{$payload},", $csv);
        $this->assertStringContainsString("'" . $payload, $csv);

        // Round-trip: parsing the neutralised CSV must recover the quoted value
        // exactly, and amount_cents must stay a plain numeric cell (not neutralised).
        $body = substr($csv, 3); // strip BOM
        $lines = array_values(array_filter(explode("\n", trim($body))));
        $header = str_getcsv($lines[0], ',', '"', '');
        $counterpartyCol = array_search('counterparty_name', $header, true);
        $amountCol = array_search('amount_cents', $header, true);
        $this->assertNotFalse($counterpartyCol);

        $matched = null;
        foreach (array_slice($lines, 1) as $line) {
            $cells = str_getcsv($line, ',', '"', '');
            if (($cells[$counterpartyCol] ?? null) === "'" . $payload) {
                $matched = $cells;
                break;
            }
        }
        $this->assertNotNull($matched, 'neutralised counterparty row must round-trip');
        $this->assertSame('15000', $matched[$amountCol], 'numeric amount must stay un-neutralised');
    }

    public function test_export_requires_auth(): void
    {
        $response = $this->handler()->handle($this->jsonRequest('POST', '/admin/vault/export', [], auth: false));
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── helpers ──

    private function handler(): RequestHandlerInterface
    {
        $container = self::$container;
        assert($container !== null);
        $h = $container->get(RequestHandlerInterface::class);
        assert($h instanceof RequestHandlerInterface);

        return $h;
    }

    /** @param array<string, mixed> $json */
    private function jsonRequest(string $method, string $uri, array $json, bool $auth = true): ServerRequestInterface
    {
        $psr17 = $this->psr17();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);

        $headers = ['Host' => 'localhost', 'Content-Type' => 'application/json'];
        if ($auth) {
            $headers['Authorization'] = 'Bearer ' . self::$token;
        }

        return $creator->fromArrays(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            headers: $headers,
            body: $psr17->createStream(json_encode($json, JSON_THROW_ON_ERROR)),
        );
    }

    private function upload(RequestHandlerInterface $handler, string $counterparty, string $date, string $amount): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vault_exp_');
        assert($tmp !== false);
        file_put_contents($tmp, "%PDF-1.4\n{$counterparty}\n");

        $psr17 = $this->psr17();
        $file = $psr17->createUploadedFile(
            $psr17->createStreamFromFile($tmp),
            (int) filesize($tmp),
            UPLOAD_ERR_OK,
            'doc.pdf',
            'application/pdf',
        );

        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $request = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: ['Host' => 'localhost', 'Authorization' => 'Bearer ' . self::$token],
        )
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody([
                'counterparty_name' => $counterparty,
                'category' => 'invoice_received',
                'transaction_date' => $date,
                'amount_cents' => $amount,
            ]);

        $response = $handler->handle($request);
        assert($response->getStatusCode() === 201, (string) $response->getBody());
        @unlink($tmp);
    }

    private function psr17(): Psr17Factory
    {
        $container = self::$container;
        assert($container !== null);
        $psr17 = $container->get(Psr17Factory::class);
        assert($psr17 instanceof Psr17Factory);

        return $psr17;
    }
}
