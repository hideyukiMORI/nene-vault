<?php

declare(strict_types=1);

namespace NeneVault\Demo;

/**
 * File-backed sink for {@see DemoEntryLog} (#192): appends one line per demo
 * entry to `<baseDir>/demo-entry.log` instead of the default `error_log`.
 *
 * Exists because on the Tier A shared-hosting target (HETEML), `error_log`
 * output lands in the hosting control panel's log — invisible over SSH — so
 * precise UTM/channel analysis of demo entries isn't possible there. `var/`
 * is the writable runtime directory the deployment already relies on (same
 * resolution as {@see FileRateLimitStorage}, #141): `RuntimeServiceProvider`'s
 * project root plus `/var`.
 *
 * Best-effort like its sibling: when the file cannot be opened or the write
 * fails, the line falls back to `error_log` so a line is never silently
 * dropped, it just loses the SSH-visible destination for that one line.
 */
final readonly class FileDemoEntryLogSink
{
    private const string LOG_FILENAME = 'demo-entry.log';

    public function __construct(
        private string $baseDir,
    ) {
    }

    public function __invoke(string $line): void
    {
        if (!is_dir($this->baseDir)) {
            @mkdir($this->baseDir, 0o775, true);
        }

        $handle = @fopen($this->baseDir . '/' . self::LOG_FILENAME, 'ab');

        if ($handle === false) {
            error_log($line);

            return;
        }

        try {
            flock($handle, LOCK_EX);
            $written = fwrite($handle, $line . PHP_EOL);
            fflush($handle);

            if ($written === false) {
                error_log($line);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
