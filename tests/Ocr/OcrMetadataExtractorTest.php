<?php

declare(strict_types=1);

namespace NeneVault\Tests\Ocr;

use NeneVault\Ocr\OcrMetadataExtractor;
use PHPUnit\Framework\TestCase;

final class OcrMetadataExtractorTest extends TestCase
{
    private OcrMetadataExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new OcrMetadataExtractor();
    }

    // ── Date extraction ────────────────────────────────────────────────────────

    public function test_extracts_iso_date(): void
    {
        $s = $this->extractor->extract("請求書\n発行日: 2026-05-31\n");
        $this->assertSame('2026-05-31', $s->transactionDate);
    }

    public function test_extracts_slash_date(): void
    {
        $s = $this->extractor->extract("2026/5/31\n");
        $this->assertSame('2026-05-31', $s->transactionDate);
    }

    public function test_extracts_japanese_date(): void
    {
        $s = $this->extractor->extract("2026年5月31日\n");
        $this->assertSame('2026-05-31', $s->transactionDate);
    }

    public function test_extracts_reiwa_date(): void
    {
        $s = $this->extractor->extract("令和8年5月31日\n");
        $this->assertSame('2026-05-31', $s->transactionDate);
    }

    public function test_date_null_when_not_found(): void
    {
        $s = $this->extractor->extract("請求書\n金額 110,000円\n");
        $this->assertNull($s->transactionDate);
    }

    // ── Amount extraction ─────────────────────────────────────────────────────

    public function test_extracts_amount_with_yen_sign(): void
    {
        $s = $this->extractor->extract("合計 ¥110,000\n");
        $this->assertSame(110000, $s->amountCents);
    }

    public function test_extracts_amount_with_yen_suffix(): void
    {
        $s = $this->extractor->extract("税込合計 110,000円\n");
        $this->assertSame(110000, $s->amountCents);
    }

    public function test_extracts_large_amount(): void
    {
        $s = $this->extractor->extract("請求金額 1,234,567円\n");
        $this->assertSame(1234567, $s->amountCents);
    }

    public function test_amount_null_when_not_found(): void
    {
        $s = $this->extractor->extract("2026-05-31\n株式会社サンプル\n");
        $this->assertNull($s->amountCents);
    }

    // ── Counterparty extraction ────────────────────────────────────────────────

    public function test_extracts_kabushiki_leading(): void
    {
        $s = $this->extractor->extract("株式会社サンプル御中\n2026年5月31日\n");
        $this->assertNotNull($s->counterpartyName);
        $this->assertStringContainsString('株式会社サンプル', $s->counterpartyName);
    }

    public function test_extracts_kabushiki_trailing(): void
    {
        $s = $this->extractor->extract("テスト株式会社\n合計 ¥55,000\n");
        $this->assertNotNull($s->counterpartyName);
        $this->assertStringContainsString('株式会社', $s->counterpartyName);
    }

    public function test_counterparty_null_when_not_found(): void
    {
        $s = $this->extractor->extract("2026-05-31\n¥110,000\n");
        $this->assertNull($s->counterpartyName);
    }

    // ── isEmpty / has_suggestion ──────────────────────────────────────────────

    public function test_not_empty_when_date_found(): void
    {
        $s = $this->extractor->extract('2026-05-31');
        $this->assertFalse($s->isEmpty());
    }

    public function test_empty_when_nothing_found(): void
    {
        $s = $this->extractor->extract('no data here');
        $this->assertTrue($s->isEmpty());
    }

    public function test_raw_text_preserved(): void
    {
        $text = "2026-05-31\n¥110,000\n";
        $s = $this->extractor->extract($text);
        $this->assertSame($text, $s->rawText);
    }
}
