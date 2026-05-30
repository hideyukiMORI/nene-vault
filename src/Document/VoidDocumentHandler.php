<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class VoidDocumentHandler
{
    public function __construct(
        private VoidDocumentUseCaseInterface $useCase,
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

        $body = JsonRequestBodyParser::parse($request);

        $voidReason = isset($body['void_reason']) && is_string($body['void_reason']) ? trim($body['void_reason']) : '';
        if ($voidReason === '') {
            throw new ValidationException([
                new ValidationError('void_reason', 'A reason is required to void a document.', 'required'),
            ]);
        }

        $voidNote = isset($body['void_note']) && is_string($body['void_note']) && $body['void_note'] !== ''
            ? $body['void_note']
            : null;

        [$document, $version] = $this->useCase->execute($documentId, $orgId, $voidReason, $voidNote, $actorUserId);

        return $this->response->create(VaultDocumentPresenter::present($document, $version));
    }
}
