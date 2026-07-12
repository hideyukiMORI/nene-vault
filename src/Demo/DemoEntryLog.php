<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Closure;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Attribution layer 1 (#184): records one line per demo entry with the referer
 * and UTM tags, at the last moment they exist.
 *
 * Both demo entrances land the visitor on the SPA at `/` via a client-side
 * `location.replace('/')` (see {@see DemoSessionSeater}, {@see SeatFixedDemoHandler}):
 * the query string with the UTM tags is dropped by that navigation and the
 * next request is same-origin (a self Referer, no UTM). So the entry handler is
 * the only place the channel/campaign can be captured server-side — the
 * client-side beacon (#184 ②) only sees post-landing browsing.
 *
 * No PII: only the Referer, the `utm_*` tags and the disposable/guided slug are
 * logged — never the client IP or any personal field. Values are sanitised
 * (control chars stripped, length-capped) so a crafted Referer / query cannot
 * forge log lines, and missing tags render as `-` so a UTM-less entry still
 * logs cleanly instead of breaking the landing.
 */
final readonly class DemoEntryLog
{
    /** @var Closure(string): void Where entry log lines go; defaults to `error_log`. */
    private Closure $sink;

    /**
     * @param (Closure(string): void)|null $sink Sink for the demo-entry line.
     *        Defaults to PHP's `error_log`; overridable in tests so the recorded
     *        line can be asserted without depending on the global `error_log` ini.
     */
    public function __construct(?Closure $sink = null)
    {
        $this->sink = $sink ?? static function (string $line): void {
            error_log($line);
        };
    }

    /**
     * Emits one `error_log` line for a demo entry.
     *
     * @param string $slug the disposable org slug (`/demo/standard`) or a fixed
     *                     label such as `guided` (`/demo/guided`)
     */
    public function record(ServerRequestInterface $request, string $slug): void
    {
        $query = $request->getQueryParams();

        $fields = [
            'slug' => $slug,
            'utm_source' => $query['utm_source'] ?? null,
            'utm_medium' => $query['utm_medium'] ?? null,
            'utm_campaign' => $query['utm_campaign'] ?? null,
            'referer' => $request->getHeaderLine('Referer'),
        ];

        $parts = [];

        foreach ($fields as $key => $value) {
            $parts[] = $key . '=' . self::sanitise(is_string($value) ? $value : null);
        }

        ($this->sink)('NeNe Vault: demo-entry ' . implode(' ', $parts));
    }

    /**
     * Renders a log field value: `-` when absent/empty, otherwise the value with
     * CR/LF and other control characters removed (log-injection defence) and
     * capped at 256 chars so a long crafted URL can't bloat the log.
     */
    private static function sanitise(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));

        if ($clean === '') {
            return '-';
        }

        return mb_substr($clean, 0, 256);
    }
}
