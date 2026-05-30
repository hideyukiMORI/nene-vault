<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Nene2\Routing\Router;

final readonly class OrganizationRouteRegistrar
{
    public function __construct(
        private ListOrganizationsHandler $list,
        private GetOrganizationByIdHandler $get,
        private CreateOrganizationHandler $create,
        private UpdateOrganizationHandler $update,
        private DeleteOrganizationHandler $delete,
    ) {
    }

    public function register(Router $router): void
    {
        $router->get('/admin/organizations', $this->list->handle(...));
        $router->get('/admin/organizations/{id}', $this->get->handle(...));
        $router->post('/admin/organizations', $this->create->handle(...));
        $router->patch('/admin/organizations/{id}', $this->update->handle(...));
        $router->delete('/admin/organizations/{id}', $this->delete->handle(...));
    }
}
