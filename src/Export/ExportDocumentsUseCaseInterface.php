<?php

declare(strict_types=1);

namespace NeneVault\Export;

interface ExportDocumentsUseCaseInterface
{
    public function execute(ExportDocumentsInput $input): ExportDocumentsOutput;
}
