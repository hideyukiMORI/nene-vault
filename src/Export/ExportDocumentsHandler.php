<?php

declare(strict_types=1);

namespace NeneVault\Export;

use Nene2\Http\JsonRequestBodyParser;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ExportDocumentsHandler
{
    public function __construct(
        private ExportDocumentsUseCaseInterface $useCase,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $claims = $request->getAttribute('nene2.auth.claims');
        $actorUserId = is_array($claims) && isset($claims['user_id']) ? (int) $claims['user_id'] : null;

        $body = JsonRequestBodyParser::parse($request);

        $input = new ExportDocumentsInput(
            organizationId: $orgId,
            transactionDateFrom: $this->strOrNull($body['transaction_date_from'] ?? null),
            transactionDateTo: $this->strOrNull($body['transaction_date_to'] ?? null),
            counterpartyName: $this->strOrNull($body['counterparty_name'] ?? null),
            includeVoided: isset($body['include_voided']) && ($body['include_voided'] === true || $body['include_voided'] === '1' || $body['include_voided'] === 'true'),
            actorUserId: $actorUserId,
        );

        $csv = $this->useCase->execute($input);

        $filename = 'vault-manifest-' . date('Ymd-His') . '.csv';

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody($this->streamFactory->createStream($csv));
    }

    private function strOrNull(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : null;
    }
}
