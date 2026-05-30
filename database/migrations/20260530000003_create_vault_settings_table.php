<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateVaultSettingsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('vault_settings', ['id' => false, 'primary_key' => ['organization_id']])
            ->addColumn('organization_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('retention_years', 'integer', ['null' => false, 'default' => 10])
            ->addColumn('storage_path_override', 'string', ['limit' => 512, 'null' => true, 'default' => null])
            ->addColumn('invoice_api_base_url', 'string', ['limit' => 512, 'null' => true, 'default' => null])
            ->addColumn('clear_api_base_url', 'string', ['limit' => 512, 'null' => true, 'default' => null])
            ->addColumn('updated_by', 'integer', ['null' => true, 'default' => null, 'signed' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->create();
    }
}
