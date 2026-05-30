<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Routing\Router;

final readonly class DocumentRouteRegistrar
{
    public function __construct(
        private UploadDocumentHandler $upload,
        private SearchDocumentsHandler $search,
        private GetDocumentByIdHandler $get,
    ) {
    }

    public function register(Router $router): void
    {
        $router->get('/admin/vault/documents', $this->search->handle(...));
        $router->post('/admin/vault/documents', $this->upload->handle(...));
        $router->get('/admin/vault/documents/{id}', $this->get->handle(...));
    }
}
