<?php

declare(strict_types=1);

namespace NeneVault\Audit;

final readonly class ListAuditEventsOutput
{
    /**
     * @param list<AuditEvent> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
    ) {
    }
}
