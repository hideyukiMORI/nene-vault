<?php

declare(strict_types=1);

namespace NeneVault\Document;

interface SearchDocumentsUseCaseInterface
{
    public function execute(DocumentSearchCriteria $criteria): SearchDocumentsOutput;
}
