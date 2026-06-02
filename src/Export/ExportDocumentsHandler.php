<?php

declare(strict_types=1);

namespace NeneVault\Export;

use Nene2\Http\JsonRequestBodyParser;
use NeneVault\Auth\RequestContext;
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
        $orgId = RequestContext::organizationId($request);

        $actorUserId = RequestContext::actorUserId($request);

        $body = JsonRequestBodyParser::parse($request);

        $format = (is_string($body['format'] ?? null) && $body['format'] === 'zip') ? 'zip' : 'csv';

        $input = new ExportDocumentsInput(
            organizationId: $orgId,
            transactionDateFrom: $this->strOrNull($body['transaction_date_from'] ?? null),
            transactionDateTo: $this->strOrNull($body['transaction_date_to'] ?? null),
            counterpartyName: $this->strOrNull($body['counterparty_name'] ?? null),
            includeVoided: isset($body['include_voided']) && ($body['include_voided'] === true || $body['include_voided'] === '1' || $body['include_voided'] === 'true'),
            actorUserId: $actorUserId,
            format: $format,
        );

        $output = $this->useCase->execute($input);

        $timestamp = date('Ymd-His');

        if ($output->format === 'zip') {
            $filename = 'vault-export-' . $timestamp . '.zip';

            return $this->responseFactory->createResponse(200)
                ->withHeader('Content-Type', 'application/zip')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withBody($this->streamFactory->createStream($output->payload));
        }

        $filename = 'vault-manifest-' . $timestamp . '.csv';

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody($this->streamFactory->createStream($output->payload));
    }

    private function strOrNull(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : null;
    }
}
