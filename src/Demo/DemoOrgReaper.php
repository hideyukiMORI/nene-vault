<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\DisposableOrgReaperInterface;

/**
 * Destroys one demo organization and everything it owns (#118, `Nene2\Demo`
 * consumer since #141): the DB rows (children → parents — the migrations
 * declare no FK cascade) **and the org's document storage tree**
 * (`{storageRoot}/vault/{orgId}/`), which is Vault-specific residue the
 * sibling reapers don't have. Also removes the org-scoped-null audit rows the
 * creation use cases record against the organization entity itself —
 * otherwise every disposable demo would leave an orphan `organization.*`
 * trail behind forever.
 *
 * A demo org's audit trail is part of its demo data and dies with it — this
 * NEVER runs against real tenants: callers select targets explicitly (the
 * reset tool by the fixed demo slug; the sweeper by the `demo-` prefix).
 * Idempotent: reaping an org that is already gone is success.
 */
final readonly class DemoOrgReaper implements DisposableOrgReaperInterface
{
    /** Tables carrying `organization_id`, children first. */
    private const array CHILD_TABLES = [
        'document_versions',
        'vault_documents',
        'audit_events',
        'vault_settings',
        'users',
    ];

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private string $storageRoot,
    ) {
    }

    public function reap(int $orgId): void
    {
        foreach (self::CHILD_TABLES as $table) {
            $this->query->execute("DELETE FROM {$table} WHERE organization_id = ?", [$orgId]);
        }

        // The organization.created/updated audit rows carry organization_id NULL
        // (the org is the entity, not the tenant scope) — sweep them too (#141).
        $this->query->execute(
            "DELETE FROM audit_events WHERE entity_type = 'organization' AND entity_id = ?",
            [(string) $orgId],
        );

        $this->query->execute('DELETE FROM organizations WHERE id = ?', [$orgId]);

        self::removeTree($this->storageRoot . '/vault/' . $orgId);
    }

    /** Best-effort recursive delete of the org's document tree. */
    private static function removeTree(string $dir): void
    {
        $children = @scandir($dir);

        if ($children === false) {
            return;
        }

        foreach ($children as $child) {
            if ($child === '.' || $child === '..') {
                continue;
            }

            $path = $dir . '/' . $child;
            is_dir($path) ? self::removeTree($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
