<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Demo\DemoConfig;
use NeneVault\Auth\Role;
use NeneVault\Auth\UserRepositoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Auto-login for the fixed demo organization (#127): `GET /demo/standard`
 * mints a normal 24 h access token for the seeded demo admin and serves a
 * one-shot seat page whose nonce'd inline script stores the SPA's
 * `AuthSession` JSON in `localStorage` and replaces into the app — the
 * invoice/clear "open one URL, land signed in" experience, without the
 * disposable-org module (blocked on host-based tenant resolution, #118).
 *
 * No tenancy change is needed: in `single` mode every request already
 * resolves to the served org, so a token whose `org_id` is that org passes
 * the capability org-scope check.
 *
 * Fail-close: 404 while `DEMO_MODE` is off, and 404 when the demo admin
 * account is absent (not a demo deployment). The page carries its own
 * per-response CSP — the app-wide policy would block the inline script (the
 * trap invoice hit; the security-headers middleware only fills absent
 * headers). Only server-generated values are embedded; nothing from the
 * request is echoed.
 */
final readonly class SeatFixedDemoHandler
{
    public const string DEMO_ADMIN_EMAIL = 'demo-admin@nene-vault.dev';

    private const int TOKEN_TTL_SECONDS = 86400; // mirror LoginUseCase

    public function __construct(
        private DemoConfig $config,
        private UserRepositoryInterface $users,
        private TokenIssuerInterface $tokenIssuer,
        private Psr17Factory $psr17,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->config->demoMode) {
            return $this->notFound();
        }

        $admin = $this->users->findByEmail(self::DEMO_ADMIN_EMAIL);

        if ($admin === null || $admin->organizationId === null || Role::tryFrom($admin->role) !== Role::Admin) {
            return $this->notFound();
        }

        $now = time();
        $token = $this->tokenIssuer->issue([
            'sub' => $admin->email,
            'user_id' => $admin->id,
            'role' => $admin->role,
            'org_id' => $admin->organizationId,
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL_SECONDS,
        ]);

        $session = json_encode([
            'token' => $token,
            'userId' => $admin->id,
            'email' => $admin->email,
            'role' => $admin->role,
            'orgId' => $admin->organizationId,
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
