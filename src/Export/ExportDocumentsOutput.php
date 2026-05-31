<?php

declare(strict_types=1);

namespace NeneVault\Export;

final readonly class ExportDocumentsOutput
{
    /**
     * @param string $format       'csv' or 'zip'
     * @param string $payload      CSV string for 'csv'; raw ZIP bytes for 'zip'
     * @param int    $documentCount Number of documents included
     */
    public function __construct(
        public string $format,
        public string $payload,
        public int $documentCount,
    ) {
    }
}
