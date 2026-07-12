<?php

declare(strict_types=1);

use Nene2\Http\ResponseEmitter;
use NeneVault\Http\AuthorizationHeaderFallback;
use NeneVault\Http\RuntimeContainerFactory;
use NeneVault\Http\SpaShell;
use NeneVault\Support\EnvFileLoader;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Server\RequestHandlerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

// Tier A: .env values must reach raw getenv() readers (ORG_SLUG tenant
// resolution etc.) — shared hosting provides no process env (#124).
EnvFileLoader::load(dirname(__DIR__));

$container = (new RuntimeContainerFactory(dirname(__DIR__)))->create();
$psr17Factory = $container->get(Psr17Factory::class);
assert($psr17Factory instanceof Psr17Factory);
$serverRequestCreator = new ServerRequestCreator(
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
    $psr17Factory,
);

$request = $serverRequestCreator->fromGlobals();

// Shared-hosting front proxies (HETEML) strip the Authorization header; adopt
// the SPA's X-Authorization mirror when the standard header is absent (#118).
$request = AuthorizationHeaderFallback::apply($request);

// SPA shell — served *before* the router so it bypasses the security-headers
// middleware and stays byte-identical to the static file it replaced. A non-API
// GET/HEAD returns the built admin shell (`public_html/index.html`), which also
// serves SPA deep-links / F5. On the disposable-demo host — and only there —
// DEMO_ANALYTICS_ENDPOINT is set, and the shell then carries the env-gated
// cookieless analytics beacon plus a CSP widened for the analytics origin
// (#184). Every other install leaves the env unset, so the shell body is
// byte-identical and no CSP header is set. The origin literal lives only in the
// demo host's `.env` — never in `.env.example` or the committed frontend.
$response = null;
$method = $request->getMethod();
$path = $request->getUri()->getPath();
$isApiPath = $path === '/admin' || str_starts_with($path, '/admin/')
    || $path === '/health' || str_starts_with($path, '/health/')
    || $path === '/demo' || str_starts_with($path, '/demo/');

if (($method === 'GET' || $method === 'HEAD') && !$isApiPath) {
    $analyticsEndpointRaw = $_ENV['DEMO_ANALYTICS_ENDPOINT'] ?? getenv('DEMO_ANALYTICS_ENDPOINT');
    $analyticsEndpoint = is_string($analyticsEndpointRaw) && $analyticsEndpointRaw !== '' ? $analyticsEndpointRaw : null;

    $response = (new SpaShell(
        dirname(__DIR__) . '/public_html/index.html',
        $psr17Factory,
        $psr17Factory,
        $analyticsEndpoint,
    ))->serve();
}

if ($response === null) {
    $application = $container->get(RequestHandlerInterface::class);
    assert($application instanceof RequestHandlerInterface);
    $response = $application->handle($request);
}

$emitter = $container->get(ResponseEmitter::class);
assert($emitter instanceof ResponseEmitter);
$emitter->emit($response);
