<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Registers the fixed-demo seat route (#127). Public and gated at runtime by
 * DEMO_MODE inside {@see SeatFixedDemoHandler} (404 fail-close), so
 * registering it on a non-demo instance is safe. The path matches the
 * sibling demos' convention (`/demo/standard`) so the future disposable-org
 * flow can take the URL over without retraining anyone.
 */
final readonly class DemoRouteRegistrar
{
    public function __construct(
        private SeatFixedDemoHandler $handler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $handler = $this->handler;
        $router->get('/demo/standard', static fn (ServerRequestInterface $request) => $handler->handle($request));
    }
}
