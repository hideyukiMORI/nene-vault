<?php

declare(strict_types=1);

namespace NeneVault\Http;

use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Http\HealthCheckInterface;
use Nene2\Http\HealthStatus;
use Throwable;

/**
 * Reports database connectivity on `GET /health` (#163, deal shape).
 *
 * Adapted from the NENE2 reference implementation, which is outside the
 * framework's public API stability guarantee and intended to be copied into
 * the consuming application.
 */
final readonly class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private DatabaseConnectionFactoryInterface $connectionFactory,
    ) {
    }

    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthStatus
    {
        try {
            $this->connectionFactory->create()->query('SELECT 1');

            return HealthStatus::Ok;
        } catch (Throwable) {
            return HealthStatus::Error;
        }
    }
}
