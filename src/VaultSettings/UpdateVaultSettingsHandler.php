<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UpdateVaultSettingsHandler
{
    private const MIN_RETENTION_YEARS = 7;
    private const MAX_RETENTION_YEARS = 99;

    public function __construct(
        private VaultSettingsRepositoryInterface $settings,
        private JsonResponseFactory $response,
        private AuditRecorderInterface $audit,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $claims = $request->getAttribute('nene2.auth.claims');
        $actorUserId = is_array($claims) && isset($claims['user_id']) ? (int) $claims['user_id'] : null;

        $body = JsonRequestBodyParser::parse($request);
        $errors = [];

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

        $current = $this->settings->findByOrganizationId($orgId);

        // Capture before state
        $beforeJson = $current !== null ? $this->toAuditArray($current) : null;

        $updated = new VaultSettings(
            organizationId: $orgId,
            retentionYears: $retentionYears,
            storagePathOverride: $storagePathOverride,
            invoiceApiBaseUrl: $invoiceApiBaseUrl,
            clearApiBaseUrl: $clearApiBaseUrl,
            updatedBy: $actorUserId,
        );

        if ($current === null) {
            $this->settings->save($updated);
        } else {
            $this->settings->update($updated);
        }

        $refreshed = $this->settings->findByOrganizationId($orgId) ?? $updated;

        $this->audit->record(
            action: AuditAction::VAULT_SETTINGS_CHANGED,
            entityType: 'vault_settings',
            entityId: (string) $orgId,
            actorUserId: $actorUserId,
            organizationId: $orgId,
            beforeJson: $beforeJson,
            afterJson: $this->toAuditArray($refreshed),
        );

        return $this->response->create([
            'organization_id'       => $refreshed->organizationId,
            'retention_years'       => $refreshed->retentionYears,
            'storage_path_override' => $refreshed->storagePathOverride,
            'invoice_api_base_url'  => $refreshed->invoiceApiBaseUrl,
            'clear_api_base_url'    => $refreshed->clearApiBaseUrl,
            'updated_at'            => $refreshed->updatedAt,
        ]);
    }

    /** @return array<string, mixed> */
    private function toAuditArray(VaultSettings $s): array
    {
        return [
            'organization_id'       => $s->organizationId,
            'retention_years'       => $s->retentionYears,
            'storage_path_override' => $s->storagePathOverride,
            'invoice_api_base_url'  => $s->invoiceApiBaseUrl,
            'clear_api_base_url'    => $s->clearApiBaseUrl,
        ];
    }
}
