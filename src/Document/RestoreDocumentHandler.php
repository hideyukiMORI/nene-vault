<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneVault\Auth\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RestoreDocumentHandler
{
    public function __construct(
        private RestoreDocumentUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = RequestContext::organizationId($request);

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $documentId = (string) ($params['id'] ?? '');

        $actorUserId = RequestContext::actorUserId($request);

        [$document, $version] = $this->useCase->execute($documentId, $orgId, $actorUserId);

        return $this->response->create(VaultDocumentPresenter::present($document, $version));
    }
}
