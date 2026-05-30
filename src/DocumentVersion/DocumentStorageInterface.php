<?php

declare(strict_types=1);

namespace NeneVault\DocumentVersion;

/**
 * File storage abstraction for document versions.
 *
 * The local filesystem adapter is Phase 1–3; an S3-compatible adapter is Phase 4+
 * (ADR 0012). Implementations own all file I/O; repositories and use cases never
 * touch the filesystem directly.
 */
interface DocumentStorageInterface
{
    /**
     * Store a file for a document version and return the relative storage path.
     *
     * Layout (ADR 0012):
     * {root}/vault/{organizationId}/{documentId}/v{versionNumber}/{sanitizedFilename}
     *
     * File bytes are never overwritten — each version writes to a distinct path.
     *
     * @return string Relative path (never exposed in API responses)
     */
    public function store(
        string $sourceTmpPath,
        int $organizationId,
        string $documentId,
        int $versionNumber,
        string $originalFilename,
    ): string;

    /** Absolute path for reading a stored file. */
    public function resolveAbsolutePath(string $relativePath): string;

    /** Compute the SHA-256 hex digest of a file. */
    public function sha256(string $absolutePath): string;
}
