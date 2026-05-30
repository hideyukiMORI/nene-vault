<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateVaultDocumentsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('vault_documents', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('organization_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('current_version_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'active'])
            ->addColumn('transaction_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('amount_cents', 'integer', ['null' => true, 'default' => null])
            ->addColumn('counterparty_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('category', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('tags', 'text', ['null' => true, 'default' => null])
            ->addColumn('date_uncertain', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('is_metadata_confirmed', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('retention_years', 'integer', ['null' => false, 'default' => 10])
            ->addColumn('retention_expires_at', 'date', ['null' => false])
            ->addColumn('uploaded_at', 'datetime', ['null' => false])
            ->addColumn('uploaded_by', 'integer', ['null' => true, 'default' => null, 'signed' => false])
            ->addColumn('voided_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('voided_by', 'integer', ['null' => true, 'default' => null, 'signed' => false])
            ->addColumn('void_reason', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('void_note', 'text', ['null' => true, 'default' => null])
            ->addIndex(['organization_id', 'transaction_date'], ['name' => 'idx_vault_documents_org_date'])
            ->addIndex(['organization_id', 'counterparty_name'], ['name' => 'idx_vault_documents_org_counterparty'])
            ->addIndex(['organization_id', 'status'], ['name' => 'idx_vault_documents_org_status'])
            ->addIndex(['organization_id', 'retention_expires_at'], ['name' => 'idx_vault_documents_org_retention'])
            ->create();
    }
}
