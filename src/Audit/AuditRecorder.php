<?php

declare(strict_types=1);

namespace NeneVault\Audit;

/**
 * Records one audit event per mutating operation.
 *
 * This service is called from the UseCase layer, which has both the actor/tenant
 * context and the before/after entity state.
 *
 * Sanitization rules:
 *  - before_json and after_json are produced by dedicated toAuditArray() helpers
 *    on domain entities; they must never contain passwords, tokens, or file paths.
 *  - If the repository call throws, the exception propagates — callers must
 *    decide whether to wrap in a transaction.
 */
final readonly class AuditRecorder implements AuditRecorderInterface
{
    public function __construct(
        private AuditEventRepositoryInterface $repository,
    ) {
    }

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
    ): void {
        $this->repository->append(new AuditEvent(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            actorUserId: $actorUserId,
            organizationId: $organizationId,
            beforeJson: $beforeJson,
            afterJson: $afterJson,
            source: $source,
            metadataJson: $metadataJson,
        ));
    }
}
