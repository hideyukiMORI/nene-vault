<?php

declare(strict_types=1);

namespace NeneVault\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Terminal handler spy for middleware tests: records the request it received
 * (null when the middleware short-circuited) and answers a plain 200.
 */
final class SpyRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $captured = null;

    public function __construct(private readonly Psr17Factory $psr17)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->captured = $request;

        return $this->psr17->createResponse(200);
    }
}
