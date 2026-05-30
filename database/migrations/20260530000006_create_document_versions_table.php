<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Immutable document version records. File bytes are never overwritten;
 * a correction creates a new row with a higher version_number
 * (received-document-compliance §3.1).
 */
final class CreateDocumentVersionsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('document_versions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('vault_document_id', 'char', ['limit' => 26, 'null' => false])
            ->addColumn('organization_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('version_number', 'integer', ['null' => false])
            ->addColumn('file_path', 'string', ['limit' => 512, 'null' => false])
            ->addColumn('file_sha256', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('mime_type', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('original_filename', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('file_size_bytes', 'integer', ['null' => false])
            ->addColumn('source', 'string', ['limit' => 32, 'null' => false, 'default' => 'web_upload'])
            ->addColumn('uploaded_at', 'datetime', ['null' => false])
            ->addColumn('uploaded_by', 'integer', ['null' => true, 'default' => null, 'signed' => false])
            ->addIndex(['vault_document_id', 'version_number'], ['unique' => true, 'name' => 'uniq_document_versions_doc_version'])
            ->addIndex(['organization_id', 'file_sha256'], ['name' => 'idx_document_versions_org_sha256'])
            ->create();
    }
}
