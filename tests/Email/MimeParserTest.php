<?php

declare(strict_types=1);

namespace NeneVault\Tests\Email;

use NeneVault\Email\MimeParser;
use PHPUnit\Framework\TestCase;

final class MimeParserTest extends TestCase
{
    private MimeParser $parser;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->parser = new MimeParser();
        $this->fixtureDir = dirname(__DIR__) . '/fixtures/email';
    }

    public function test_parse_multipart_pdf_attachment(): void
    {
        $raw = (string) file_get_contents($this->fixtureDir . '/invoice_with_pdf.eml');
        $email = $this->parser->parse($raw);

        $this->assertSame('ACME Corp <billing@acme.example>', $email->from);
        $this->assertSame('20260531100000.abc123@acme.example', $email->messageId);
        $this->assertSame('2026-05-31', $email->date);

        $this->assertCount(1, $email->attachments);
        $this->assertSame('invoice_2026_05.pdf', $email->attachments[0]->filename);
        $this->assertSame('application/pdf', $email->attachments[0]->mimeType);
        $this->assertTrue($email->attachments[0]->isAllowed());
    }

    public function test_parse_rfc2047_subject_base64(): void
    {
        $raw = (string) file_get_contents($this->fixtureDir . '/invoice_with_pdf.eml');
        $email = $this->parser->parse($raw);

        // =?UTF-8?B?6K2w5qCh5pu4?= decodes to 請求書
        $this->assertSame('請求書 2026-05', $email->subject);
    }

    public function test_parse_no_attachment(): void
    {
        $raw = (string) file_get_contents($this->fixtureDir . '/no_attachment.eml');
        $email = $this->parser->parse($raw);

        $this->assertSame('newsletter@example.com', $email->from);
        $this->assertCount(0, $email->attachments);
        $this->assertCount(0, $email->allowedAttachments());
    }

    public function test_parse_jpeg_attachment(): void
    {
        $raw = (string) file_get_contents($this->fixtureDir . '/multipart_with_jpeg.eml');
        $email = $this->parser->parse($raw);

        // =?UTF-8?Q?...?= decodes to 領収書
        $this->assertStringContainsString('領収書', $email->subject);

        $allowed = $email->allowedAttachments();
        $this->assertCount(1, $allowed);
        $this->assertSame('receipt.jpg', $allowed[0]->filename);
        $this->assertSame('image/jpeg', $allowed[0]->mimeType);
    }

    public function test_rfc2047_quoted_printable_subject(): void
    {
        $raw = "From: test@example.com\r\n"
            . "Subject: =?UTF-8?Q?=E9=A0=98=E5=8F=8E=E6=9B=B8?=\r\n"
            . "Message-ID: <test@example.com>\r\n"
            . "\r\n"
            . 'body';

        $email = $this->parser->parse($raw);
        $this->assertSame('領収書', $email->subject);
    }

    public function test_text_plain_is_not_attachment(): void
    {
        $raw = "From: test@example.com\r\n"
            . "Subject: test\r\n"
            . "Message-ID: <test2@example.com>\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . 'just text';

        $email = $this->parser->parse($raw);
        $this->assertCount(0, $email->attachments);
    }

    public function test_allowed_attachments_filters_disallowed(): void
    {
        $raw = "From: test@example.com\r\n"
            . "Subject: test\r\n"
            . "Message-ID: <test3@example.com>\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"b\"\r\n"
            . "\r\n"
            . "--b\r\n"
            . "Content-Type: text/csv; name=\"data.csv\"\r\n"
            . "Content-Disposition: attachment; filename=\"data.csv\"\r\n"
            . "\r\n"
            . "col1,col2\r\n"
            . "--b\r\n"
            . "Content-Type: application/pdf; name=\"invoice.pdf\"\r\n"
            . "Content-Disposition: attachment; filename=\"invoice.pdf\"\r\n"
            . "\r\n"
            . "fake pdf bytes\r\n"
            . "--b--\r\n";

        $email = $this->parser->parse($raw);
        $this->assertCount(2, $email->attachments);

        $allowed = $email->allowedAttachments();
        $this->assertCount(1, $allowed);
        $this->assertSame('invoice.pdf', $allowed[0]->filename);
    }
}
