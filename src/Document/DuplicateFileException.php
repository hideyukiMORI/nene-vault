<?php

declare(strict_types=1);

namespace NeneVault\Document;

use RuntimeException;

/**
 * Thrown when an upload's SHA-256 already exists in the organization and the
 * operator has not confirmed the duplicate (received-document-compliance §3.5).
 */
final class DuplicateFileException extends RuntimeException
{
    public function __construct(string $fileSha256)
    {
        parent::__construct("A file with SHA-256 {$fileSha256} already exists in this organization. Set confirm_duplicate to upload anyway.");
    }
}
