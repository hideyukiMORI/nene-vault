<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

use Nene2\Routing\Router;

final readonly class VaultSettingsRouteRegistrar
{
    public function __construct(
        private GetVaultSettingsHandler $get,
        private UpdateVaultSettingsHandler $update,
    ) {
    }

    public function register(Router $router): void
    {
        $router->get('/admin/vault/settings', $this->get->handle(...));
        $router->patch('/admin/vault/settings', $this->update->handle(...));
    }
}
