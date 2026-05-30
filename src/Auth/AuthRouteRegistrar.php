<?php

declare(strict_types=1);

namespace NeneVault\Auth;

use Nene2\Routing\Router;

final readonly class AuthRouteRegistrar
{
    public function __construct(
        private LoginHandler $loginHandler,
    ) {
    }

    public function register(Router $router): void
    {
        $router->post('/admin/auth/login', $this->loginHandler->handle(...));
    }
}
