<?php

declare(strict_types=1);

namespace NeneVault\User;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneVault\Auth\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetUserByIdHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);

        $user = $this->users->findById($id);

        if ($user === null || $user->organizationId !== $orgId) {
            throw new UserNotFoundException($id);
        }

        return $this->response->create(UserPresenter::present($user));
    }
}
