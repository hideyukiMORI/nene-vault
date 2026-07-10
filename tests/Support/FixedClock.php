<?php

declare(strict_types=1);

namespace NeneVault\Tests\Support;

use DateTimeImmutable;
use Nene2\Http\ClockInterface;

/**
 * Test {@see ClockInterface} that always returns a fixed instant, so
 * time-dependent behaviour (token iat/exp, rate-limit windows) is
 * deterministic.
 */
final readonly class FixedClock implements ClockInterface
{
    public const DEFAULT_INSTANT = '2026-07-10T10:00:00+00:00';

    public function __construct(private string $instant = self::DEFAULT_INSTANT)
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->instant);
    }
}
