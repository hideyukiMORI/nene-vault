<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Audit\AuditEventRepositoryInterface;
use Nene2\Audit\AuditQuery;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;

final readonly class GetDocumentHistoryUseCase implements GetDocumentHistoryUseCaseInterface
{
    private const MAX_EVENTS = 500;

    public function __construct(
        private VaultDocumentRepositoryInterface $documents,
        private DocumentVersionRepositoryInterface $versions,
        private AuditEventRepositoryInterface $auditEvents,
    ) {
    }

    /**
     * @return array{versions: list<\NeneVault\DocumentVersion\DocumentVersion>, audit_events: list<\Nene2\Audit\AuditEvent>}
     */
    public function execute(string $documentId, int $organizationId): array
    {
        $document = $this->documents->findById($documentId, $organizationId);

        if ($document === null) {
            throw new VaultDocumentNotFoundException($documentId);
        }

        $versions = $this->versions->listByDocumentId($documentId, $organizationId);

        $auditEvents = $this->auditEvents->query(
            new AuditQuery(
                organizationId: $organizationId,
                entityType: 'vault_document',
                entityId: $documentId,
            ),
            self::MAX_EVENTS,
            0,
        );

        return ['versions' => $versions, 'audit_events' => $auditEvents];
    }
}
