<?php

declare(strict_types=1);

namespace NeneVault\Ocr;

/**
 * Runs Tesseract OCR via exec() to extract text from PDF or image files.
 *
 * Requirements:
 *   - tesseract binary in $PATH (or configured via NENE_VAULT_OCR_BINARY)
 *   - Language packs: e.g. tesseract-ocr-jpn, tesseract-ocr-eng
 *
 * For PDF input, Tesseract requires Ghostscript (gs) or ImageMagick (convert)
 * to be installed.
 *
 * Install on Debian/Ubuntu:
 *   apt install tesseract-ocr tesseract-ocr-jpn tesseract-ocr-eng ghostscript
 */
final readonly class TesseractOcrExtractor implements OcrExtractorInterface
{
    public function __construct(
        private string $binary = 'tesseract',
        private string $lang = 'jpn+eng',
    ) {
    }

    public function extract(string $absolutePath): string
    {
        if (!is_file($absolutePath)) {
            throw new OcrException('File not found: ' . $absolutePath);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'vault_ocr_');

        if ($tmp === false) {
            throw new OcrException('Failed to create temporary file for OCR output.');
        }

        // Tesseract writes output to $tmp.txt; we specify the base path without extension.
        $outputBase = $tmp;
        @unlink($tmp);

        $cmd = sprintf(
            '%s %s %s -l %s --psm 3 2>/dev/null',
            escapeshellcmd($this->binary),
            escapeshellarg($absolutePath),
            escapeshellarg($outputBase),
            escapeshellarg($this->lang),
        );

        exec($cmd, $output, $exitCode);

        $outputFile = $outputBase . '.txt';

        if ($exitCode !== 0 || !file_exists($outputFile)) {
            @unlink($outputFile);
            throw new OcrException(sprintf(
                'Tesseract exited with code %d for file "%s".',
                $exitCode,
                basename($absolutePath),
            ));
        }

        $text = (string) file_get_contents($outputFile);
        @unlink($outputFile);

        return $text;
    }
}
