<?php

declare(strict_types=1);

namespace NeneVault\Tests\Http;

use LogicException;
use Nene2\Config\AppConfig;
use Nene2\Config\AppEnvironment;
use Nene2\Config\DatabaseConfig;
use NeneVault\Http\RuntimeServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The local JWT signing key must fail closed in production when
 * NENE2_LOCAL_JWT_SECRET is unset or blank, rather than falling back to the
 * public dev constant (which would let anyone forge a superadmin token).
 */
final class JwtSecretResolutionTest extends TestCase
{
    public function testProductionWithoutSecretRefusesToBoot(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('NENE2_LOCAL_JWT_SECRET');

        self::resolve(self::config(AppEnvironment::Production, null));
    }

    public function testProductionWithBlankSecretRefusesToBoot(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('NENE2_LOCAL_JWT_SECRET');

        self::resolve(self::config(AppEnvironment::Production, ''));
    }

    public function testProductionWithSecretUsesIt(): void
    {
        self::assertSame(
            'a-real-production-secret',
            self::resolve(self::config(AppEnvironment::Production, 'a-real-production-secret')),
        );
    }

    public function testLocalWithoutSecretFallsBackToDevConstant(): void
    {
        // Off-production convenience: the dev fallback is allowed only outside production.
        self::assertNotSame('', self::resolve(self::config(AppEnvironment::Local, null)));
    }

    private static function resolve(AppConfig $config): string
    {
        $method = new ReflectionMethod(RuntimeServiceProvider::class, 'resolveJwtSecret');

        /** @var string $secret */
        $secret = $method->invoke(null, $config);

        return $secret;
    }

    private static function config(AppEnvironment $environment, ?string $secret): AppConfig
    {
        return new AppConfig(
            $environment,
            false,
            'NeNe Vault',
            new DatabaseConfig(null, 'test', 'sqlite', '', 1, ':memory:', '', '', ''),
            null,
            $secret,
        );
    }
}
