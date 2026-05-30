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
        private UpdateDocumentMetadataHandler $updateMetadata,
        private VoidDocumentHandler $void,
        private RestoreDocumentHandler $restore,
        private GetDocumentHistoryHandler $history,
    ) {
    }

    public function register(Router $router): void
    {
        $router->get('/admin/vault/documents', $this->search->handle(...));
        $router->post('/admin/vault/documents', $this->upload->handle(...));
        $router->get('/admin/vault/documents/{id}', $this->get->handle(...));
        $router->patch('/admin/vault/documents/{id}/metadata', $this->updateMetadata->handle(...));
        $router->post('/admin/vault/documents/{id}/void', $this->void->handle(...));
        $router->post('/admin/vault/documents/{id}/restore', $this->restore->handle(...));
        $router->get('/admin/vault/documents/{id}/history', $this->history->handle(...));
    }
}
