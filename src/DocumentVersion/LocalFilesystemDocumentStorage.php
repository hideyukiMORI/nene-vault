<?php

declare(strict_types=1);

namespace NeneVault\DocumentVersion;

use RuntimeException;

final readonly class LocalFilesystemDocumentStorage implements DocumentStorageInterface
{
    public function __construct(
        private string $storageRoot,
    ) {
    }

    public function store(
        string $sourceTmpPath,
        int $organizationId,
        string $documentId,
        int $versionNumber,
        string $originalFilename,
    ): string {
        $sanitized = $this->sanitizeFilename($originalFilename);
        $relativeDir = sprintf('vault/%d/%s/v%d', $organizationId, $documentId, $versionNumber);
        $relativePath = $relativeDir . '/' . $sanitized;

        $absoluteDir = $this->storageRoot . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Failed to create storage directory: ' . $absoluteDir);
        }

        $dest = $this->storageRoot . '/' . $relativePath;

        // move_uploaded_file in production; copy fallback for test environments
        if (!@move_uploaded_file($sourceTmpPath, $dest)) {
            if (!@copy($sourceTmpPath, $dest)) {
                throw new RuntimeException('Failed to store uploaded file.');
            }
        }

        return $relativePath;
    }

    public function resolveAbsolutePath(string $relativePath): string
    {
        return $this->storageRoot . '/' . $relativePath;
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->resolveAbsolutePath($relativePath));
    }

    public function readContents(string $relativePath): string
    {
        $abs = $this->resolveAbsolutePath($relativePath);
        $contents = @file_get_contents($abs);

        if ($contents === false) {
            throw new RuntimeException('Failed to read stored file: ' . $relativePath);
        }

        return $contents;
    }

    public function sha256(string $absolutePath): string
    {
        $hash = hash_file('sha256', $absolutePath);

        if ($hash === false) {
            throw new RuntimeException('Failed to compute SHA-256 for stored file.');
        }

        return $hash;
    }

    private function sanitizeFilename(string $filename): string
    {
        $base = basename($filename);
        // Allow safe chars only; collapse everything else to underscore.
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?? 'upload';
        $safe = trim($safe, '.');

        return $safe === '' ? 'upload' : $safe;
    }
}
