<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use NeneVault\Demo\DemoInvoicePdf;
use PHPUnit\Framework\TestCase;

final class DemoInvoicePdfTest extends TestCase
{
    private function build(): string
    {
        return DemoInvoicePdf::build(
            vendorRomaji: 'Yamato Kensetsu K.K.',
            registrationNumber: 'T1234567890123',
            invoiceNumber: 'INV-202607-001',
            issueDate: '2026-07-01',
            lines: [['Scaffolding work', 550000], ['Site (extra) costs', 66000]],
            totalYen: 616000,
        );
    }

    public function test_produces_a_structurally_valid_pdf(): void
    {
        $pdf = $this->build();

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringEndsWith("%%EOF\n", $pdf);

        // The startxref offset must point at the xref table — the property
        // real viewers need; a drifting offset means the writer broke.
        self::assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF\n$/', $pdf, $m));
        $xrefAt = (int) ($m[1] ?? -1);
        self::assertSame('xref', substr($pdf, $xrefAt, 4));

        // Every object offset in the xref table points at its object header.
        preg_match_all('/^(\d{10}) 00000 n /m', $pdf, $offsets);
        foreach (array_slice($offsets[1], 0, 6) as $i => $offset) {
            self::assertSame(($i + 1) . ' 0 obj', substr($pdf, (int) $offset, strlen(($i + 1) . ' 0 obj')));
        }
    }

    public function test_contains_the_invoice_facts_and_escapes_specials(): void
    {
        $pdf = $this->build();

        self::assertStringContainsString('(INVOICE)', $pdf);
        self::assertStringContainsString('T1234567890123', $pdf);
        self::assertStringContainsString('INV-202607-001', $pdf);
        self::assertStringContainsString('TOTAL  JPY 616,000', $pdf);
        // Parentheses in labels must be escaped inside PDF string literals.
        self::assertStringContainsString('Site \\(extra\\) costs', $pdf);
    }
}
