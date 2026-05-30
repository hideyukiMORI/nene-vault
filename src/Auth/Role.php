<?php

declare(strict_types=1);

namespace NeneVault\Auth;

enum Role: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function hasCapability(Capability $capability): bool
    {
        return match ($this) {
            self::Superadmin => true,
            self::Admin => $capability !== Capability::ManageOrganizations,
            self::Member => match ($capability) {
                Capability::UploadDocument,
                Capability::EditMetadata,
                Capability::ViewDocuments => true,
                default => false,
            },
            self::Viewer => $capability === Capability::ViewDocuments,
        };
    }
}
