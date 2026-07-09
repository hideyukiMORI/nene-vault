<?php

declare(strict_types=1);

use Nene2\Http\ResponseEmitter;
use NeneVault\Http\AuthorizationHeaderFallback;
use NeneVault\Http\RuntimeContainerFactory;
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
$application = $container->get(RequestHandlerInterface::class);
assert($application instanceof RequestHandlerInterface);
$response = $application->handle($request);

$emitter = $container->get(ResponseEmitter::class);
assert($emitter instanceof ResponseEmitter);
$emitter->emit($response);
