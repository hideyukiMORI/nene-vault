<?php

declare(strict_types=1);

namespace NeneVault\Audit;

final readonly class ListAuditEventsInput
{
    public function __construct(
        public ?int $organizationId,
        public ?string $entityType,
        public ?string $entityId,
        public ?string $action,
        public ?int $actorUserId,
        public int $limit,
        public int $offset,
    ) {
    }
}
