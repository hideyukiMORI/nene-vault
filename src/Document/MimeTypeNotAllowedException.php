<?php

declare(strict_types=1);

namespace NeneVault\Document;

use RuntimeException;

final class MimeTypeNotAllowedException extends RuntimeException
{
    public function __construct(string $mimeType)
    {
        parent::__construct("File type '{$mimeType}' is not allowed. Only PDF, JPEG, and PNG are accepted.");
    }
}
