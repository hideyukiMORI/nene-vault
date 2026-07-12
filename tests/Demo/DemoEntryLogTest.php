<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use NeneVault\Demo\DemoEntryLog;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class DemoEntryLogTest extends TestCase
{
    /** @var list<string> */
    private array $lines = [];

    private function log(): DemoEntryLog
    {
        return new DemoEntryLog(function (string $line): void {
            $this->lines[] = $line;
        });
    }

    /**
     * @param array<string, string> $query
     */
    private function request(array $query = [], ?string $referer = null): ServerRequestInterface
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'https://vault.example.test/demo/standard')
            ->withQueryParams($query);

        if ($referer !== null) {
            $request = $request->withHeader('Referer', $referer);
        }

        return $request;
    }

    public function test_records_slug_referer_and_utm_tags(): void
    {
        $this->log()->record(
            $this->request([
                'utm_source' => 'newsletter',
                'utm_medium' => 'email',
                'utm_campaign' => 'launch',
            ], 'https://ref.example.test/post'),
            'demo-abc123',
        );

        self::assertCount(1, $this->lines);
        $line = $this->lines[0];
        self::assertStringStartsWith('NeNe Vault: demo-entry ', $line);
        self::assertStringContainsString('slug=demo-abc123', $line);
        self::assertStringContainsString('utm_source=newsletter', $line);
        self::assertStringContainsString('utm_medium=email', $line);
        self::assertStringContainsString('utm_campaign=launch', $line);
        self::assertStringContainsString('referer=https://ref.example.test/post', $line);
    }

    public function test_missing_tags_render_as_dash(): void
    {
        $this->log()->record($this->request(), 'guided');

        self::assertSame(
            'NeNe Vault: demo-entry slug=guided utm_source=- utm_medium=- utm_campaign=- referer=-',
            $this->lines[0],
        );
    }

    public function test_control_characters_are_stripped_to_prevent_log_injection(): void
    {
        $this->log()->record(
            $this->request(['utm_source' => "evil\r\nNeNe Vault: demo-entry slug=forged"]),
            'demo-x',
        );

        self::assertCount(1, $this->lines);
        // The forged newline must not create a second logical line.
        self::assertStringNotContainsString("\n", $this->lines[0]);
        self::assertStringContainsString('utm_source=evil NeNe Vault: demo-entry slug=forged', $this->lines[0]);
    }

    public function test_non_string_query_value_renders_as_dash(): void
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'https://vault.example.test/demo/standard')
            ->withQueryParams(['utm_source' => ['array', 'value']]);

        $this->log()->record($request, 'demo-x');

        self::assertStringContainsString('utm_source=-', $this->lines[0]);
    }

    public function test_long_value_is_capped(): void
    {
        $this->log()->record($this->request(['utm_campaign' => str_repeat('a', 500)]), 'demo-x');

        self::assertStringContainsString('utm_campaign=' . str_repeat('a', 256) . ' ', $this->lines[0] . ' ');
        self::assertStringNotContainsString(str_repeat('a', 257), $this->lines[0]);
    }
}
