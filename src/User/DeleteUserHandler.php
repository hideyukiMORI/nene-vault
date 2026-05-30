<?php

declare(strict_types=1);

namespace NeneVault\User;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DeleteUserHandler
{
    public function __construct(
        private DeleteUserUseCaseInterface $useCase,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $claims = $request->getAttribute('nene2.auth.claims');
        $actorUserId = is_array($claims) && isset($claims['user_id']) ? (int) $claims['user_id'] : null;

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);

        $this->useCase->execute($id, $orgId, $actorUserId);

        return $this->responseFactory->createResponse(204);
    }
}
