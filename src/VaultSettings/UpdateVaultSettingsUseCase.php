<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;

final readonly class UpdateVaultSettingsUseCase implements UpdateVaultSettingsUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): VaultSettingsRepositoryInterface $settingsRepository
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface           $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $settingsRepository,
        private Closure $auditRecorder,
    ) {
    }

    public function execute(UpdateVaultSettingsInput $input): VaultSettings
    {
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($input): VaultSettings {
                $settings = ($this->settingsRepository)($executor);
                $audit = ($this->auditRecorder)($executor);

                $current = $settings->findByOrganizationId($input->organizationId);
                $beforeJson = $current !== null ? $this->toAuditArray($current) : null;

                $updated = new VaultSettings(
                    organizationId: $input->organizationId,
                    retentionYears: $input->retentionYears,
                    storagePathOverride: $input->storagePathOverride,
                    invoiceApiBaseUrl: $input->invoiceApiBaseUrl,
                    clearApiBaseUrl: $input->clearApiBaseUrl,
                    updatedBy: $input->actorUserId,
                );

                if ($current === null) {
                    $settings->save($updated);
                } else {
                    $settings->update($updated);
                }

                $refreshed = $settings->findByOrganizationId($input->organizationId) ?? $updated;

                $audit->record(
                    action: AuditAction::VAULT_SETTINGS_CHANGED,
                    entityType: 'vault_settings',
                    entityId: (string) $input->organizationId,
                    actorUserId: $input->actorUserId,
                    organizationId: $input->organizationId,
                    beforeJson: $beforeJson,
                    afterJson: $this->toAuditArray($refreshed),
                );

                return $refreshed;
            },
        );
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
