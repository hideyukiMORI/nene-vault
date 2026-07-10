<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use Nene2\Demo\CountingDemoCapacityGuard;
use Nene2\Demo\DemoConfig;
use Nene2\Demo\StartDisposableDemoHandler;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Routing\Router;
use NeneVault\Demo\DemoBrowserErrorPage;
use NeneVault\Demo\DemoOrgProvisioner;
use NeneVault\Demo\DemoProvisionRegistry;
use NeneVault\Demo\DemoSessionSeater;
use NeneVault\Demo\DemoTemplate;
use NeneVault\Demo\DisposableDemoSeeder;
use NeneVault\Demo\FileRateLimitStorage;
use NeneVault\Tests\Support\ApiTestCase;
use NeneVault\Tests\Support\FixedClock;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The demo start gates (#141) wired with Vault's concretes and tight limits:
 * fail-close 404 while demo mode is off, per-IP throttle 429 (JSON for API
 * clients, the branded HTML card for browsers, Retry-After preserved on
 * both), and the instance-wide org ceiling 503.
 */
final class StartDemoGuardsTest extends ApiTestCase
{
    private string $rateLimitDir;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
    }

    protected function setUp(): void
    {
        $this->rateLimitDir = sys_get_temp_dir() . '/nene-vault-demo-guards-' . bin2hex(random_bytes(6));
        mkdir($this->rateLimitDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->rateLimitDir . '/rate-limits/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->rateLimitDir . '/rate-limits');
        @rmdir($this->rateLimitDir);
    }

    private function startHandler(DemoConfig $config, int $throttleLimit): StartDisposableDemoHandler
    {
        $container = self::$container;
        assert($container !== null);

        $psr17 = $this->psr17();
        $problemDetails = new ProblemDetailsResponseFactory($psr17, $psr17, 'https://nene-vault.dev/problems/');
        $clock = new FixedClock();

        $registry = new DemoProvisionRegistry();
        $provisioner = $container->get(DemoOrgProvisioner::class);
        assert($provisioner instanceof DemoOrgProvisioner);
        $seeder = $container->get(DisposableDemoSeeder::class);
        assert($seeder instanceof DisposableDemoSeeder);
        $seater = $container->get(DemoSessionSeater::class);
        assert($seater instanceof DemoSessionSeater);

        $pdo = self::pdo();
        $guard = new CountingDemoCapacityGuard(
            demoOrgCount: static function () use ($pdo, $config): int {
                $like = str_replace(['|', '%', '_'], ['||', '|%', '|_'], $config->slugPrefix) . '%';
                $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM organizations WHERE slug LIKE ? ESCAPE '|'");
                $stmt->execute([$like]);

                return (int) $stmt->fetch()['n'];
            },
            config: $config,
            throttleStorage: new FileRateLimitStorage($this->rateLimitDir, $clock),
            throttleLimit: $throttleLimit,
            throttleWindowSeconds: 3600,
            clock: $clock,
        );

        return new StartDisposableDemoHandler(
            config: $config,
            capacityGuard: $guard,
            provisioner: $provisioner,
            seeder: $seeder,
            seater: $seater,
            problemDetails: $problemDetails,
            templateKeyClass: DemoTemplate::class,
            errorPageRenderer: new DemoBrowserErrorPage($psr17, $throttleLimit, 3600),
        );
    }

    private function demoRequest(string $accept = 'application/json'): ServerRequestInterface
    {
        return $this->psr17()
            ->createServerRequest('GET', '/demo/standard', ['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeader('Accept', $accept)
            ->withAttribute(Router::PARAMETERS_ATTRIBUTE, ['template' => 'standard']);
    }

    public function test_demo_mode_off_answers_a_plain_404(): void
    {
        $response = $this->startHandler(new DemoConfig(demoMode: false), throttleLimit: 5)
            ->handle($this->demoRequest());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function test_throttle_answers_429_json_for_api_clients(): void
    {
        // Limit 0: the very first hit exceeds the window — no org is created.
        $handler = $this->startHandler(new DemoConfig(demoMode: true), throttleLimit: 0);

        $response = $handler->handle($this->demoRequest());

        $this->assertSame(429, $response->getStatusCode());
        $this->assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));
        $this->assertNotSame('', $response->getHeaderLine('Retry-After'));
    }

    public function test_throttle_answers_the_branded_html_page_for_browsers(): void
    {
        $handler = $this->startHandler(new DemoConfig(demoMode: true), throttleLimit: 0);

        $response = $handler->handle($this->demoRequest('text/html,application/xhtml+xml'));

        $this->assertSame(429, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertNotSame('', $response->getHeaderLine('Retry-After'));
        $this->assertSame('noindex', $response->getHeaderLine('X-Robots-Tag'));
        $html = (string) $response->getBody();
        $this->assertStringContainsString('NeNe Vault', $html);
        $this->assertStringContainsString('デモのご利用が集中しています', $html);
    }

    public function test_org_ceiling_answers_503(): void
    {
        // Guarantee at least one existing demo org, then cap the ceiling at 1.
        $slug = 'demo-cap' . substr(uniqid(), -6);
        self::pdo()->exec("INSERT INTO organizations (name, slug, plan, is_active, created_at, updated_at)
            VALUES ('Cap', '{$slug}', 'free', 1, datetime('now'), datetime('now'))");

        $handler = $this->startHandler(new DemoConfig(demoMode: true, maxOrgs: 1), throttleLimit: 5);

        $response = $handler->handle($this->demoRequest());

        $this->assertSame(503, $response->getStatusCode());
    }

    public function test_capacity_error_is_branded_for_browsers(): void
    {
        $slug = 'demo-cap' . substr(uniqid(), -6);
        self::pdo()->exec("INSERT INTO organizations (name, slug, plan, is_active, created_at, updated_at)
            VALUES ('Cap', '{$slug}', 'free', 1, datetime('now'), datetime('now'))");

        $handler = $this->startHandler(new DemoConfig(demoMode: true, maxOrgs: 1), throttleLimit: 5);

        $response = $handler->handle($this->demoRequest('text/html'));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('ただいまデモが満席です', (string) $response->getBody());
    }
}
