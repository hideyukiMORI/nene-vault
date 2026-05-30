<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\DocumentVersion\DocumentVersion;

final readonly class SearchDocumentsOutput
{
    /**
     * @param list<array{0: VaultDocument, 1: DocumentVersion}> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
    ) {
    }
}
