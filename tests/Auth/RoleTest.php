<?php

declare(strict_types=1);

namespace NeneVault\Tests\Auth;

use NeneVault\Auth\Capability;
use NeneVault\Auth\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_superadmin_has_all_capabilities(): void
    {
        foreach (Capability::cases() as $capability) {
            $this->assertTrue(
                Role::Superadmin->hasCapability($capability),
                "Superadmin should have {$capability->name}",
            );
        }
    }

    public function test_admin_cannot_manage_organizations(): void
    {
        $this->assertFalse(Role::Admin->hasCapability(Capability::ManageOrganizations));
    }

    public function test_admin_has_all_vault_capabilities(): void
    {
        $vaultCaps = [
            Capability::ManageUsers,
            Capability::ManageVaultSettings,
            Capability::UploadDocument,
            Capability::EditMetadata,
            Capability::VoidDocument,
            Capability::ViewDocuments,
            Capability::ExportDocuments,
        ];

        foreach ($vaultCaps as $cap) {
            $this->assertTrue(Role::Admin->hasCapability($cap), "Admin should have {$cap->name}");
        }
    }

    public function test_member_can_upload_edit_and_view(): void
    {
        $this->assertTrue(Role::Member->hasCapability(Capability::UploadDocument));
        $this->assertTrue(Role::Member->hasCapability(Capability::EditMetadata));
        $this->assertTrue(Role::Member->hasCapability(Capability::ViewDocuments));
    }

    public function test_member_cannot_void_export_or_manage(): void
    {
        $this->assertFalse(Role::Member->hasCapability(Capability::VoidDocument));
        $this->assertFalse(Role::Member->hasCapability(Capability::ExportDocuments));
        $this->assertFalse(Role::Member->hasCapability(Capability::ManageUsers));
        $this->assertFalse(Role::Member->hasCapability(Capability::ManageOrganizations));
    }

    public function test_viewer_can_only_view(): void
    {
        $this->assertTrue(Role::Viewer->hasCapability(Capability::ViewDocuments));

        $denied = [
            Capability::ManageOrganizations,
            Capability::ManageUsers,
            Capability::ManageVaultSettings,
            Capability::UploadDocument,
            Capability::EditMetadata,
            Capability::VoidDocument,
            Capability::ExportDocuments,
        ];

        foreach ($denied as $cap) {
            $this->assertFalse(Role::Viewer->hasCapability($cap), "Viewer should NOT have {$cap->name}");
        }
    }
}
