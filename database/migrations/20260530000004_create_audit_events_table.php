<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Append-only audit log for all mutating operations.
 *
 * The application DB user must have INSERT on this table but NOT UPDATE or DELETE.
 * See ADR 0014 and docs/explanation/received-document-compliance.md §7.
 */
final class CreateAuditEventsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('audit_events')
            ->addColumn('action', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('entity_type', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('entity_id', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('actor_user_id', 'integer', ['null' => true, 'default' => null, 'signed' => false])
            ->addColumn('organization_id', 'integer', ['null' => true, 'default' => null, 'signed' => false])
            ->addColumn('before_json', 'text', ['null' => true, 'default' => null])
            ->addColumn('after_json', 'text', ['null' => true, 'default' => null])
            ->addColumn('source', 'string', ['limit' => 32, 'null' => false, 'default' => 'api'])
            ->addColumn('metadata_json', 'text', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['organization_id'], ['name' => 'idx_audit_events_org_id'])
            ->addIndex(['entity_type', 'entity_id'], ['name' => 'idx_audit_events_entity'])
            ->addIndex(['action'], ['name' => 'idx_audit_events_action'])
            ->addIndex(['actor_user_id'], ['name' => 'idx_audit_events_actor'])
            ->addIndex(['created_at'], ['name' => 'idx_audit_events_created_at'])
            ->create();
    }
}
