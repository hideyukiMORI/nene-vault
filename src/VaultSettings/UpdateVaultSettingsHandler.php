<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneVault\Auth\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UpdateVaultSettingsHandler
{
    private const MIN_RETENTION_YEARS = 7;
    private const MAX_RETENTION_YEARS = 99;

    public function __construct(
        private UpdateVaultSettingsUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = RequestContext::organizationId($request);
        $actorUserId = RequestContext::actorUserId($request);

        $body = JsonRequestBodyParser::parse($request);

        $retentionYears = isset($body['retention_years']) ? (int) $body['retention_years'] : 10;
        $storagePathOverride = isset($body['storage_path_override']) && is_string($body['storage_path_override']) && $body['storage_path_override'] !== ''
            ? $body['storage_path_override']
            : null;
        $invoiceApiBaseUrl = isset($body['invoice_api_base_url']) && is_string($body['invoice_api_base_url']) && $body['invoice_api_base_url'] !== ''
            ? $body['invoice_api_base_url']
            : null;
        $clearApiBaseUrl = isset($body['clear_api_base_url']) && is_string($body['clear_api_base_url']) && $body['clear_api_base_url'] !== ''
            ? $body['clear_api_base_url']
            : null;

        $this->validateRetentionYears($retentionYears);

        $refreshed = $this->useCase->execute(new UpdateVaultSettingsInput(
            organizationId: $orgId,
            retentionYears: $retentionYears,
            storagePathOverride: $storagePathOverride,
            invoiceApiBaseUrl: $invoiceApiBaseUrl,
            clearApiBaseUrl: $clearApiBaseUrl,
            actorUserId: $actorUserId,
        ));

        return $this->response->create([
            'organization_id'       => $refreshed->organizationId,
            'retention_years'       => $refreshed->retentionYears,
            'storage_path_override' => $refreshed->storagePathOverride,
            'invoice_api_base_url'  => $refreshed->invoiceApiBaseUrl,
            'clear_api_base_url'    => $refreshed->clearApiBaseUrl,
            'updated_at'            => $refreshed->updatedAt,
        ]);
    }

    private function validateRetentionYears(int $retentionYears): void
    {
        $errors = [];

        if ($retentionYears < self::MIN_RETENTION_YEARS) {
            $errors[] = new ValidationError(
                'retention_years',
                sprintf('Retention years must be at least %d. Values below 10 may not satisfy statutory requirements — confirm with your 税理士.', self::MIN_RETENTION_YEARS),
                'too_small',
            );
        }

        if ($retentionYears > self::MAX_RETENTION_YEARS) {
            $errors[] = new ValidationError(
                'retention_years',
                sprintf('Retention years must be at most %d.', self::MAX_RETENTION_YEARS),
                'too_large',
            );
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
