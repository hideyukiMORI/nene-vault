<?php

declare(strict_types=1);

namespace NeneVault\Audit;

interface AuditRecorderInterface
{
    /**
     * Record one mutating operation.
     *
     * @param array<string, mixed>|null $beforeJson  Sanitized entity state before mutation; null for create
     * @param array<string, mixed>|null $afterJson   Sanitized entity state after mutation; null for delete
     * @param array<string, mixed>|null $metadataJson Extra event data (void_reason, export_filter, etc.)
     */
    public function record(
        string $action,
        string $entityType,
        string $entityId,
        ?int $actorUserId,
        ?int $organizationId,
        ?array $beforeJson,
        ?array $afterJson,
        string $source = 'api',
        ?array $metadataJson = null,
    ): void;
}
