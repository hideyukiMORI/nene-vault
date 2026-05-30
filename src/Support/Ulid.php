<?php

declare(strict_types=1);

namespace NeneVault\Support;

/**
 * Minimal ULID generator (Crockford base32, 26 chars).
 *
 * 48-bit millisecond timestamp + 80-bit randomness. Lexicographically sortable.
 * Used for vault_document and document_version IDs (see docs/terms.md §7).
 */
final class Ulid
{
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $timeMs = (int) (microtime(true) * 1000);

        // 48-bit timestamp → 10 chars
        $timeChars = '';
        for ($i = 0; $i < 10; $i++) {
            $mod = $timeMs % 32;
            $timeChars = self::ENCODING[$mod] . $timeChars;
            $timeMs = intdiv($timeMs, 32);
        }

        // 80-bit randomness → 16 chars
        $randChars = '';
        for ($i = 0; $i < 16; $i++) {
            $randChars .= self::ENCODING[random_int(0, 31)];
        }

        return $timeChars . $randChars;
    }
}
