<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;

final readonly class GetDocumentByIdUseCase implements GetDocumentByIdUseCaseInterface
{
    public function __construct(
        private VaultDocumentRepositoryInterface $documents,
        private DocumentVersionRepositoryInterface $versions,
    ) {
    }

    /**
     * @return array{0: VaultDocument, 1: \NeneVault\DocumentVersion\DocumentVersion}
     */
    public function execute(string $id, int $organizationId): array
    {
        $document = $this->documents->findById($id, $organizationId);

        if ($document === null) {
            throw new VaultDocumentNotFoundException($id);
        }

        $version = $this->versions->findById($document->currentVersionId, $organizationId);

        if ($version === null) {
            // A document must always have its current version; absence is a data integrity error.
            throw new VaultDocumentNotFoundException($id);
        }

        return [$document, $version];
    }
}
