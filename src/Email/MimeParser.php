<?php

declare(strict_types=1);

namespace NeneVault\Email;

/**
 * Lightweight RFC 2822 / MIME parser for extracting attachments from .eml files.
 *
 * Supports:
 *   - multipart/mixed, multipart/related, multipart/alternative
 *   - Content-Transfer-Encoding: base64, quoted-printable, 7bit, 8bit
 *   - RFC 2047 encoded-word headers (=?charset?B/Q?...?=)
 *   - Nested multipart (recursive)
 *
 * No external dependencies; no ext-imap required.
 */
final class MimeParser
{
    public function parse(string $raw): InboundEmail
    {
        [$headerBlock, $body] = $this->splitHeadersBody($raw);
        $headers = $this->parseHeaders($headerBlock);

        $messageId = $this->header($headers, 'message-id') ?? ('generated-' . hash('sha256', $raw));
        $from = $this->header($headers, 'from') ?? '';
        $subject = $this->decodeWords($this->header($headers, 'subject') ?? '');
        $date = $this->header($headers, 'date');

        $attachments = $this->extractAttachments($headers, $body);

        return new InboundEmail(
            messageId: trim($messageId, '<> '),
            from: $from,
            subject: $subject,
            date: $date !== null ? $this->parseDate($date) : null,
            attachments: $attachments,
        );
    }

    // ── Part extraction ────────────────────────────────────────────────────────

    /**
     * @param array<string, list<string>> $headers
     * @return list<EmailAttachment>
     */
    private function extractAttachments(array $headers, string $body): array
    {
        $contentType = $this->header($headers, 'content-type') ?? 'text/plain';
        $mimeType = $this->mimeType($contentType);

        if (str_starts_with($mimeType, 'multipart/')) {
            $boundary = $this->param($contentType, 'boundary');

            if ($boundary === null) {
                return [];
            }

            return $this->extractFromMultipart($body, $boundary);
        }

        return $this->extractFromPart($headers, $body);
    }

    /**
     * @return list<EmailAttachment>
     */
    private function extractFromMultipart(string $body, string $boundary): array
    {
        $attachments = [];
        $parts = $this->splitMultipart($body, $boundary);

        foreach ($parts as $part) {
            [$partHeaderBlock, $partBody] = $this->splitHeadersBody($part);
            $partHeaders = $this->parseHeaders($partHeaderBlock);
            $partAttachments = $this->extractAttachments($partHeaders, $partBody);
            $attachments = array_merge($attachments, $partAttachments);
        }

        return $attachments;
    }

    /**
     * @param array<string, list<string>> $headers
     * @return list<EmailAttachment>
     */
    private function extractFromPart(array $headers, string $body): array
    {
        $contentType = $this->header($headers, 'content-type') ?? 'text/plain';
        $disposition = $this->header($headers, 'content-disposition') ?? '';
        $mimeType = $this->mimeType($contentType);

        // Only collect if it's an attachment (by disposition or by type)
        $isAttachment = str_contains(strtolower($disposition), 'attachment')
            || in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true);

        if (!$isAttachment) {
            return [];
        }

        $filename = $this->param($disposition, 'filename')
            ?? $this->param($contentType, 'name')
            ?? 'attachment';

        $filename = $this->decodeWords($filename);

        $encoding = strtolower($this->header($headers, 'content-transfer-encoding') ?? '7bit');
        $decoded = $this->decodeBody($body, $encoding);

        return [new EmailAttachment(
            filename: $filename,
            mimeType: $mimeType,
            bytes: $decoded,
        )];
    }

    // ── MIME helpers ───────────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    private function splitMultipart(string $body, string $boundary): array
    {
        $parts = [];
        $pattern = '/--' . preg_quote($boundary, '/') . '(?:--)?[\r\n]*/';
        $segments = preg_split($pattern, $body);

        if ($segments === false) {
            return [];
        }

        foreach ($segments as $segment) {
            $trimmed = trim($segment);

            if ($trimmed !== '' && $trimmed !== '--') {
                $parts[] = $trimmed;
            }
        }

        return $parts;
    }

    private function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64'           => base64_decode(preg_replace('/\s+/', '', $body) ?? '', true) ?: '',
            'quoted-printable' => quoted_printable_decode($body),
            default            => $body,
        };
    }

    // ── Header parsing ─────────────────────────────────────────────────────────

    /**
     * @return array{string, string}
     */
    private function splitHeadersBody(string $raw): array
    {
        $pos = strpos($raw, "\r\n\r\n");

        if ($pos !== false) {
            return [substr($raw, 0, $pos), substr($raw, $pos + 4)];
        }

        $pos = strpos($raw, "\n\n");

        if ($pos !== false) {
            return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
        }

        return [$raw, ''];
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseHeaders(string $block): array
    {
        // Unfold continuation lines
        $block = preg_replace("/\r\n([ \t])/", ' $1', $block) ?? $block;
        $block = preg_replace("/\n([ \t])/", ' $1', $block) ?? $block;

        $headers = [];
        $lines = preg_split('/\r?\n/', $block) ?: [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $colon = strpos($line, ':');

            if ($colon === false) {
                continue;
            }

            $name = strtolower(trim(substr($line, 0, $colon)));
            $value = trim(substr($line, $colon + 1));

            if (!isset($headers[$name])) {
                $headers[$name] = [];
            }

            $headers[$name][] = $value;
        }

        return $headers;
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function header(array $headers, string $name): ?string
    {
        return isset($headers[$name][0]) ? $headers[$name][0] : null;
    }

    private function mimeType(string $contentType): string
    {
        $parts = explode(';', $contentType, 2);

        return strtolower(trim($parts[0]));
    }

    private function param(string $headerValue, string $name): ?string
    {
        $pattern = '/' . preg_quote($name, '/') . '\s*=\s*"?([^";]+)"?/i';

        if (preg_match($pattern, $headerValue, $m)) {
            return trim($m[1], '"');
        }

        return null;
    }

    // ── RFC 2047 encoded-word ─────────────────────────────────────────────────

    private function decodeWords(string $s): string
    {
        return (string) preg_replace_callback(
            '/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/',
            static function (array $m): string {
                $charset = $m[1];
                $encoding = strtoupper($m[2]);
                $encoded = $m[3];

                $decoded = match ($encoding) {
                    'B' => (base64_decode($encoded, true) ?: $encoded),
                    default => quoted_printable_decode(str_replace('_', ' ', $encoded)),
                };

                if (strtolower($charset) !== 'utf-8') {
                    $converted = mb_convert_encoding($decoded, 'UTF-8', $charset);
                    $decoded = $converted !== false ? $converted : $decoded;
                }

                return $decoded;
            },
            $s,
        ) ?: $s;
    }

    // ── Date parsing ──────────────────────────────────────────────────────────

    private function parseDate(string $dateStr): ?string
    {
        // Use DateTimeImmutable to respect the timezone in the Date header.
        // This preserves the calendar date as seen in the sender's timezone.
        try {
            $dt = new \DateTimeImmutable($dateStr);
            return $dt->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
