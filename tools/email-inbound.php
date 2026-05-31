<?php

/**
 * NeNe Vault — Email inbound processor
 *
 * Scans a maildir directory for .eml files and uploads PDF/JPEG/PNG
 * attachments to the vault via the REST API.
 *
 * Environment variables (set in .env or export before running):
 *   NENE_VAULT_EMAIL_MAILDIR        Directory containing .eml files (required)
 *   NENE_VAULT_EMAIL_API_BASE_URL   API base URL (default: http://localhost:8080)
 *   NENE_VAULT_EMAIL_API_TOKEN      Bearer token for API auth (required)
 *   NENE_VAULT_EMAIL_CATEGORY       Document category (default: invoice_received)
 *   NENE_VAULT_EMAIL_DRY_RUN        Set to 'true' to parse only without uploading
 *
 * Usage (manual):
 *   php tools/email-inbound.php
 *
 * Usage (cron — every 5 minutes):
 *   * /5 * * * * /usr/bin/php /path/to/nene-vault/tools/email-inbound.php >> /var/log/nene-vault-email.log 2>&1
 *
 * MTA delivery (Postfix example):
 *   Create /etc/aliases or .forward to pipe to a script that saves .eml files:
 *     vault: "|/usr/bin/tee /var/mail/vault/$(date +%s%N).eml"
 *   Then set NENE_VAULT_EMAIL_MAILDIR=/var/mail/vault
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use NeneVault\Email\MaildirPoller;
use NeneVault\Email\MimeParser;

$maildir = (string) (getenv('NENE_VAULT_EMAIL_MAILDIR') ?: '');
$apiBase = rtrim((string) (getenv('NENE_VAULT_EMAIL_API_BASE_URL') ?: 'http://localhost:8080'), '/');
$token = (string) (getenv('NENE_VAULT_EMAIL_API_TOKEN') ?: '');
$category = (string) (getenv('NENE_VAULT_EMAIL_CATEGORY') ?: 'invoice_received');
$dryRun = getenv('NENE_VAULT_EMAIL_DRY_RUN') === 'true';

// ── Validation ────────────────────────────────────────────────────────────────

if ($maildir === '') {
    fwrite(STDERR, "[email-inbound] ERROR: NENE_VAULT_EMAIL_MAILDIR is not set.\n");
    exit(1);
}

if (!$dryRun && $token === '') {
    fwrite(STDERR, "[email-inbound] ERROR: NENE_VAULT_EMAIL_API_TOKEN is not set.\n");
    exit(1);
}

// ── Poll ──────────────────────────────────────────────────────────────────────

$poller = new MaildirPoller($maildir, new MimeParser());

try {
    $items = $poller->poll();
} catch (Throwable $e) {
    fwrite(STDERR, '[email-inbound] ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($items === []) {
    echo "[email-inbound] No new .eml files found in {$maildir}\n";
    exit(0);
}

echo '[email-inbound] Found ' . count($items) . " email(s) to process.\n";

$uploaded = 0;
$skipped = 0;
$errors = 0;

foreach ($items as ['email' => $email, 'path' => $path]) {
    $allowed = $email->allowedAttachments();

    echo sprintf(
        "[email-inbound] Processing: %s (from=%s, attachments=%d, allowed=%d)\n",
        basename($path),
        $email->from,
        count($email->attachments),
        count($allowed),
    );

    if ($allowed === []) {
        echo "[email-inbound]   No allowed attachments — skipping.\n";
        $skipped++;
        $poller->markProcessed($path);
        continue;
    }

    foreach ($allowed as $attachment) {
        if ($dryRun) {
            echo sprintf(
                "[email-inbound]   [DRY-RUN] Would upload: %s (%s, %d bytes)\n",
                $attachment->filename,
                $attachment->mimeType,
                strlen($attachment->bytes),
            );
            $uploaded++;
            continue;
        }

        $result = uploadAttachment(
            apiBase: $apiBase,
            token: $token,
            attachment: $attachment,
            email: $email,
            category: $category,
        );

        if ($result['ok']) {
            echo sprintf(
                "[email-inbound]   Uploaded: %s → document %s\n",
                $attachment->filename,
                $result['id'] ?? '(unknown)',
            );
            $uploaded++;
        } else {
            fwrite(STDERR, sprintf(
                "[email-inbound]   ERROR uploading %s: %s\n",
                $attachment->filename,
                $result['error'] ?? 'unknown error',
            ));
            $errors++;
        }
    }

    $poller->markProcessed($path);
}

echo sprintf(
    "[email-inbound] Done. uploaded=%d skipped=%d errors=%d\n",
    $uploaded,
    $skipped,
    $errors,
);

exit($errors > 0 ? 1 : 0);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * @return array{ok: bool, id?: string, error?: string}
 */
function uploadAttachment(
    string $apiBase,
    string $token,
    \NeneVault\Email\EmailAttachment $attachment,
    \NeneVault\Email\InboundEmail $email,
    string $category,
): array {
    $tmp = tempnam(sys_get_temp_dir(), 'vault_email_');

    if ($tmp === false) {
        return ['ok' => false, 'error' => 'Failed to create temp file.'];
    }

    file_put_contents($tmp, $attachment->bytes);

    try {
        $ch = curl_init($apiBase . '/admin/vault/documents');

        $postFields = [
            'file'             => new CURLFile($tmp, $attachment->mimeType, $attachment->filename),
            'counterparty_name' => extractCounterparty($email->from),
            'category'         => $category,
            'source'           => 'email_inbound',
        ];

        if ($email->date !== null) {
            $postFields['transaction_date'] = $email->date;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body)) {
            return ['ok' => false, 'error' => 'curl returned non-string response.'];
        }

        if ($status === 201) {
            $data = json_decode($body, true);
            return ['ok' => true, 'id' => is_array($data) ? (string) ($data['id'] ?? '') : ''];
        }

        return ['ok' => false, 'error' => "HTTP {$status}: {$body}"];
    } finally {
        @unlink($tmp);
    }
}

/**
 * Extract a display name or email address from a From: header value.
 * "ACME Corp <billing@acme.example>" → "ACME Corp"
 * "billing@acme.example"             → "billing@acme.example"
 */
function extractCounterparty(string $from): string
{
    if (preg_match('/^([^<]+)</', $from, $m)) {
        $name = trim($m[1], ' "\'');

        if ($name !== '') {
            return $name;
        }
    }

    if (preg_match('/<([^>]+)>/', $from, $m)) {
        return $m[1];
    }

    return $from !== '' ? $from : 'Unknown';
}
