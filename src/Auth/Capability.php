<?php

declare(strict_types=1);

namespace NeneVault\Auth;

enum Capability
{
    case ManageOrganizations;
    case ManageUsers;
    case ManageVaultSettings;
    case UploadDocument;
    case EditMetadata;
    case VoidDocument;
    case ViewDocuments;
    case ExportDocuments;
}
