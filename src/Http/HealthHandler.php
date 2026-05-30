<?php

declare(strict_types=1);

namespace NeneVault\Http;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HealthHandler
{
    public function __construct(
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response->create(['status' => 'ok', 'service' => 'nene-vault']);
    }
}
