<?php

declare(strict_types=1);

namespace NeneVault\Document;

use RuntimeException;

final class MimeTypeNotAllowedException extends RuntimeException
{
    /**
     * @param string      $mimeType     the client-declared media type
     * @param string|null $sniffedType  the type detected from the file's magic
     *                                   bytes, when the rejection is a content
     *                                   mismatch (a spoofed declaration) rather
     *                                   than a disallowed declaration
     */
    public function __construct(string $mimeType, ?string $sniffedType = null)
    {
        $message = $sniffedType === null
            ? "File type '{$mimeType}' is not allowed. Only PDF, JPEG, and PNG are accepted."
            : "Declared file type '{$mimeType}' does not match the file content "
                . "('{$sniffedType}'). Only genuine PDF, JPEG, and PNG files are accepted.";

        parent::__construct($message);
    }
}
