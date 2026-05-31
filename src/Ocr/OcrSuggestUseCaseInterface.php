<?php

declare(strict_types=1);

namespace NeneVault\Ocr;

interface OcrSuggestUseCaseInterface
{
    /**
     * @throws \NeneVault\Document\VaultDocumentNotFoundException
     * @throws OcrException
     */
    public function execute(string $documentId, int $organizationId): OcrMetadataSuggestion;
}
