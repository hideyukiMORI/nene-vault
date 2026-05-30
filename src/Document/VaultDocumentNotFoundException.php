<?php

declare(strict_types=1);

namespace NeneVault\Document;

use RuntimeException;

final class VaultDocumentNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Document with id {$id} was not found.");
    }
}
