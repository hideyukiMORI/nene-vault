<?php

declare(strict_types=1);

namespace NeneVault\Tests\Document;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use NeneVault\Http\RuntimeContainerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DocumentApiTest extends TestCase
{
    private static ?ContainerInterface $container = null;
    private static int $orgId = 0;
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
        $row = $stmt->fetch();
        self::$orgId = (int) $row['id'];

        $issuer = self::$container->get(TokenIssuerInterface::class);
        assert($issuer instanceof TokenIssuerInterface);
        self::$token = $issuer->issue([
            'sub' => 'admin@example.com',
            'user_id' => 1,
            'role' => 'admin',
            'org_id' => self::$orgId,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);
    }

    public function test_upload_then_get_document(): void
    {
        $handler = $this->handler();

        $tmp = $this->makeTempFile("%PDF-1.4\nfake pdf content for test\n");
        $uploadRequest = $this->request('POST', '/admin/vault/documents')
            ->withUploadedFiles(['file' => $this->uploadedFile($tmp, 'invoice.pdf', 'application/pdf')])
            ->withParsedBody([
                'counterparty_name' => 'Sample Inc.',
                'category' => 'invoice_received',
                'transaction_date' => '2026-03-31',
                'amount_cents' => '110000',
                'tags' => 'q1,important',
            ]);

        $uploadResponse = $handler->handle($uploadRequest);

        $this->assertSame(201, $uploadResponse->getStatusCode(), (string) $uploadResponse->getBody());
        $uploaded = json_decode((string) $uploadResponse->getBody(), true);
        $this->assertIsArray($uploaded);
        $this->assertSame('active', $uploaded['status']);
        $this->assertSame('Sample Inc.', $uploaded['counterparty_name']);
        $this->assertSame('invoice_received', $uploaded['category']);
        $this->assertSame(110000, $uploaded['amount_cents']);
        $this->assertSame('2026-03-31', $uploaded['transaction_date']);
        $this->assertSame(1, $uploaded['version_number']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $uploaded['file_sha256']);
        $this->assertSame('2036-03-31', $uploaded['retention_expires_at']);
        $this->assertArrayNotHasKey('file_path', $uploaded);
        $this->assertContains('q1', $uploaded['tags']);

        $documentId = $uploaded['id'];
        $this->assertIsString($documentId);

        $getResponse = $handler->handle($this->request('GET', '/admin/vault/documents/' . $documentId));

        $this->assertSame(200, $getResponse->getStatusCode(), (string) $getResponse->getBody());
        $fetched = json_decode((string) $getResponse->getBody(), true);
        $this->assertSame($documentId, $fetched['id']);
        $this->assertSame('Sample Inc.', $fetched['counterparty_name']);

        @unlink($tmp);
    }

    public function test_upload_rejects_disallowed_mime(): void
    {
        $handler = $this->handler();
        $tmp = $this->makeTempFile('plain text');

        $request = $this->request('POST', '/admin/vault/documents')
            ->withUploadedFiles(['file' => $this->uploadedFile($tmp, 'note.txt', 'text/plain')])
            ->withParsedBody(['counterparty_name' => 'X', 'category' => 'other']);

        $response = $handler->handle($request);

        $this->assertSame(415, $response->getStatusCode());
        @unlink($tmp);
    }

    public function test_search_by_counterparty_and_date_combination(): void
    {
        $handler = $this->handler();

        // Upload two documents with distinct counterparties and dates
        $this->upload(handler: $handler, counterparty: 'Alpha Trading', date: '2026-02-10', amount: '50000');
        $this->upload(handler: $handler, counterparty: 'Beta Supplies', date: '2026-08-20', amount: '75000');

        // Two-field combination: counterparty partial + date range
        $response = $handler->handle($this->request(
            'GET',
            '/admin/vault/documents?counterparty_name=Alpha&transaction_date_from=2026-01-01&transaction_date_to=2026-06-30',
        ));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertGreaterThanOrEqual(1, $body['total']);
        foreach ($body['items'] as $item) {
            $this->assertStringContainsString('Alpha', $item['counterparty_name']);
        }
    }

    public function test_search_by_amount_range(): void
    {
        $handler = $this->handler();
        $this->upload(handler: $handler, counterparty: 'Gamma Corp', date: '2026-03-15', amount: '999999');

        $response = $handler->handle($this->request(
            'GET',
            '/admin/vault/documents?amount_min_cents=999999&amount_max_cents=999999&counterparty_name=Gamma',
        ));

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThanOrEqual(1, $body['total']);
        foreach ($body['items'] as $item) {
            $this->assertSame(999999, $item['amount_cents']);
        }
    }

    public function test_metadata_edit_then_void_then_restore_with_history(): void
    {
        $handler = $this->handler();
        $id = $this->upload(handler: $handler, counterparty: 'Delta Ltd', date: '2026-04-01', amount: '12345');

        // 1. Edit metadata — counterparty + amount change
        $patch = $this->request('PATCH', '/admin/vault/documents/' . $id . '/metadata', json: [
            'counterparty_name' => 'Delta Limited',
            'category' => 'invoice_received',
            'transaction_date' => '2026-04-02',
            'amount_cents' => '54321',
        ]);
        $patchResponse = $handler->handle($patch);
        $this->assertSame(200, $patchResponse->getStatusCode(), (string) $patchResponse->getBody());
        $patched = json_decode((string) $patchResponse->getBody(), true);
        $this->assertSame('Delta Limited', $patched['counterparty_name']);
        $this->assertSame(54321, $patched['amount_cents']);
        $this->assertTrue($patched['is_metadata_confirmed']);

        // 2. Void with reason
        $void = $this->request('POST', '/admin/vault/documents/' . $id . '/void', json: ['void_reason' => 'Duplicate entry']);
        $voidResponse = $handler->handle($void);
        $this->assertSame(200, $voidResponse->getStatusCode(), (string) $voidResponse->getBody());
        $voided = json_decode((string) $voidResponse->getBody(), true);
        $this->assertSame('voided', $voided['status']);
        $this->assertSame('Duplicate entry', $voided['void_reason']);

        // 3. Voided excluded from default search
        $search = $handler->handle($this->request('GET', '/admin/vault/documents?counterparty_name=Delta+Limited'));
        $searchBody = json_decode((string) $search->getBody(), true);
        foreach ($searchBody['items'] as $item) {
            $this->assertNotSame($id, $item['id'], 'Voided document must not appear in default search');
        }

        // 4. Voided included when include_voided=true
        $searchVoided = $handler->handle($this->request('GET', '/admin/vault/documents?counterparty_name=Delta+Limited&include_voided=true'));
        $searchVoidedBody = json_decode((string) $searchVoided->getBody(), true);
        $ids = array_column($searchVoidedBody['items'], 'id');
        $this->assertContains($id, $ids);

        // 5. Restore
        $restore = $handler->handle($this->request('POST', '/admin/vault/documents/' . $id . '/restore'));
        $this->assertSame(200, $restore->getStatusCode());
        $restored = json_decode((string) $restore->getBody(), true);
        $this->assertSame('active', $restored['status']);

        // 6. History contains version + audit events (uploaded, metadata_changed, voided, restored)
        $history = $handler->handle($this->request('GET', '/admin/vault/documents/' . $id . '/history'));
        $this->assertSame(200, $history->getStatusCode());
        $historyBody = json_decode((string) $history->getBody(), true);
        $this->assertCount(1, $historyBody['versions']);
        $actions = array_column($historyBody['audit_events'], 'action');
        $this->assertContains('document.uploaded', $actions);
        $this->assertContains('document.metadata_changed', $actions);
        $this->assertContains('document.voided', $actions);
        $this->assertContains('document.restored', $actions);

        // metadata_changed event must carry before/after
        foreach ($historyBody['audit_events'] as $event) {
            if ($event['action'] === 'document.metadata_changed') {
                $this->assertSame('Delta Ltd', $event['before_json']['counterparty_name']);
                $this->assertSame('Delta Limited', $event['after_json']['counterparty_name']);
            }
        }
    }

    public function test_void_requires_reason(): void
    {
        $handler = $this->handler();
        $id = $this->upload(handler: $handler, counterparty: 'Epsilon', date: '2026-05-01', amount: '100');

        // Send a JSON object without void_reason → validation 422
        $response = $handler->handle(
            $this->request('POST', '/admin/vault/documents/' . $id . '/void', json: ['void_note' => 'no reason given']),
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_get_unknown_document_returns_404(): void
    {
        $response = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents/00000000000000000000000000'),
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_upload_without_token_returns_401(): void
    {
        $response = $this->handler()->handle(
            $this->request('POST', '/admin/vault/documents', auth: false),
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    // ── helpers ──

    private function handler(): RequestHandlerInterface
    {
        $container = self::$container;
        assert($container !== null);
        $handler = $container->get(RequestHandlerInterface::class);
        assert($handler instanceof RequestHandlerInterface);

        return $handler;
    }

    /** @param array<string, mixed>|null $json */
    private function request(string $method, string $uri, bool $auth = true, ?array $json = null): ServerRequestInterface
    {
        $container = self::$container;
        assert($container !== null);
        $psr17 = $container->get(Psr17Factory::class);
        assert($psr17 instanceof Psr17Factory);

        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);

        $headers = ['Host' => 'localhost'];
        if ($auth) {
            $headers['Authorization'] = 'Bearer ' . self::$token;
        }

        $queryParams = [];
        $queryString = parse_url($uri, PHP_URL_QUERY);
        if (is_string($queryString)) {
            parse_str($queryString, $queryParams);
        }

        $body = null;
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
            $encoded = json_encode($json, JSON_THROW_ON_ERROR);
            $body = $psr17->createStream($encoded);
        }

        return $creator->fromArrays(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            headers: $headers,
            get: $queryParams,
            body: $body,
        );
    }

    /** Uploads a document and returns its id. */
    private function upload(RequestHandlerInterface $handler, string $counterparty, string $date, string $amount): string
    {
        $tmp = $this->makeTempFile("%PDF-1.4\n{$counterparty} {$date} {$amount}\n");
        $request = $this->request('POST', '/admin/vault/documents')
            ->withUploadedFiles(['file' => $this->uploadedFile($tmp, 'doc.pdf', 'application/pdf')])
            ->withParsedBody([
                'counterparty_name' => $counterparty,
                'category' => 'invoice_received',
                'transaction_date' => $date,
                'amount_cents' => $amount,
            ]);

        $response = $handler->handle($request);
        assert($response->getStatusCode() === 201, (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        @unlink($tmp);

        return (string) $body['id'];
    }

    private function makeTempFile(string $content): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vault_test_');
        assert($tmp !== false);
        file_put_contents($tmp, $content);

        return $tmp;
    }

    private function uploadedFile(string $path, string $filename, string $mediaType): \Psr\Http\Message\UploadedFileInterface
    {
        $container = self::$container;
        assert($container !== null);
        $psr17 = $container->get(Psr17Factory::class);
        assert($psr17 instanceof Psr17Factory);

        return $psr17->createUploadedFile(
            $psr17->createStreamFromFile($path),
            (int) filesize($path),
            UPLOAD_ERR_OK,
            $filename,
            $mediaType,
        );
    }
}
