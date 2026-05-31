<?php

declare(strict_types=1);

namespace NeneVault\Tests\Email;

use NeneVault\Email\MaildirPoller;
use NeneVault\Email\MimeParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * MaildirPoller boundary tests: scanning, parsing, processed-file move,
 * empty dir, missing dir.
 */
final class MaildirPollerTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/vault_maildir_' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function poller(): MaildirPoller
    {
        return new MaildirPoller($this->dir, new MimeParser());
    }

    private function writeEml(string $name, string $content): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    private function sampleEml(string $from = 'vendor@example.com'): string
    {
        $pdf = base64_encode('%PDF-1.4 fake');

        return "From: {$from}\r\n"
            . "Subject: Invoice\r\n"
            . 'Message-ID: <' . uniqid() . "@example.com>\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"b\"\r\n"
            . "\r\n"
            . "--b\r\n"
            . "Content-Type: application/pdf; name=\"invoice.pdf\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=\"invoice.pdf\"\r\n"
            . "\r\n"
            . $pdf . "\r\n"
            . "--b--\r\n";
    }

    // ── Scanning ───────────────────────────────────────────────────────────────

    public function test_poll_empty_dir_returns_empty(): void
    {
        $this->assertSame([], $this->poller()->poll());
    }

    public function test_poll_finds_single_eml(): void
    {
        $this->writeEml('msg1.eml', $this->sampleEml());
        $items = $this->poller()->poll();
        $this->assertCount(1, $items);
        $this->assertSame('vendor@example.com', $items[0]['email']->from);
        $this->assertCount(1, $items[0]['email']->allowedAttachments());
    }

    public function test_poll_finds_multiple_eml(): void
    {
        $this->writeEml('a.eml', $this->sampleEml('a@example.com'));
        $this->writeEml('b.eml', $this->sampleEml('b@example.com'));
        $this->writeEml('c.eml', $this->sampleEml('c@example.com'));
        $items = $this->poller()->poll();
        $this->assertCount(3, $items);
    }

    public function test_poll_ignores_non_eml_files(): void
    {
        $this->writeEml('msg.eml', $this->sampleEml());
        file_put_contents($this->dir . '/readme.txt', 'not an email');
        file_put_contents($this->dir . '/data.json', '{}');
        $items = $this->poller()->poll();
        $this->assertCount(1, $items);
    }

    // ── Processed move ───────────────────────────────────────────────────────

    public function test_mark_processed_moves_file(): void
    {
        $path = $this->writeEml('move-me.eml', $this->sampleEml());
        $this->assertFileExists($path);

        $this->poller()->markProcessed($path);

        $this->assertFileDoesNotExist($path);
        $this->assertFileExists($this->dir . '/processed/move-me.eml');
    }

    public function test_mark_processed_avoids_collision(): void
    {
        $poller = $this->poller();

        // Pre-create a processed file with the same name
        mkdir($this->dir . '/processed', 0755, true);
        file_put_contents($this->dir . '/processed/dup.eml', 'existing');

        $path = $this->writeEml('dup.eml', $this->sampleEml());
        $poller->markProcessed($path);

        // Original processed file must survive; new one stored under a unique name
        $this->assertSame('existing', file_get_contents($this->dir . '/processed/dup.eml'));
        $processedFiles = glob($this->dir . '/processed/*.eml') ?: [];
        $this->assertGreaterThanOrEqual(2, count($processedFiles));
    }

    public function test_processed_files_excluded_from_next_poll(): void
    {
        $path = $this->writeEml('once.eml', $this->sampleEml());
        $this->poller()->markProcessed($path);

        // processed/ is a subdir; glob('*.eml') on the top dir must not see it
        $this->assertCount(0, $this->poller()->poll());
    }

    // ── Missing dir ────────────────────────────────────────────────────────────

    public function test_poll_missing_dir_throws(): void
    {
        $poller = new MaildirPoller('/nonexistent/path/xyz', new MimeParser());
        $this->expectException(RuntimeException::class);
        $poller->poll();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
