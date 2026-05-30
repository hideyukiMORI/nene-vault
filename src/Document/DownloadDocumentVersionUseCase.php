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
     * @return array{absolute_path: string, mime_type: string, filename: string}
     */
    public function execute(string $documentId, string $versionId, int $organizationId): array
    {
        $version = $this->versions->findById($versionId, $organizationId);

        // Org-scoped + must belong to the named document
        if ($version === null || $version->vaultDocumentId !== $documentId) {
            throw new VaultDocumentNotFoundException($documentId);
        }

        $absolutePath = $this->storage->resolveAbsolutePath($version->filePath);

        if (!is_file($absolutePath)) {
            throw new VaultDocumentNotFoundException($documentId);
        }

        // Verify SHA-256 before serving (compliance §3.1) — mismatch is a P0 defect
        $actualHash = $this->storage->sha256($absolutePath);
        if (!hash_equals($version->fileSha256, $actualHash)) {
            throw new FileIntegrityException($versionId);
        }

        return [
            'absolute_path' => $absolutePath,
            'mime_type' => $version->mimeType,
            'filename' => $version->originalFilename,
        ];
    }
}
