<?php

declare(strict_types=1);

namespace NeneVault\Tests\Support;

use Nene2\Database\DatabaseTransactionManagerInterface;

/**
 * Test double that runs a unit of work synchronously without a real database
 * transaction.
 *
 * Use-case unit tests inject in-memory repositories that ignore the executor, so
 * the callback receives a guard executor that throws if it is ever touched.
 */
final class SynchronousTransactionManager implements DatabaseTransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback(new NullDatabaseQueryExecutor());
    }
}
