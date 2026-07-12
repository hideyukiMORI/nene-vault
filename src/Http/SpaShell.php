<?php

declare(strict_types=1);

namespace NeneVault\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Serves the built admin SPA shell (`public_html/index.html`) for non-API GET
 * requests, so the disposable-demo host can inject a cookieless analytics
 * beacon into it — env-gated and OSS-clean.
 *
 * Vault ships the shell as a **static file**; on OSS/self-hosted installs
 * `.htaccess` still hands `/` to this class only so the response is
 * byte-for-byte the static shell (no injection, no CSP header — Apache's
 * default applies unchanged). The shell is served *before* the router runs, so
 * it never passes through the app's security-headers middleware: with analytics
 * off the shell carries exactly the headers the static file did (none of its
 * own CSP), preserving the current behaviour.
 *
 * ## Optional demo analytics (env-gated, OSS-clean)
 *
 * When — and only when — `$analyticsEndpoint` is a valid origin (wired from the
 * `DEMO_ANALYTICS_ENDPOINT` env, set on the disposable-demo host alone), a
 * cookieless GoatCounter beacon is injected just before `</head>`
 * (`<script data-goatcounter="…/count" async src="…/count.js">`). Because that
 * beacon loads a cross-origin script and posts to a cross-origin collector,
 * this response then also carries its own `Content-Security-Policy` widening
 * `script-src` / `connect-src` / `img-src` to the analytics origin.
 *
 * The CSP intentionally keeps the Google Fonts origins the shell already loads
 * (`fonts.googleapis.com` stylesheet, `fonts.gstatic.com` fonts) — dropping
 * them would break the shell's typography (the clear #277-era CSP trap). It is
 * emitted *only* when analytics is enabled: with the env unset no CSP is set at
 * all, so a self-hosted install is never given a policy it did not have before.
 *
 * The OSS release ships **no** analytics origin anywhere: the endpoint literal
 * lives only in the demo host's `.env`, never in `.env.example`, the committed
 * React source/build, or `.htaccess`.
 */
final readonly class SpaShell
{
    /**
     * The Content-Security-Policy emitted only when demo analytics is on. It
     * keeps the shell's cross-origin Google Fonts (style/font) and adds the
     * analytics origin to script/connect/img. `%1$s` is the validated origin.
     */
    private const string ANALYTICS_CSP_TEMPLATE = "default-src 'self'; script-src 'self' %1\$s; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: %1\$s; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' %1\$s; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    /** Normalised analytics origin (e.g. `https://stats.example.test`), or null when disabled. */
    private ?string $analyticsEndpoint;

    public function __construct(
        private string $shellPath,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        ?string $analyticsEndpoint = null,
    ) {
        $this->analyticsEndpoint = self::normaliseEndpoint($analyticsEndpoint);
    }

    /**
     * Accepts an analytics endpoint only when it is a bare `http(s)://host[:port]`
     * origin — no path, query, fragment, whitespace or control characters. Any
     * trailing slash is trimmed. Anything else (including empty/unset) disables
     * analytics (fail-safe), so a fat-fingered env can never inject markup or a
     * malformed CSP / header. The value is operator-controlled (server `.env`),
     * but validating it keeps header/attribute construction provably safe.
     */
    private static function normaliseEndpoint(?string $endpoint): ?string
    {
        if ($endpoint === null) {
            return null;
        }

        $endpoint = rtrim(trim($endpoint), '/');

        if ($endpoint === '' || preg_match('#^https?://[A-Za-z0-9.\-]+(:\d+)?$#', $endpoint) !== 1) {
            return null;
        }

        return $endpoint;
    }

    /**
     * Returns the shell response, or null when the built shell is absent (e.g. a
     * backend-only checkout, or a dev instance whose frontend runs on Vite) so
     * the caller can fall back to the API router.
     *
     * With analytics disabled the body is the shell file verbatim and no CSP
     * header is set. With analytics enabled the beacon is injected and the
     * widened CSP is attached.
     */
    public function serve(): ?ResponseInterface
    {
        if (!is_file($this->shellPath)) {
            return null;
        }

        $html = file_get_contents($this->shellPath);

        if ($html === false) {
            return null;
        }

        $body = $this->analyticsEndpoint === null ? $html : $this->inject($html);

        $response = $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($body));

        if ($this->analyticsEndpoint !== null) {
            $response = $response->withHeader('Content-Security-Policy', $this->analyticsCsp());
        }

        return $response;
    }

    /**
     * Inserts the beacon immediately before the closing `</head>` so it follows
     * the font links. If the marker is absent (unexpected), the shell is served
     * unchanged (fail-safe — never emit a mangled document).
     */
    private function inject(string $html): string
    {
        return str_replace('</head>', $this->analyticsTag() . "\n  </head>", $html);
    }

    /**
     * The cookieless GoatCounter beacon. Both URLs derive from the single
     * validated origin; the origin is escaped for the HTML attribute context
     * (belt-and-suspenders — it is already validated to a bare origin).
     */
    private function analyticsTag(): string
    {
        $dataAttr = htmlspecialchars((string) $this->analyticsEndpoint . '/count', ENT_QUOTES);
        $src = htmlspecialchars((string) $this->analyticsEndpoint . '/count.js', ENT_QUOTES);

        return "  <script data-goatcounter=\"{$dataAttr}\" async src=\"{$src}\"></script>";
    }

    private function analyticsCsp(): string
    {
        return sprintf(self::ANALYTICS_CSP_TEMPLATE, $this->analyticsEndpoint);
    }
}
