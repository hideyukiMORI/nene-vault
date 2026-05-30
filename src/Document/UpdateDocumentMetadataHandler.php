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

final readonly class UpdateDocumentMetadataHandler
{
    /** @var list<string> */
    private const VALID_CATEGORIES = ['invoice_received', 'contract', 'receipt', 'delivery_note', 'other'];

    public function __construct(
        private UpdateDocumentMetadataUseCaseInterface $useCase,
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
        $errors = [];

        $counterparty = isset($body['counterparty_name']) && is_string($body['counterparty_name'])
            ? trim($body['counterparty_name'])
            : '';
        if ($counterparty === '') {
            $errors[] = new ValidationError('counterparty_name', 'This field is required.', 'required');
        }

        $category = isset($body['category']) && is_string($body['category']) ? $body['category'] : '';
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            $errors[] = new ValidationError('category', 'Invalid category.', 'invalid_format');
        }

        $transactionDate = isset($body['transaction_date']) && is_string($body['transaction_date']) && $body['transaction_date'] !== ''
            ? $body['transaction_date']
            : null;
        if ($transactionDate !== null && !$this->isValidDate($transactionDate)) {
            $errors[] = new ValidationError('transaction_date', 'Please enter a valid date (YYYY-MM-DD).', 'invalid_date');
        }

        $amountCents = null;
        if (isset($body['amount_cents']) && $body['amount_cents'] !== '') {
            if (!is_numeric($body['amount_cents']) || (int) $body['amount_cents'] != $body['amount_cents']) {
                $errors[] = new ValidationError('amount_cents', 'Please enter a valid integer amount.', 'invalid_amount');
            } else {
                $amountCents = (int) $body['amount_cents'];
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $tags = [];
        if (isset($body['tags']) && is_array($body['tags'])) {
            $tags = array_values(array_map('strval', $body['tags']));
        }

        [$document, $version] = $this->useCase->execute(new UpdateDocumentMetadataInput(
            documentId: $documentId,
            organizationId: $orgId,
            transactionDate: $transactionDate,
            amountCents: $amountCents,
            counterpartyName: $counterparty,
            category: $category,
            tags: $tags,
            actorUserId: $actorUserId,
        ));

        return $this->response->create(VaultDocumentPresenter::present($document, $version));
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
