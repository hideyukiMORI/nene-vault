<?php

declare(strict_types=1);

namespace NeneVault\Audit;

use Nene2\Audit\AuditEvent;

/**
 * Maps a framework {@see AuditEvent} to nene-vault's stable `/admin/audit-events`
 * JSON shape (unchanged since before the NENE2 `Nene2\Audit\*` adoption).
 *
 * `AuditTableConfig` has no `source` column axis, so upload call sites fold the
 * upload channel into `metadata['source']` at record time; this derives the
 * legacy top-level `source` field back out, defaulting to `'api'` when absent
 * (every non-upload action, and any row recorded before this field existed).
 */
final class AuditEventPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(AuditEvent $e): array
    {
        $metadata = $e->metadata;
        $source = is_array($metadata) && is_string($metadata['source'] ?? null) ? $metadata['source'] : 'api';

        return [
            'id'              => $e->id,
            'action'          => $e->action,
            'entity_type'     => $e->entityType,
            'entity_id'       => $e->entityId,
            'actor_user_id'   => self::toIntOrNull($e->actorId),
            'organization_id' => self::toIntOrNull($e->organizationId),
            'before_json'     => $e->before,
            'after_json'      => $e->after,
            'source'          => $source,
            'metadata_json'   => $metadata,
            'created_at'      => $e->occurredAt,
        ];
    }

    private static function toIntOrNull(string|int|null $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
