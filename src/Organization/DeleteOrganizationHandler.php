<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Nene2\Routing\Router;
use NeneVault\Auth\RequestContext;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DeleteOrganizationHandler
{
    public function __construct(
        private DeleteOrganizationUseCaseInterface $useCase,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);
        $actorUserId = RequestContext::actorUserId($request);

        $this->useCase->execute($id, $actorUserId);

        return $this->responseFactory->createResponse(204);
    }
}
