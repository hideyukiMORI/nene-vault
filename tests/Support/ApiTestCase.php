<?php

declare(strict_types=1);

namespace NeneVault\Tests\Support;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use NeneVault\Http\RuntimeContainerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Base class for HTTP-level API integration tests.
 *
 * Each concrete test class must call parent::setUpBeforeClass() and then
 * call self::bootContainer() once to initialise the shared container.
 */
abstract class ApiTestCase extends TestCase
{
    protected static ?ContainerInterface $container = null;

    // ---------------------------------------------------------------------------
    // Bootstrap
    // ---------------------------------------------------------------------------

    protected static function bootContainer(): ContainerInterface
    {
        if (self::$container === null) {
            self::$container = (new RuntimeContainerFactory(dirname(__DIR__, 2)))->create();
        }

        return self::$container;
    }

    // ---------------------------------------------------------------------------
    // Token factory
    // ---------------------------------------------------------------------------

    /** @param array<string, mixed> $extra */
    protected static function issueToken(
        string $role,
        int $orgId,
        int $userId = 1,
        array $extra = [],
    ): string {
        $container = self::$container;
        assert($container !== null);
        $issuer = $container->get(TokenIssuerInterface::class);
        assert($issuer instanceof TokenIssuerInterface);

        return $issuer->issue(array_merge([
            'sub'     => "{$role}@example.com",
            'user_id' => $userId,
            'role'    => $role,
            'org_id'  => $orgId,
            'iat'     => time(),
            'exp'     => time() + 3600,
        ], $extra));
    }

    protected static function issueSuperadminToken(int $userId = 9999): string
    {
        $container = self::$container;
        assert($container !== null);
        $issuer = $container->get(TokenIssuerInterface::class);
        assert($issuer instanceof TokenIssuerInterface);

        return $issuer->issue([
            'sub'     => 'superadmin@example.com',
            'user_id' => $userId,
            'role'    => 'superadmin',
            'org_id'  => null,
            'iat'     => time(),
            'exp'     => time() + 3600,
        ]);
    }

    // ---------------------------------------------------------------------------
    // DB helpers
    // ---------------------------------------------------------------------------

    protected static function pdo(): \PDO
    {
        $container = self::$container;
        assert($container !== null);
        $factory = $container->get(DatabaseConnectionFactoryInterface::class);
        assert($factory instanceof DatabaseConnectionFactoryInterface);

        return $factory->create();
    }

    protected static function ensureOrg(string $slug, string $name = 'Test Org'): int
    {
        $pdo = self::pdo();
        $pdo->exec("INSERT OR IGNORE INTO organizations
            (name, slug, plan, is_active, created_at, updated_at)
            VALUES ('{$name}', '{$slug}', 'free', 1, datetime('now'), datetime('now'))");
        $stmt = $pdo->query("SELECT id FROM organizations WHERE slug = '{$slug}'");
        assert($stmt !== false);

        return (int) $stmt->fetch()['id'];
    }

    // ---------------------------------------------------------------------------
    // Request factory
    // ---------------------------------------------------------------------------

    protected function handler(): RequestHandlerInterface
    {
        $container = self::$container;
        assert($container !== null);
        $h = $container->get(RequestHandlerInterface::class);
        assert($h instanceof RequestHandlerInterface);

        return $h;
    }

    protected function psr17(): Psr17Factory
    {
        $container = self::$container;
        assert($container !== null);
        $psr17 = $container->get(Psr17Factory::class);
        assert($psr17 instanceof Psr17Factory);

        return $psr17;
    }

    /** @param array<string, mixed>|null $json */
    protected function request(
        string $method,
        string $uri,
        ?string $token = null,
        ?array $json = null,
    ): ServerRequestInterface {
        $psr17   = $this->psr17();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);

        $headers = ['Host' => 'localhost'];
        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $queryParams = [];
        $qs          = parse_url($uri, PHP_URL_QUERY);
        if (is_string($qs)) {
            parse_str($qs, $queryParams);
        }

        $body = null;
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
            $body = $psr17->createStream(json_encode($json, JSON_THROW_ON_ERROR));
        }

        return $creator->fromArrays(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            headers: $headers,
            get: $queryParams,
            body: $body,
        );
    }

    // ---------------------------------------------------------------------------
    // File upload helper
    // ---------------------------------------------------------------------------

    /** Uploads a PDF and returns the document id. Random bytes prevent SHA-256 reuse. */
    protected function uploadDoc(
        RequestHandlerInterface $handler,
        string $token,
        string $counterparty,
        string $date  = '2026-01-01',
        string $amount = '10000',
        string $category = 'invoice_received',
    ): string {
        $psr17 = $this->psr17();

        $tmp = tempnam(sys_get_temp_dir(), 'vault_t_');
        assert($tmp !== false);
        file_put_contents(
            $tmp,
            "%PDF-1.4\n{$counterparty} {$date} {$amount}\n" . bin2hex(random_bytes(8)),
        );

        $file = $psr17->createUploadedFile(
            $psr17->createStreamFromFile($tmp),
            (int) filesize($tmp),
            UPLOAD_ERR_OK,
            'doc.pdf',
            'application/pdf',
        );

        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $req = $creator->fromArrays(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/vault/documents'],
            headers: ['Host' => 'localhost', 'Authorization' => "Bearer {$token}"],
        )
            ->withUploadedFiles(['file' => $file])
            ->withParsedBody([
                'counterparty_name' => $counterparty,
                'category'          => $category,
                'transaction_date'  => $date,
                'amount_cents'      => $amount,
            ]);

        $resp = $handler->handle($req);
        assert($resp->getStatusCode() === 201, 'uploadDoc failed: ' . (string) $resp->getBody());
        @unlink($tmp);
        $body = json_decode((string) $resp->getBody(), true);

        return (string) $body['id'];
    }

    protected function makeTempPdf(string $seed = ''): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vault_t_');
        assert($tmp !== false);
        file_put_contents($tmp, "%PDF-1.4\n{$seed}\n" . bin2hex(random_bytes(8)));

        return $tmp;
    }

    protected function uploadedFile(string $path, string $filename = 'doc.pdf', string $mime = 'application/pdf'): UploadedFileInterface
    {
        $psr17 = $this->psr17();

        return $psr17->createUploadedFile(
            $psr17->createStreamFromFile($path),
            (int) filesize($path),
            UPLOAD_ERR_OK,
            $filename,
            $mime,
        );
    }
}
