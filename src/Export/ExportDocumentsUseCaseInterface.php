<?php

declare(strict_types=1);

namespace NeneVault\Export;

interface ExportDocumentsUseCaseInterface
{
    /** Returns the manifest CSV content for the matching documents. */
    public function execute(ExportDocumentsInput $input): string;
}
