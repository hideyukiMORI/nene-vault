<?php

declare(strict_types=1);

namespace NeneVault\Tests\Audit;

use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Audit\AuditRecorderInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * In-memory {@see AuditRecorderFactoryInterface} test double: captures every
 * recorded {@see AuditEvent} without touching a database, for use-case tests
 * that assert an audit side effect (e.g. CreateOrganizationUseCaseTest,
 * UploadDocumentUseCaseTest).
 *
 * The mechanics of recording itself (clock fill-in, org holder fallback, TX
 * binding) are covered by NENE2's own `Nene2\Audit\*` test suite — this double
 * only needs to capture what the use case passed in.
 */
final class InMemoryAuditRecorderFactory implements AuditRecorderFactoryInterface
{
    /** @var list<AuditEvent> */
    private array $events = [];

    public function forExecutor(DatabaseQueryExecutorInterface $executor): AuditRecorderInterface
    {
        return new class ($this) implements AuditRecorderInterface {
            public function __construct(private readonly InMemoryAuditRecorderFactory $owner)
            {
            }

            public function record(AuditEvent $event): void
            {
                $this->owner->append($event);
            }
        };
    }

    /** @internal */
    public function append(AuditEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<AuditEvent> */
    public function all(): array
    {
        return $this->events;
    }
}
