<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\DocumentVersion\DocumentStorageInterface;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;

final readonly class DownloadDocumentVersionUseCase implements DownloadDocumentVersionUseCaseInterface
{
    public function __construct(
        private DocumentVersionRepositoryInterface $versions,
        private DocumentStorageInterface $storage,
    ) {
    }

    /**
     * @return array{absolute_path: string, file_contents: string, mime_type: string, filename: string}
     */
    public function execute(string $documentId, string $versionId, int $organizationId): array
    {
        $version = $this->versions->findById($versionId, $organizationId);

        // Org-scoped + must belong to the named document
        if ($version === null || $version->vaultDocumentId !== $documentId) {
            throw new VaultDocumentNotFoundException($documentId);
        }

        if (!$this->storage->exists($version->filePath)) {
            throw new VaultDocumentNotFoundException($documentId);
        }

        $contents = $this->storage->readContents($version->filePath);

        // Verify SHA-256 before serving (compliance §3.1) — mismatch is a P0 defect
        $actualHash = hash('sha256', $contents);
        if (!hash_equals($version->fileSha256, $actualHash)) {
            throw new FileIntegrityException($versionId);
        }

        $absolutePath = $this->storage->resolveAbsolutePath($version->filePath);

        return [
            'absolute_path' => $absolutePath,
            'file_contents' => $contents,
            'mime_type' => $version->mimeType,
            'filename' => $version->originalFilename,
        ];
    }
}
