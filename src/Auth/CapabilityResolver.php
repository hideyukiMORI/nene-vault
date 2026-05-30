<?php

declare(strict_types=1);

namespace NeneVault\Auth;

final class CapabilityResolver
{
    public static function resolve(string $path, string $method): ?Capability
    {
        $method = strtoupper($method);

        // Organization management: superadmin only, all methods
        if (str_starts_with($path, '/admin/organizations')) {
            return Capability::ManageOrganizations;
        }

        // User management: admin+, all methods
        if (str_starts_with($path, '/admin/users')) {
            return Capability::ManageUsers;
        }

        // Vault settings: admin+
        if (str_starts_with($path, '/admin/vault/settings')) {
            return Capability::ManageVaultSettings;
        }

        // Audit events: admin+
        if (str_starts_with($path, '/admin/audit-events')) {
            return Capability::ManageVaultSettings;
        }

        // Export: admin+
        if (str_starts_with($path, '/admin/vault/export')) {
            return Capability::ExportDocuments;
        }

        // Document void / restore
        if (str_contains($path, '/void') || str_contains($path, '/restore')) {
            return Capability::VoidDocument;
        }

        // Document metadata update
        if (str_contains($path, '/metadata') && $method === 'PATCH') {
            return Capability::EditMetadata;
        }

        // Document upload (POST to collection)
        if ($path === '/admin/vault/documents' && $method === 'POST') {
            return Capability::UploadDocument;
        }

        // Document reads (search, detail, history, download)
        if (str_starts_with($path, '/admin/vault/documents')) {
            return $method === 'GET' || $method === 'HEAD'
                ? Capability::ViewDocuments
                : Capability::EditMetadata;
        }

        return null;
    }
}
