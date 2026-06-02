<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneVault\Auth\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SearchDocumentsHandler
{
    public function __construct(
        private SearchDocumentsUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = RequestContext::organizationId($request);

        $q = $request->getQueryParams();
        $pagination = PaginationQueryParser::parse($request);

        $criteria = new DocumentSearchCriteria(
            organizationId: $orgId,
            transactionDateFrom: $this->strOrNull($q['transaction_date_from'] ?? null),
            transactionDateTo: $this->strOrNull($q['transaction_date_to'] ?? null),
            amountMinCents: $this->intOrNull($q['amount_min_cents'] ?? null),
            amountMaxCents: $this->intOrNull($q['amount_max_cents'] ?? null),
            counterpartyName: $this->strOrNull($q['counterparty_name'] ?? null),
            category: $this->strOrNull($q['category'] ?? null),
            includeVoided: isset($q['include_voided']) && ($q['include_voided'] === '1' || $q['include_voided'] === 'true'),
            limit: $pagination->limit,
            offset: $pagination->offset,
        );

        $output = $this->useCase->execute($criteria);

        return $this->response->create(
            (new PaginationResponse(
                items: array_map(
                    static fn (array $pair) => VaultDocumentPresenter::present($pair[0], $pair[1]),
                    $output->items,
                ),
                limit: $output->limit,
                offset: $output->offset,
                total: $output->total,
            ))->toArray(),
        );
    }

    private function strOrNull(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : null;
    }

    private function intOrNull(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }
}
