<?php

declare(strict_types=1);

namespace NeneVault\Document;

use RuntimeException;

final class EmptyFileException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The uploaded file is empty (0 bytes). Upload a document with content.');
    }
}
