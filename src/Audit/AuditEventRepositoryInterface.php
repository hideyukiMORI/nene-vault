<?php

declare(strict_types=1);

namespace NeneVault\Audit;

interface AuditEventRepositoryInterface
{
    /** Append-only insert. Implementations MUST NOT expose update or delete operations. */
    public function append(AuditEvent $event): void;

    /**
     * @param array<string, mixed> $filters  Keys: entity_type, entity_id, action, organization_id, actor_user_id
     * @return list<AuditEvent>
     */
    public function findByCriteria(array $filters, int $limit, int $offset): array;

    /** @param array<string, mixed> $filters */
    public function countByCriteria(array $filters): int;
}
