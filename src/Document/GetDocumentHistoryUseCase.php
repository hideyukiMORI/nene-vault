<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\Audit\AuditEventRepositoryInterface;
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
     * @return array{versions: list<\NeneVault\DocumentVersion\DocumentVersion>, audit_events: list<\NeneVault\Audit\AuditEvent>}
     */
    public function execute(string $documentId, int $organizationId): array
    {
        $document = $this->documents->findById($documentId, $organizationId);

        if ($document === null) {
            throw new VaultDocumentNotFoundException($documentId);
        }

        $versions = $this->versions->listByDocumentId($documentId, $organizationId);

        $auditEvents = $this->auditEvents->findByCriteria(
            [
                'organization_id' => $organizationId,
                'entity_type' => 'vault_document',
                'entity_id' => $documentId,
            ],
            self::MAX_EVENTS,
            0,
        );

        return ['versions' => $versions, 'audit_events' => $auditEvents];
    }
}
