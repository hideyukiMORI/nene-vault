<?php

declare(strict_types=1);

namespace NeneVault\Document;

final readonly class SearchDocumentsUseCase implements SearchDocumentsUseCaseInterface
{
    public function __construct(
        private VaultDocumentRepositoryInterface $documents,
    ) {
    }

    public function execute(DocumentSearchCriteria $criteria): SearchDocumentsOutput
    {
        return new SearchDocumentsOutput(
            items: $this->documents->search($criteria),
            total: $this->documents->countByCriteria($criteria),
            limit: $criteria->limit,
            offset: $criteria->offset,
        );
    }
}
