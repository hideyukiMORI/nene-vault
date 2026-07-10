<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers the fixed-demo seat route (#127, moved to `/demo/guided` in #141).
 *
 * `/demo/standard` — the distribution link — now starts a disposable org
 * ({@see \Nene2\Demo\StartDisposableDemoHandler} via `/demo/{template}`);
 * the shared, nightly-reseeded showcase org keeps its viewer seat here for
 * guided walkthroughs and the README screenshots. The static path wins over
 * the parameterized demo route by router precedence (fewer parameters sort
 * first), so `guided` never reaches the template parser.
 *
 * Public and gated at runtime by DEMO_MODE inside {@see SeatFixedDemoHandler}
 * (404 fail-close), so registering it on a non-demo instance is safe.
 */
final readonly class GuidedDemoRouteRegistrar
{
    public function __construct(
        private SeatFixedDemoHandler $handler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $handler = $this->handler;
        $router->get('/demo/guided', static fn (ServerRequestInterface $request) => $handler->handle($request));
    }
}
