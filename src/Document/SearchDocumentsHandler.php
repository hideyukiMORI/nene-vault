<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Http\JsonResponseFactory;
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
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $q = $request->getQueryParams();

        $limit = min(100, max(1, (int) ($q['limit'] ?? 20)));
        $offset = max(0, (int) ($q['offset'] ?? 0));

        $criteria = new DocumentSearchCriteria(
            organizationId: $orgId,
            transactionDateFrom: $this->strOrNull($q['transaction_date_from'] ?? null),
            transactionDateTo: $this->strOrNull($q['transaction_date_to'] ?? null),
            amountMinCents: $this->intOrNull($q['amount_min_cents'] ?? null),
            amountMaxCents: $this->intOrNull($q['amount_max_cents'] ?? null),
            counterpartyName: $this->strOrNull($q['counterparty_name'] ?? null),
            category: $this->strOrNull($q['category'] ?? null),
            includeVoided: isset($q['include_voided']) && ($q['include_voided'] === '1' || $q['include_voided'] === 'true'),
            limit: $limit,
            offset: $offset,
        );

        $output = $this->useCase->execute($criteria);

        return $this->response->create([
            'items'  => array_map(
                static fn (array $pair) => VaultDocumentPresenter::present($pair[0], $pair[1]),
                $output->items,
            ),
            'total'  => $output->total,
            'limit'  => $output->limit,
            'offset' => $output->offset,
        ]);
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
