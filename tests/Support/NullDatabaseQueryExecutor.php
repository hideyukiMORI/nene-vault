<?php

declare(strict_types=1);

namespace NeneVault\Tests\Support;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * Guard executor handed to use-case unit tests via {@see SynchronousTransactionManager}.
 *
 * Unit tests inject in-memory repositories that never touch the executor, so any
 * call here indicates a test wired against real SQL by mistake.
 */
final class NullDatabaseQueryExecutor implements DatabaseQueryExecutorInterface
{
    /** @param array<int|string, mixed> $parameters */
    public function execute(string $sql, array $parameters = []): int
    {
        throw $this->unexpected();
    }

    /** @param array<int|string, mixed> $parameters */
    public function insert(string $sql, array $parameters = []): int
    {
        throw $this->unexpected();
    }

    public function lastInsertId(): int
    {
        throw $this->unexpected();
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        throw $this->unexpected();
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $parameters = []): array
    {
        throw $this->unexpected();
    }

    private function unexpected(): LogicException
    {
        return new LogicException(
            'NullDatabaseQueryExecutor must not be used: inject in-memory repositories in use-case unit tests.',
        );
    }
}
