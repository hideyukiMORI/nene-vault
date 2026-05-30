<?php

declare(strict_types=1);

namespace NeneVault\Document;

use RuntimeException;

final class FileTooLargeException extends RuntimeException
{
    public function __construct(int $sizeBytes, int $maxBytes)
    {
        parent::__construct("File size {$sizeBytes} bytes exceeds the maximum of {$maxBytes} bytes.");
    }
}
