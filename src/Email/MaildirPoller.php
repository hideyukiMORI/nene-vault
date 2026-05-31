<?php

declare(strict_types=1);

namespace NeneVault\Email;

use RuntimeException;

/**
 * Scans a directory for .eml files (maildir dropbox or MTA delivery).
 *
 * Operators can configure their MTA (Postfix, Sendmail, etc.) or a mail
 * forwarding rule to deliver incoming emails as .eml files to the watched
 * directory. The poller reads new files, parses them, and optionally moves
 * them to a processed/ subdirectory.
 *
 * Environment:
 *   NENE_VAULT_EMAIL_MAILDIR   Directory to scan (required)
 */
final class MaildirPoller
{
    public function __construct(
        private readonly string $maildir,
        private readonly MimeParser $parser,
    ) {
    }

    /**
     * Yield InboundEmail objects for each unprocessed .eml file.
     * Moves processed files to {maildir}/processed/.
     *
     * @return list<array{email: InboundEmail, path: string}>
     */
    public function poll(): array
    {
        if (!is_dir($this->maildir)) {
            throw new RuntimeException('Email maildir does not exist: ' . $this->maildir);
        }

        $files = glob($this->maildir . '/*.eml') ?: [];
        $results = [];

        foreach ($files as $path) {
            $raw = file_get_contents($path);

            if ($raw === false) {
                continue;
            }

            $email = $this->parser->parse($raw);
            $results[] = ['email' => $email, 'path' => $path];
        }

        return $results;
    }

    public function markProcessed(string $path): void
    {
        $processedDir = $this->maildir . '/processed';

        if (!is_dir($processedDir)) {
            mkdir($processedDir, 0755, true);
        }

        $dest = $processedDir . '/' . basename($path);

        if (file_exists($dest)) {
            $dest = $processedDir . '/' . uniqid('', true) . '_' . basename($path);
        }

        rename($path, $dest);
    }
}
