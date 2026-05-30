<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
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
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $documentId = (string) ($params['id'] ?? '');

        $claims = $request->getAttribute('nene2.auth.claims');
        $actorUserId = is_array($claims) && isset($claims['user_id']) ? (int) $claims['user_id'] : null;

        [$document, $version] = $this->useCase->execute($documentId, $orgId, $actorUserId);

        return $this->response->create(VaultDocumentPresenter::present($document, $version));
    }
}
