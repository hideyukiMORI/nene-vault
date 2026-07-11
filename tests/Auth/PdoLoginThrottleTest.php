<?php

declare(strict_types=1);

namespace NeneVault\Tests\Auth;

use DateTimeImmutable;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneVault\Auth\PdoLoginThrottle;
use PHPUnit\Framework\TestCase;

/** A clock the test can advance, to exercise window/lock expiry. */
final class MutableClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $start = '2026-07-11T09:00:00+00:00')
    {
        $this->now = new DateTimeImmutable($start);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }
}

final class PdoLoginThrottleTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private MutableClock $clock;
    private PdoLoginThrottle $throttle;

    private const string ID = 'attacker@x.example|203.0.113.9';

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('vault-throttle-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        $this->query->execute('CREATE TABLE login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier_hash VARCHAR(64) NOT NULL,
            attempt_count INTEGER NOT NULL DEFAULT 0,
            window_started_at DATETIME NOT NULL,
            locked_until DATETIME,
            created_at DATETIME,
            UNIQUE(identifier_hash)
        )', []);
        $this->clock = new MutableClock();
        $this->throttle = new PdoLoginThrottle($this->query, $this->clock);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function test_not_locked_initially(): void
    {
        self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_locks_after_five_failures(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->throttle->recordFailure(self::ID);
            self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID), 'not locked after ' . ($i + 1));
        }

        $this->throttle->recordFailure(self::ID); // 5th → lock

        self::assertSame(900, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_success_clears_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->throttle->recordFailure(self::ID);
        }

        self::assertGreaterThan(0, $this->throttle->secondsUntilUnlocked(self::ID));

        $this->throttle->clear(self::ID);

        self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_lock_expires_after_lock_seconds(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->throttle->recordFailure(self::ID);
        }

        $this->clock->advance(901);

        self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_window_expiry_resets_the_counter(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->throttle->recordFailure(self::ID);
        }

        // Window (15 min) passes → the next failure starts a fresh window.
        $this->clock->advance(901);
        $this->throttle->recordFailure(self::ID);

        self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_identifiers_are_independent(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->throttle->recordFailure(self::ID);
        }

        self::assertSame(0, $this->throttle->secondsUntilUnlocked('someone-else@x.example|198.51.100.7'));
    }

    public function test_raw_identifier_is_never_persisted(): void
    {
        $this->throttle->recordFailure(self::ID);

        $rows = $this->query->fetchAll('SELECT identifier_hash FROM login_attempts', []);

        self::assertCount(1, $rows);
        self::assertDoesNotMatchRegularExpression('/attacker|203\.0\.113/', (string) $rows[0]['identifier_hash']);
    }
}
