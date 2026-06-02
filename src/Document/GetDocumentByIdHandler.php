<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneVault\Auth\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetDocumentByIdHandler
{
    public function __construct(
        private GetDocumentByIdUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = RequestContext::organizationId($request);

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (string) ($params['id'] ?? '');

        [$document, $version] = $this->useCase->execute($id, $orgId);

        return $this->response->create(VaultDocumentPresenter::present($document, $version));
    }
}
