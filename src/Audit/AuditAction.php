<?php

declare(strict_types=1);

namespace NeneVault\Audit;

/**
 * Canonical audit action strings (dot-notation).
 * Every value here must be registered in docs/terms.md §14.
 */
final class AuditAction
{
    // Organization
    public const ORGANIZATION_CREATED = 'organization.created';
    public const ORGANIZATION_UPDATED = 'organization.updated';
    public const ORGANIZATION_DELETED = 'organization.deleted';

    // User
    public const USER_CREATED = 'user.created';
    public const USER_UPDATED = 'user.updated';
    public const USER_DELETED = 'user.deleted';

    // VaultSettings
    public const VAULT_SETTINGS_CHANGED = 'vault_settings.changed';

    // Document (Phase 1 API — defined now, wired later)
    public const DOCUMENT_UPLOADED = 'document.uploaded';
    public const DOCUMENT_METADATA_CHANGED = 'document.metadata_changed';
    public const DOCUMENT_VOIDED = 'document.voided';
    public const DOCUMENT_RESTORED = 'document.restored';
    public const DOCUMENT_VERSION_ADDED = 'document.version_added';
    public const DOCUMENT_EXPORTED = 'document.exported';
    public const DOCUMENT_PURGED = 'document.purged';
    public const DOCUMENT_LINK_CREATED = 'document.link_created';
    public const DOCUMENT_LINK_DELETED = 'document.link_deleted';
}
