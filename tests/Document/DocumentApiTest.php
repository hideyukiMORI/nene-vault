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

    private function request(string $method, string $uri, bool $auth = true): ServerRequestInterface
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

        return $creator->fromArrays(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            headers: $headers,
        );
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
