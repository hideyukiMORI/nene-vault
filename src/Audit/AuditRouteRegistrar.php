<?php

declare(strict_types=1);

namespace NeneVault\Audit;

use Nene2\Routing\Router;

final readonly class AuditRouteRegistrar
{
    public function __construct(
        private ListAuditEventsHandler $listHandler,
    ) {
    }

    public function register(Router $router): void
    {
        $router->get('/admin/audit-events', $this->listHandler->handle(...));
    }
}
