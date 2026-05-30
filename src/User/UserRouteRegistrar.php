<?php

declare(strict_types=1);

namespace NeneVault\User;

use Nene2\Routing\Router;

final readonly class UserRouteRegistrar
{
    public function __construct(
        private ListUsersHandler $list,
        private GetUserByIdHandler $get,
        private CreateUserHandler $create,
        private UpdateUserHandler $update,
        private DeleteUserHandler $delete,
    ) {
    }

    public function register(Router $router): void
    {
        $router->get('/admin/users', $this->list->handle(...));
        $router->get('/admin/users/{id}', $this->get->handle(...));
        $router->post('/admin/users', $this->create->handle(...));
        $router->patch('/admin/users/{id}', $this->update->handle(...));
        $router->delete('/admin/users/{id}', $this->delete->handle(...));
    }
}
