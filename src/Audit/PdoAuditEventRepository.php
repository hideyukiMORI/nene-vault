<?php

declare(strict_types=1);

namespace NeneVault\Audit;

use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * Append-only audit event repository.
 *
 * The application DB user must have INSERT on audit_events but NOT UPDATE or DELETE.
 * This class intentionally exposes no update or delete methods.
 */
final readonly class PdoAuditEventRepository implements AuditEventRepositoryInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function append(AuditEvent $event): void
    {
        $this->query->execute(
            'INSERT INTO audit_events
                (action, entity_type, entity_id, actor_user_id, organization_id,
                 before_json, after_json, source, metadata_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $event->action,
                $event->entityType,
                $event->entityId,
                $event->actorUserId,
                $event->organizationId,
                $event->beforeJson !== null ? json_encode($event->beforeJson, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
                $event->afterJson !== null ? json_encode($event->afterJson, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
                $event->source,
                $event->metadataJson !== null ? json_encode($event->metadataJson, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
                date('Y-m-d H:i:s'),
            ],
        );
    }

    /** @param array<string, mixed> $filters */
    public function findByCriteria(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $rows = $this->query->fetchAll(
            'SELECT id, action, entity_type, entity_id, actor_user_id, organization_id,
                    before_json, after_json, source, metadata_json, created_at
             FROM audit_events' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?',
            [...$params, $limit, $offset],
        );

        return array_map($this->mapRow(...), $rows);
    }

    /** @param array<string, mixed> $filters */
    public function countByCriteria(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $row = $this->query->fetchOne(
            'SELECT COUNT(*) AS cnt FROM audit_events' . $where,
            $params,
        );

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{string, list<mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (isset($filters['organization_id'])) {
            $conditions[] = 'organization_id = ?';
            $params[] = (int) $filters['organization_id'];
        }

        if (isset($filters['entity_type'])) {
            $conditions[] = 'entity_type = ?';
            $params[] = (string) $filters['entity_type'];
        }

        if (isset($filters['entity_id'])) {
            $conditions[] = 'entity_id = ?';
            $params[] = (string) $filters['entity_id'];
        }

        if (isset($filters['action'])) {
            $conditions[] = 'action = ?';
            $params[] = (string) $filters['action'];
        }

        if (isset($filters['actor_user_id'])) {
            $conditions[] = 'actor_user_id = ?';
            $params[] = (int) $filters['actor_user_id'];
        }

        $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

        return [$where, $params];
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): AuditEvent
    {
        $beforeJson = isset($row['before_json'])
            ? json_decode((string) $row['before_json'], true, 512, JSON_THROW_ON_ERROR)
            : null;
        $afterJson = isset($row['after_json'])
            ? json_decode((string) $row['after_json'], true, 512, JSON_THROW_ON_ERROR)
            : null;
        $metadataJson = isset($row['metadata_json'])
            ? json_decode((string) $row['metadata_json'], true, 512, JSON_THROW_ON_ERROR)
            : null;

        return new AuditEvent(
            action: (string) $row['action'],
            entityType: (string) $row['entity_type'],
            entityId: (string) $row['entity_id'],
            actorUserId: isset($row['actor_user_id']) ? (int) $row['actor_user_id'] : null,
            organizationId: isset($row['organization_id']) ? (int) $row['organization_id'] : null,
            beforeJson: is_array($beforeJson) ? $beforeJson : null,
            afterJson: is_array($afterJson) ? $afterJson : null,
            source: (string) ($row['source'] ?? 'api'),
            metadataJson: is_array($metadataJson) ? $metadataJson : null,
            id: (int) $row['id'],
            createdAt: (string) $row['created_at'],
        );
    }
}
