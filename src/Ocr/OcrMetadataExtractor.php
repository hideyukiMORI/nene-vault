<?php

declare(strict_types=1);

namespace NeneVault\Ocr;

/**
 * Extracts structured metadata (date, amount, counterparty) from raw OCR text.
 *
 * Uses heuristic regex patterns for Japanese business documents. Returns null
 * for any field that cannot be found reliably. The operator always confirms.
 */
final class OcrMetadataExtractor
{
    public function extract(string $rawText): OcrMetadataSuggestion
    {
        return new OcrMetadataSuggestion(
            transactionDate: $this->extractDate($rawText),
            amountCents: $this->extractAmount($rawText),
            counterpartyName: $this->extractCounterparty($rawText),
            rawText: $rawText,
        );
    }

    // ── Date extraction ────────────────────────────────────────────────────────

    private function extractDate(string $text): ?string
    {
        // ISO 8601: 2026-05-31 or 2026/05/31
        if (preg_match('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/', $text, $m)) {
            return $this->formatDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Japanese: 令和8年5月31日 / 2026年5月31日
        if (preg_match('/(\d{4})年\s*(\d{1,2})月\s*(\d{1,2})日/', $text, $m)) {
            return $this->formatDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Reiwa era: 令和N年 (令和1 = 2019)
        if (preg_match('/令和\s*(\d{1,2})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日/', $text, $m)) {
            $year = 2018 + (int) $m[1];
            return $this->formatDate($year, (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    private function formatDate(int $year, int $month, int $day): ?string
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    // ── Amount extraction ─────────────────────────────────────────────────────

    private function extractAmount(string $text): ?int
    {
        // Look for 合計, 請求金額, 税込, 総計 followed by an amount
        // Pattern: ¥110,000 or ￥110,000 or 110,000円 or 110000円
        $amountPatterns = [
            // Label + amount on same or next lines
            '/(?:合計|請求金額|ご請求金額|税込(?:合計)?|総額|total|TOTAL)[^\d¥￥]*([¥￥]?\s*[\d,]+)\s*円?/u',
            // ¥amount
            '/[¥￥]\s*([\d,]+)/',
            // amount + 円
            '/([\d,]{4,})円/',
        ];

        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $amount = $this->parseAmount($m[1]);

                if ($amount !== null && $amount >= 100 && $amount <= 99_999_999) {
                    return $amount;
                }
            }
        }

        return null;
    }

    private function parseAmount(string $raw): ?int
    {
        $cleaned = preg_replace('/[^0-9]/', '', $raw);

        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        return (int) $cleaned;
    }

    // ── Counterparty extraction ────────────────────────────────────────────────

    private function extractCounterparty(string $text): ?string
    {
        // Look for 株式会社, 有限会社, 合同会社, ㈱ etc. — typical Japanese company names
        $companyPatterns = [
            // Leading: 株式会社 Foo
            '/((?:株式会社|有限会社|合同会社|一般社団法人|一般財団法人|特定非営利活動法人)[^\n\r、。　]{2,20})/u',
            // Trailing: Foo 株式会社
            '/([^\n\r、。　]{2,20}(?:株式会社|有限会社|合同会社))/u',
            // ㈱ Foo
            '/(㈱[^\n\r、。　]{2,15})/u',
        ];

        foreach ($companyPatterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $name = trim($m[1]);

                if (mb_strlen($name) >= 4) {
                    return $name;
                }
            }
        }

        return null;
    }
}
