<?php

declare(strict_types=1);

namespace NeneVault\Ocr;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class OcrSuggestHandler
{
    public function __construct(
        private OcrSuggestUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $documentId = (string) ($params['id'] ?? '');

        try {
            $suggestion = $this->useCase->execute($documentId, $orgId);
        } catch (OcrException $e) {
            return $this->response->create(
                ['error' => 'ocr_failed', 'message' => $e->getMessage()],
                422,
            );
        }

        return $this->response->create([
            'document_id'      => $documentId,
            'transaction_date' => $suggestion->transactionDate,
            'amount_cents'     => $suggestion->amountCents,
            'counterparty_name' => $suggestion->counterpartyName,
            'has_suggestion'   => !$suggestion->isEmpty(),
        ]);
    }
}
