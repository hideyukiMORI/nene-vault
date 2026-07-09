<?php

declare(strict_types=1);

namespace NeneVault\Demo;

/**
 * Generates a minimal, self-contained one-page invoice PDF for demo seeding
 * (#118) — no PDF library, just the PDF 1.4 syntax with base-14 fonts and a
 * programmatically corrected xref table, so the bytes are a *valid* PDF that
 * browsers and viewers render.
 *
 * Base-14 fonts carry no CJK glyphs, so the PDF body uses romanized vendor
 * names and English labels; the Japanese vendor names live in the searchable
 * document metadata (`counterparty_name`), which is what the 電帳法 search
 * showcase exercises. The 適格請求書 registration number (T + 13 digits) is
 * printed prominently.
 */
final readonly class DemoInvoicePdf
{
    /**
     * @param list<array{string, int}> $lines item label (ASCII) and amount in yen
     */
    public static function build(
        string $vendorRomaji,
        string $registrationNumber,
        string $invoiceNumber,
        string $issueDate,
        array $lines,
        int $totalYen,
    ): string {
        $esc = static fn (string $s): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);

        $content = "BT\n/F1 22 Tf\n50 780 Td\n(INVOICE) Tj\nET\n";
        $content .= "BT\n/F2 11 Tf\n50 750 Td\n({$esc($vendorRomaji)}) Tj\nET\n";
        $content .= "BT\n/F2 10 Tf\n50 733 Td\n(Registration No. {$esc($registrationNumber)}) Tj\nET\n";
        $content .= "BT\n/F2 10 Tf\n400 780 Td\n(No. {$esc($invoiceNumber)}) Tj\nET\n";
        $content .= "BT\n/F2 10 Tf\n400 765 Td\n(Date: {$esc($issueDate)}) Tj\nET\n";
        $content .= "50 715 m 545 715 l S\n";

        $y = 685;
        foreach ($lines as [$label, $yen]) {
            $amount = number_format($yen);
            $content .= "BT\n/F2 11 Tf\n60 {$y} Td\n({$esc($label)}) Tj\nET\n";
            $content .= "BT\n/F2 11 Tf\n440 {$y} Td\n(JPY {$amount}) Tj\nET\n";
            $y -= 22;
        }

        $y -= 8;
        $content .= "50 {$y} m 545 {$y} l S\n";
        $y -= 24;
        $total = number_format($totalYen);
        $content .= "BT\n/F1 13 Tf\n360 {$y} Td\n(TOTAL  JPY {$total}) Tj\nET\n";
        $content .= "BT\n/F2 8 Tf\n50 60 Td\n(Sample document generated for the NeNe Vault demo. Not a real invoice.) Tj\nET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefAt = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefAt . "\n%%EOF\n";

        return $pdf;
    }
}
