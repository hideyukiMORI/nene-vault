<?php

declare(strict_types=1);

namespace NeneVault\Export;

use Nene2\Routing\Router;

final readonly class ExportRouteRegistrar
{
    public function __construct(
        private ExportDocumentsHandler $export,
    ) {
    }

    public function register(Router $router): void
    {
        $router->post('/admin/vault/export', $this->export->handle(...));
    }
}
