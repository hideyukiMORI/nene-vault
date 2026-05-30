<?php

declare(strict_types=1);

namespace NeneVault\Document;

interface UploadDocumentUseCaseInterface
{
    /**
     * @throws MimeTypeNotAllowedException
     * @throws FileTooLargeException
     * @throws DuplicateFileException
     */
    public function execute(UploadDocumentInput $input): UploadDocumentOutput;
}
