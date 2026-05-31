<?php

declare(strict_types=1);

namespace NeneVault\Ocr;

use NeneVault\Document\VaultDocumentNotFoundException;
use NeneVault\Document\VaultDocumentRepositoryInterface;
use NeneVault\DocumentVersion\DocumentStorageInterface;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;

final readonly class OcrSuggestUseCase implements OcrSuggestUseCaseInterface
{
    public function __construct(
        private VaultDocumentRepositoryInterface $documents,
        private DocumentVersionRepositoryInterface $versions,
        private DocumentStorageInterface $storage,
        private OcrExtractorInterface $ocr,
        private OcrMetadataExtractor $extractor,
    ) {
    }

    public function execute(string $documentId, int $organizationId): OcrMetadataSuggestion
    {
        $document = $this->documents->findById($documentId, $organizationId);

        if ($document === null) {
            throw new VaultDocumentNotFoundException($documentId);
        }

        $version = $this->versions->findById($document->currentVersionId, $organizationId);

        if ($version === null) {
            throw new VaultDocumentNotFoundException($documentId);
        }

        $absolutePath = $this->storage->resolveAbsolutePath($version->filePath);

        $rawText = $this->ocr->extract($absolutePath);

        return $this->extractor->extract($rawText);
    }
}
