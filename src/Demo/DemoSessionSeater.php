<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Demo\DemoConfig;
use Nene2\Demo\DemoSessionSeaterInterface;
use Nene2\Demo\ProvisionedDemoOrg;
use Nene2\Http\ClockInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Seats the visitor into a freshly provisioned demo org (`Nene2\Demo`
 * consumer, #141) — the Vault-specific auth handoff the framework module left
 * to the product, following the proven {@see SeatFixedDemoHandler} shape.
 *
 * Mints a normal access token for the org's throwaway ADMIN — same claims
 * shape and secret as {@see \NeneVault\Auth\LoginUseCase}; the token's
 * `org` claim is what {@see \NeneVault\Organization\Resolution\OrgResolverMiddleware}
 * resolves the tenant from — and serves a one-shot seat page whose nonce'd
 * inline script stores the SPA's `AuthSession` JSON in `sessionStorage`
 * (key `nene_vault_token`, the exact shape `frontend/src/entities/auth/model.ts`
 * persists after a login) and replaces into the app. Zero frontend change.
 *
 * The token TTL deliberately matches the demo org TTL ({@see DemoConfig::$ttlHours},
 * 3 h) rather than the 1 h login TTL: the disposable org lives exactly that
 * long, so the seat stays usable for the whole hands-on session and the stale
 * token dies with its data when the org is swept (semantics pinned by
 * {@see \NeneVault\Tests\Demo\DemoSessionSeaterTest}).
 *
 * The page carries its own per-response CSP — the app-wide policy would block
 * the inline script (the invoice #612 trap; the security-headers middleware
 * only fills absent headers). Only server-generated values are embedded;
 * nothing from the request is echoed.
 */
final readonly class DemoSessionSeater implements DemoSessionSeaterInterface
{
    private DemoEntryLog $entryLog;

    public function __construct(
        private DemoConfig $config,
        private DemoProvisionRegistry $registry,
        private TokenIssuerInterface $tokenIssuer,
        private Psr17Factory $psr17,
        private ClockInterface $clock,
        ?DemoEntryLog $entryLog = null,
    ) {
        $this->entryLog = $entryLog ?? new DemoEntryLog();
    }

    public function seatAndRedirect(ServerRequestInterface $request, ProvisionedDemoOrg $org): ResponseInterface
    {
        // Attribution layer 1 (#184): record channel/campaign here — the last
        // moment they exist — because the client-side `location.replace('/')`
        // below drops the query and the browser's next request to `/` is
        // same-origin (no UTM, a self Referer). No PII: only Referer + utm_* +
        // the disposable slug are logged; never the client IP.
        $this->entryLog->record($request, $org->slug);

        $email = $this->registry->adminEmail($org->orgId) ?? DemoOrgProvisioner::adminEmail($org->slug);
        $now = $this->clock->now()->getTimestamp();

        $token = $this->tokenIssuer->issue([
            'sub' => $org->adminUserId,
            'role' => 'admin',
            'org' => $org->orgId,
            'iat' => $now,
            'exp' => $now + $this->config->ttlHours * 3600,
        ]);

        $session = json_encode([
            'token' => $token,
            'userId' => $org->adminUserId,
            'email' => $email,
            'role' => 'admin',
            'orgId' => $org->orgId,
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        $nonce = base64_encode(random_bytes(18));

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="ja">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>NeNe Vault — デモを準備しています…</title>
        </head>
        <body>
        <noscript><p>デモの開始には JavaScript が必要です。ブラウザの JavaScript を有効にして、もう一度お試しください。</p></noscript>
        <p>デモを準備しています…</p>
        <script nonce="{$nonce}">
        sessionStorage.setItem('nene_vault_token', JSON.stringify({$session}));
        location.replace('/');
        </script>
        </body>
        </html>
        HTML;

        $response = $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Content-Security-Policy', "default-src 'none'; script-src 'nonce-{$nonce}'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'")
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer');
        $response->getBody()->write($html);

        return $response;
    }
}
