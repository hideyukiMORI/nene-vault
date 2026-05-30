<?php

declare(strict_types=1);

namespace NeneVault\Audit;

final readonly class AuditEvent
{
    /**
     * @param array<string, mixed>|null $beforeJson  Sanitized snapshot before the mutation; null for create events
     * @param array<string, mixed>|null $afterJson   Sanitized snapshot after the mutation; null for delete events
     * @param array<string, mixed>|null $metadataJson Event-specific extra data (e.g. void_reason, export_filter)
     */
    public function __construct(
        public string $action,
        public string $entityType,
        public string $entityId,
        public ?int $actorUserId,
        public ?int $organizationId,
        public ?array $beforeJson,
        public ?array $afterJson,
        public string $source = 'api',
        public ?array $metadataJson = null,
        public ?int $id = null,
        public ?string $createdAt = null,
    ) {
    }
}
