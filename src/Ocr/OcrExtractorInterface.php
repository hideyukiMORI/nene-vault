<?php

declare(strict_types=1);

namespace NeneVault\Ocr;

interface OcrExtractorInterface
{
    /**
     * Extract plain text from a document file.
     *
     * @param string $absolutePath Absolute filesystem path to the file
     * @return string Extracted text (may be empty if OCR yields nothing)
     * @throws OcrException On unrecoverable OCR failure (e.g. binary not found)
     */
    public function extract(string $absolutePath): string;
}
