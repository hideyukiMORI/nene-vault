<?php

declare(strict_types=1);

namespace NeneVault\Document;

use RuntimeException;

/**
 * Thrown when a stored file's SHA-256 no longer matches the recorded hash.
 * This is a P0 integrity defect (received-document-compliance §3.1).
 */
final class FileIntegrityException extends RuntimeException
{
    public function __construct(string $versionId)
    {
        parent::__construct("File integrity check failed for version {$versionId}: stored hash does not match.");
    }
}
