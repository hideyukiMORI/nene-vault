<?php

declare(strict_types=1);

namespace NeneVault\User;

use Nene2\Http\JsonResponseFactory;
use NeneVault\Auth\User;
use NeneVault\Auth\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListUsersHandler
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

        $params = $request->getQueryParams();
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $all = $this->users->listByOrganizationId($orgId);
        $total = count($all);
        $page = array_slice($all, $offset, $limit);

        return $this->response->create([
            'items'  => array_map(static fn (User $u) => UserPresenter::present($u), $page),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }
}
