/**
 * Capabilities, mirroring the backend `NeneVault\Auth\Capability` enum.
 * Keep in sync with `src/Auth/Capability.php`.
 */
export type Capability =
  | 'ManageOrganizations'
  | 'ManageUsers'
  | 'ManageVaultSettings'
  | 'UploadDocument'
  | 'EditMetadata'
  | 'VoidDocument'
  | 'ViewDocuments'
  | 'ExportDocuments';

/**
 * Frontend mirror of `NeneVault\Auth\Role::hasCapability` (`src/Auth/Role.php`).
 * This is a UX convenience only — the server remains the authority (a viewer who
 * hand-types an admin URL still gets a correct 403). Keeping the table identical
 * to the backend is what lets the rail hide exactly the routes a role cannot use
 * (#174), so admin-only links never dead-end a viewer on the Forbidden page.
 *
 * Takes the raw role string from the session claim; an unknown/missing role is
 * treated as having no capabilities (fail-closed).
 */
export function roleHasCapability(role: string | undefined, capability: Capability): boolean {
  switch (role) {
    case 'superadmin':
      return true;
    case 'admin':
      return capability !== 'ManageOrganizations';
    case 'member':
      return (
        capability === 'UploadDocument' ||
        capability === 'EditMetadata' ||
        capability === 'ViewDocuments'
      );
    case 'viewer':
      return capability === 'ViewDocuments';
    default:
      return false;
  }
}
