<?php

declare(strict_types=1);

namespace NeneVault\Ocr;

/**
 * Metadata suggestions extracted from OCR text.
 *
 * All fields are nullable — the extractor returns null when it cannot find a
 * reliable value. The operator always confirms before applying.
 */
final readonly class OcrMetadataSuggestion
{
    public function __construct(
        public ?string $transactionDate,
        public ?int $amountCents,
        public ?string $counterpartyName,
        public string $rawText,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->transactionDate === null
            && $this->amountCents === null
            && $this->counterpartyName === null;
    }
}
