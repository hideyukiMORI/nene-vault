<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Demo\DemoConfig;
use Nene2\Http\ClockInterface;
use NeneVault\Auth\Role;
use NeneVault\Auth\UserRepositoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Auto-login for the fixed demo organization (#127, viewer-scoped per #130):
 * `GET /demo/standard` mints a normal 24 h access token for the seeded demo
 * VIEWER and serves a
 * one-shot seat page whose nonce'd inline script stores the SPA's
 * `AuthSession` JSON in `localStorage` and replaces into the app — the
 * invoice/clear "open one URL, land signed in" experience, without the
 * disposable-org module (blocked on host-based tenant resolution, #118).
 *
 * No tenancy change is needed: in `single` mode every request already
 * resolves to the served org, so a token whose `org_id` is that org passes
 * the capability org-scope check.
 *
 * Fail-close: 404 while `DEMO_MODE` is off, and 404 when the demo viewer
 * account is absent (not a demo deployment). The shared-org admin token this
 * page must NEVER mint again would make the URL a public upload endpoint
 * (#130) — the disposable-org adoption is the real fix. The page carries its own
 * per-response CSP — the app-wide policy would block the inline script (the
 * trap invoice hit; the security-headers middleware only fills absent
 * headers). Only server-generated values are embedded; nothing from the
 * request is echoed.
 */
final readonly class SeatFixedDemoHandler
{
    public const string DEMO_VIEWER_EMAIL = 'demo-viewer@nene-vault.dev';

    private const int TOKEN_TTL_SECONDS = 86400; // mirror LoginUseCase

    public function __construct(
        private DemoConfig $config,
        private UserRepositoryInterface $users,
        private TokenIssuerInterface $tokenIssuer,
        private Psr17Factory $psr17,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->demoMode) {
            return $this->notFound();
        }

        // Seat as VIEWER (#130): every visitor shares the one fixed org, so a
        // public admin token would be a public upload endpoint. Read covers
        // the showcase (search / SHA-256 / audit / export); the upload demo
        // uses the hand-out admin credentials on the login form.
        $viewer = $this->users->findByEmail(self::DEMO_VIEWER_EMAIL);

        if ($viewer === null || $viewer->organizationId === null || Role::tryFrom($viewer->role) !== Role::Viewer) {
            return $this->notFound();
        }

        $now = $this->clock->now()->getTimestamp();
        $token = $this->tokenIssuer->issue([
            'sub' => $viewer->email,
            'user_id' => $viewer->id,
            'role' => $viewer->role,
            'org_id' => $viewer->organizationId,
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL_SECONDS,
        ]);

        $session = json_encode([
            'token' => $token,
            'userId' => $viewer->id,
            'email' => $viewer->email,
            'role' => $viewer->role,
            'orgId' => $viewer->organizationId,
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
        localStorage.setItem('nene_vault_token', JSON.stringify({$session}));
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

    private function notFound(): ResponseInterface
    {
        $response = $this->psr17->createResponse(404)
            ->withHeader('Content-Type', 'application/problem+json; charset=utf-8');
        $response->getBody()->write((string) json_encode([
            'type' => 'https://nene-vault.dev/problems/not-found',
            'title' => 'Not Found',
            'status' => 404,
        ], JSON_UNESCAPED_SLASHES));

        return $response;
    }
}
