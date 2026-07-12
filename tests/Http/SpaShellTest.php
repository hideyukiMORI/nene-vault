<?php

declare(strict_types=1);

namespace NeneVault\Tests\Http;

use NeneVault\Http\SpaShell;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpaShellTest extends TestCase
{
    private Psr17Factory $psr17;
    private string $shellPath;
    private string $shellHtml;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        // A faithful slice of the real shell: cross-origin Google Fonts in <head>,
        // absolute /assets/ references, an app root div.
        $this->shellHtml = "<!doctype html>\n<html lang=\"ja\">\n  <head>\n"
            . "    <meta charset=\"UTF-8\" />\n"
            . "    <link href=\"https://fonts.googleapis.com/css2?family=Noto+Sans+JP\" rel=\"stylesheet\" />\n"
            . "    <title>NeNe Vault</title>\n  </head>\n  <body>\n    <div id=\"root\"></div>\n  </body>\n</html>\n";
        $this->shellPath = tempnam(sys_get_temp_dir(), 'shell') ?: '';
        file_put_contents($this->shellPath, $this->shellHtml);
    }

    protected function tearDown(): void
    {
        if ($this->shellPath !== '' && is_file($this->shellPath)) {
            unlink($this->shellPath);
        }
    }

    private function shell(?string $analyticsEndpoint = null): SpaShell
    {
        return new SpaShell($this->shellPath, $this->psr17, $this->psr17, $analyticsEndpoint);
    }

    public function test_serves_the_shell_html(): void
    {
        $response = $this->shell()->serve();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<div id="root"></div>', (string) $response->getBody());
    }

    public function test_analytics_disabled_by_default_is_byte_identical_and_sets_no_csp(): void
    {
        // The OSS default: env unset → the shell body is the file verbatim and
        // no CSP header is set (the static-file behaviour it replaced).
        $response = $this->shell()->serve();
        self::assertNotNull($response);

        $body = (string) $response->getBody();
        self::assertSame($this->shellHtml, $body);
        self::assertStringNotContainsString('goatcounter', $body);
        self::assertStringNotContainsString('<script', $body);
        self::assertFalse($response->hasHeader('Content-Security-Policy'));
    }

    public function test_analytics_endpoint_injects_beacon_before_head_close(): void
    {
        $response = $this->shell('https://stats.example.test')->serve();
        self::assertNotNull($response);

        $body = (string) $response->getBody();
        self::assertStringContainsString(
            '<script data-goatcounter="https://stats.example.test/count" async src="https://stats.example.test/count.js"></script>',
            $body,
        );
        // Injected inside <head>, before the close.
        self::assertStringContainsString('</script>' . "\n  </head>", $body);
        // The original head content (fonts, title) is preserved.
        self::assertStringContainsString('fonts.googleapis.com', $body);
        self::assertStringContainsString('<title>NeNe Vault</title>', $body);
    }

    public function test_analytics_csp_widens_for_origin_and_keeps_google_fonts(): void
    {
        $response = $this->shell('https://stats.example.test')->serve();
        self::assertNotNull($response);

        $csp = $response->getHeaderLine('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self' https://stats.example.test", $csp);
        self::assertStringContainsString("connect-src 'self' https://stats.example.test", $csp);
        self::assertStringContainsString("img-src 'self' data: https://stats.example.test", $csp);
        // The shell's cross-origin Google Fonts must survive (clear #277 CSP trap).
        self::assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.googleapis.com", $csp);
        self::assertStringContainsString("font-src 'self' data: https://fonts.gstatic.com", $csp);
        // Hardening directives intact.
        self::assertStringContainsString("object-src 'none'", $csp);
        self::assertStringContainsString("frame-ancestors 'self'", $csp);
        self::assertStringContainsString("base-uri 'self'", $csp);
    }

    public function test_trailing_slash_is_trimmed_from_endpoint(): void
    {
        $response = $this->shell('https://stats.example.test/')->serve();
        self::assertNotNull($response);

        self::assertStringContainsString('src="https://stats.example.test/count.js"', (string) $response->getBody());
    }

    public function test_missing_shell_returns_null(): void
    {
        $shell = new SpaShell($this->shellPath . '.absent', $this->psr17, $this->psr17, 'https://stats.example.test');

        self::assertNull($shell->serve());
    }

    /**
     * A malformed or non-origin endpoint fails safe to disabled — no markup and
     * no CSP header — so a fat-fingered env can never inject a tag or a broken
     * header value.
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidEndpoints(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'no scheme' => ['stats.example.test'];
        yield 'has path' => ['https://stats.example.test/count'];
        yield 'has query' => ['https://stats.example.test?a=1'];
        yield 'has space' => ['https://stats.example.test evil'];
        yield 'javascript scheme' => ['javascript:alert(1)'];
        yield 'quote injection' => ['https://stats.example.test"><script>'];
    }

    #[DataProvider('invalidEndpoints')]
    public function test_invalid_endpoint_fails_safe_to_disabled(string $endpoint): void
    {
        $response = $this->shell($endpoint)->serve();
        self::assertNotNull($response);

        self::assertStringNotContainsString('goatcounter', (string) $response->getBody());
        self::assertFalse($response->hasHeader('Content-Security-Policy'));
    }
}
