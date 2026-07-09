<?php

declare(strict_types=1);

namespace NeneVault\Tests\Support;

use NeneVault\Support\EnvFileLoader;
use PHPUnit\Framework\TestCase;

final class EnvFileLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/' . uniqid('envloader-', true);
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/.env');
        @rmdir($this->dir);
        putenv('ENVLOADER_PLAIN');
        putenv('ENVLOADER_QUOTED');
        putenv('ENVLOADER_PRESET');
    }

    public function test_loads_plain_and_quoted_values_and_skips_comments(): void
    {
        file_put_contents($this->dir . '/.env', "# comment\nENVLOADER_PLAIN=hello\nENVLOADER_QUOTED=\"with # hash\"\n");

        EnvFileLoader::load($this->dir);

        self::assertSame('hello', getenv('ENVLOADER_PLAIN'));
        self::assertSame('with # hash', getenv('ENVLOADER_QUOTED'));
    }

    public function test_real_environment_wins(): void
    {
        putenv('ENVLOADER_PRESET=from-real-env');
        file_put_contents($this->dir . '/.env', "ENVLOADER_PRESET=from-file\n");

        EnvFileLoader::load($this->dir);

        self::assertSame('from-real-env', getenv('ENVLOADER_PRESET'));
    }

    public function test_missing_file_is_a_no_op(): void
    {
        EnvFileLoader::load($this->dir);
        self::assertFalse(getenv('ENVLOADER_PLAIN'));
    }
}
