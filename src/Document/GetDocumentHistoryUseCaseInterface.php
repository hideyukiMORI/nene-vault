<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Audit\AuditEvent;
use NeneVault\DocumentVersion\DocumentVersion;

interface GetDocumentHistoryUseCaseInterface
{
    /**
     * @return array{versions: list<DocumentVersion>, audit_events: list<AuditEvent>}
     * @throws VaultDocumentNotFoundException
     */
    public function execute(string $documentId, int $organizationId): array;
}
