<?php

declare(strict_types=1);

namespace NeneVault\Support;

/**
 * Loads `.env` into the process environment at a config boundary (#124).
 *
 * Docker injects real environment variables, but Tier A shared hosting only
 * has the `.env` file — and while `Nene2\Config\ConfigLoader` parses it into
 * `AppConfig`, raw `getenv()` readers (tenant resolution's `ORG_SLUG`, CLI
 * tools) never see those values. This loader bridges the gap: values already
 * present in the real environment always win, so Docker behavior is
 * unchanged.
 */
final readonly class EnvFileLoader
{
    public static function load(string $projectRoot): void
    {
        $path = $projectRoot . '/.env';

        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // EnvironmentWriter quotes values when needed; strip one layer.
            if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
                $value = stripcslashes(substr($value, 1, -1));
            }

            if ($key === '' || getenv($key) !== false) {
                continue; // real environment wins
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}
