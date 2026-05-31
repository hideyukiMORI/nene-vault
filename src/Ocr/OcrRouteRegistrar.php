<?php

declare(strict_types=1);

namespace NeneVault\Ocr;

use Nene2\Routing\Router;

final readonly class OcrRouteRegistrar
{
    public function __construct(
        private OcrSuggestHandler $handler,
    ) {
    }

    public function register(Router $router): void
    {
        $router->get(
            '/admin/vault/documents/{id}/ocr-suggest',
            $this->handler->handle(...),
        );
    }
}
