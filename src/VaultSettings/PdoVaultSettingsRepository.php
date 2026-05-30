<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoVaultSettingsRepository implements VaultSettingsRepositoryInterface, VaultSettingsSeederInterface
{
    private const COLUMNS = '
        organization_id, retention_years, storage_path_override,
        invoice_api_base_url, clear_api_base_url,
        updated_by, updated_at
    ';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findByOrganizationId(int $organizationId): ?VaultSettings
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM vault_settings WHERE organization_id = ?',
            [$organizationId],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function save(VaultSettings $settings): void
    {
        $now = date('Y-m-d H:i:s');
        $this->query->execute(
            'INSERT INTO vault_settings
                (organization_id, retention_years, storage_path_override,
                 invoice_api_base_url, clear_api_base_url, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $settings->organizationId,
                $settings->retentionYears,
                $settings->storagePathOverride,
                $settings->invoiceApiBaseUrl,
                $settings->clearApiBaseUrl,
                $settings->updatedBy,
                $now,
            ],
        );
    }

    public function update(VaultSettings $settings): void
    {
        $now = date('Y-m-d H:i:s');
        $this->query->execute(
            'UPDATE vault_settings
             SET retention_years = ?, storage_path_override = ?,
                 invoice_api_base_url = ?, clear_api_base_url = ?,
                 updated_by = ?, updated_at = ?
             WHERE organization_id = ?',
            [
                $settings->retentionYears,
                $settings->storagePathOverride,
                $settings->invoiceApiBaseUrl,
                $settings->clearApiBaseUrl,
                $settings->updatedBy,
                $now,
                $settings->organizationId,
            ],
        );
    }

    public function seed(int $organizationId): void
    {
        $existing = $this->findByOrganizationId($organizationId);

        if ($existing !== null) {
            return;
        }

        $this->save(new VaultSettings(organizationId: $organizationId));
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): VaultSettings
    {
        return new VaultSettings(
            organizationId: (int) $row['organization_id'],
            retentionYears: (int) $row['retention_years'],
            storagePathOverride: isset($row['storage_path_override']) && $row['storage_path_override'] !== '' ? (string) $row['storage_path_override'] : null,
            invoiceApiBaseUrl: isset($row['invoice_api_base_url']) && $row['invoice_api_base_url'] !== '' ? (string) $row['invoice_api_base_url'] : null,
            clearApiBaseUrl: isset($row['clear_api_base_url']) && $row['clear_api_base_url'] !== '' ? (string) $row['clear_api_base_url'] : null,
            updatedBy: isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
