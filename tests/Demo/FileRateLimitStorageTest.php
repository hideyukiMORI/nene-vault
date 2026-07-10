<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use NeneVault\Demo\FileRateLimitStorage;
use NeneVault\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class FileRateLimitStorageTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/nene-vault-rate-limit-test-' . bin2hex(random_bytes(6));
        mkdir($this->baseDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->baseDir . '/rate-limits/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->baseDir . '/rate-limits');
        @rmdir($this->baseDir);
    }

    public function test_counts_hits_within_the_window_across_instances(): void
    {
        $clock = new FixedClock('2026-07-10T00:00:00+00:00');

        // Separate instances share state through the file — the property the
        // shared-hosting deployment depends on (one PHP process per request).
        $first = (new FileRateLimitStorage($this->baseDir, $clock))->hit('ip:203.0.113.7', 3600);
        $second = (new FileRateLimitStorage($this->baseDir, $clock))->hit('ip:203.0.113.7', 3600);

        self::assertSame(1, $first['count']);
        self::assertSame(2, $second['count']);
        self::assertSame($first['reset_at'], $second['reset_at']);
    }

    public function test_window_resets_after_expiry(): void
    {
        (new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-10T00:00:00+00:00')))->hit('k', 3600);
        $later = (new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-10T02:00:00+00:00')))->hit('k', 3600);

        self::assertSame(1, $later['count']);
    }

    public function test_keys_are_isolated(): void
    {
        $clock = new FixedClock('2026-07-10T00:00:00+00:00');
        $storage = new FileRateLimitStorage($this->baseDir, $clock);

        $storage->hit('a', 3600);
        $b = $storage->hit('b', 3600);

        self::assertSame(1, $b['count']);
    }
}
