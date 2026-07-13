<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use NeneVault\Demo\DemoEntryLog;
use NeneVault\Demo\FileDemoEntryLogSink;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class FileDemoEntryLogSinkTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/nene-vault-entry-log-test-' . bin2hex(random_bytes(6));
        mkdir($this->baseDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->baseDir . '/demo-entry.log');
        @rmdir($this->baseDir);
    }

    public function test_appends_one_line_per_call_to_the_var_log_file(): void
    {
        $sink = new FileDemoEntryLogSink($this->baseDir);

        $sink('NeNe Vault: demo-entry slug=demo-abc123 utm_source=newsletter utm_medium=- utm_campaign=- referer=-');
        $sink('NeNe Vault: demo-entry slug=guided utm_source=- utm_medium=- utm_campaign=- referer=-');

        $written = file_get_contents($this->baseDir . '/demo-entry.log');
        self::assertIsString($written);
        $lines = explode(PHP_EOL, trim($written));

        self::assertCount(2, $lines);
        self::assertSame(
            'NeNe Vault: demo-entry slug=demo-abc123 utm_source=newsletter utm_medium=- utm_campaign=- referer=-',
            $lines[0],
        );
        self::assertSame(
            'NeNe Vault: demo-entry slug=guided utm_source=- utm_medium=- utm_campaign=- referer=-',
            $lines[1],
        );
    }

    public function test_creates_the_base_dir_when_missing(): void
    {
        $missingDir = $this->baseDir . '/nested';
        $sink = new FileDemoEntryLogSink($missingDir);

        $sink('NeNe Vault: demo-entry slug=guided utm_source=- utm_medium=- utm_campaign=- referer=-');

        self::assertFileExists($missingDir . '/demo-entry.log');

        // Extra teardown for the nested dir this test creates.
        @unlink($missingDir . '/demo-entry.log');
        @rmdir($missingDir);
    }

    public function test_falls_back_to_error_log_when_the_file_cannot_be_opened(): void
    {
        // A baseDir that can never become a writable directory (it's an
        // existing regular file, so @mkdir/@fopen both fail) forces the
        // fopen-failure fallback path without needing chmod tricks that
        // don't reliably deny root/CI runners.
        $unwritable = $this->baseDir . '/not-a-dir';
        file_put_contents($unwritable, 'x');

        $sink = new FileDemoEntryLogSink($unwritable);

        $previous = ini_set('error_log', $this->baseDir . '/php-error.log');
        try {
            $sink('NeNe Vault: demo-entry slug=demo-x utm_source=- utm_medium=- utm_campaign=- referer=-');

            $fallback = file_get_contents($this->baseDir . '/php-error.log');
            self::assertIsString($fallback);
            self::assertStringContainsString(
                'NeNe Vault: demo-entry slug=demo-x utm_source=- utm_medium=- utm_campaign=- referer=-',
                $fallback,
            );
        } finally {
            if ($previous !== false) {
                ini_set('error_log', $previous);
            }
            @unlink($unwritable);
            @unlink($this->baseDir . '/php-error.log');
        }
    }

    public function test_wired_through_demo_entry_log_preserves_the_line_format(): void
    {
        $sink = new FileDemoEntryLogSink($this->baseDir);
        $entryLog = new DemoEntryLog(\Closure::fromCallable($sink));

        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'https://vault.example.test/demo/standard')
            ->withQueryParams(['utm_source' => 'newsletter']);

        $entryLog->record($request, 'demo-abc123');

        $written = file_get_contents($this->baseDir . '/demo-entry.log');
        self::assertIsString($written);
        self::assertSame(
            'NeNe Vault: demo-entry slug=demo-abc123 utm_source=newsletter utm_medium=- utm_campaign=- referer=-',
            trim($written),
        );
    }
}
