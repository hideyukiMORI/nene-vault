<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneVault\DocumentVersion\DocumentVersion;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final readonly class UploadDocumentHandler
{
    /** @var list<string> */
    private const VALID_CATEGORIES = ['invoice_received', 'contract', 'receipt', 'delivery_note', 'other'];

    public function __construct(
        private UploadDocumentUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $claims = $request->getAttribute('nene2.auth.claims');
        $actorUserId = is_array($claims) && isset($claims['user_id']) ? (int) $claims['user_id'] : null;

        $file = $request->getUploadedFiles()['file'] ?? null;
        $body = (array) $request->getParsedBody();
        $errors = [];

        if (!$file instanceof UploadedFileInterface) {
            $errors[] = new ValidationError('file', 'A file is required.', 'required');
        } elseif ($file->getError() !== UPLOAD_ERR_OK) {
            $errors[] = new ValidationError('file', 'File upload failed.', 'upload_error');
        }

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

        assert($file instanceof UploadedFileInterface);

        $tmpPath = $file->getStream()->getMetadata('uri');
        if (!is_string($tmpPath)) {
            throw new ValidationException([new ValidationError('file', 'Could not read uploaded file.', 'upload_error')]);
        }

        $tags = [];
        if (isset($body['tags']) && is_string($body['tags']) && $body['tags'] !== '') {
            $tags = array_values(array_filter(array_map('trim', explode(',', $body['tags'])), static fn (string $t) => $t !== ''));
        }

        $source = $this->looksLikeScan($file->getClientMediaType() ?? '') ? 'scan_upload' : 'web_upload';
        $confirmDuplicate = isset($body['confirm_duplicate'])
            && ($body['confirm_duplicate'] === '1' || $body['confirm_duplicate'] === 'true' || $body['confirm_duplicate'] === true);

        $output = $this->useCase->execute(new UploadDocumentInput(
            organizationId: $orgId,
            tmpPath: $tmpPath,
            originalFilename: $file->getClientFilename() ?? 'upload',
            mimeType: $file->getClientMediaType() ?? 'application/octet-stream',
            fileSizeBytes: (int) $file->getSize(),
            counterpartyName: $counterparty,
            category: $category,
            transactionDate: $transactionDate,
            amountCents: $amountCents,
            tags: $tags,
            source: $source,
            confirmDuplicate: $confirmDuplicate,
            actorUserId: $actorUserId,
        ));

        $version = new DocumentVersion(
            id: $output->document->currentVersionId,
            vaultDocumentId: $output->document->id,
            organizationId: $orgId,
            versionNumber: $output->versionNumber,
            filePath: '',
            fileSha256: $output->fileSha256,
            mimeType: $output->mimeType,
            originalFilename: $output->originalFilename,
            fileSizeBytes: $output->fileSizeBytes,
            source: $source,
            uploadedAt: $output->document->uploadedAt,
            uploadedBy: $actorUserId,
        );

        return $this->response->create(
            VaultDocumentPresenter::present($output->document, $version),
            201,
        );
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $d !== false && $d->format('Y-m-d') === $date;
    }

    private function looksLikeScan(string $mimeType): bool
    {
        // JPEG/PNG uploads are likely scans of paper; PDF is treated as electronic.
        return $mimeType === 'image/jpeg' || $mimeType === 'image/png';
    }
}
