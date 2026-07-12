import { describe, expect, it } from 'vitest';
import { roleHasCapability, type Capability } from './capabilities';

// This table MUST match backend `NeneVault\Auth\Role::hasCapability`
// (src/Auth/Role.php). If the backend changes, update both together.
const ALL: Capability[] = [
  'ManageOrganizations',
  'ManageUsers',
  'ManageVaultSettings',
  'UploadDocument',
  'EditMetadata',
  'VoidDocument',
  'ViewDocuments',
  'ExportDocuments',
];

describe('roleHasCapability (mirror of backend Role::hasCapability)', () => {
  it('superadmin has every capability', () => {
    for (const cap of ALL) {
      expect(roleHasCapability('superadmin', cap)).toBe(true);
    }
  });

  it('admin has everything except ManageOrganizations', () => {
    for (const cap of ALL) {
      expect(roleHasCapability('admin', cap)).toBe(cap !== 'ManageOrganizations');
    }
  });

  it('member may upload / edit / view only', () => {
    const allowed = new Set<Capability>(['UploadDocument', 'EditMetadata', 'ViewDocuments']);
    for (const cap of ALL) {
      expect(roleHasCapability('member', cap)).toBe(allowed.has(cap));
    }
  });

  it('viewer may view documents only', () => {
    for (const cap of ALL) {
      expect(roleHasCapability('viewer', cap)).toBe(cap === 'ViewDocuments');
    }
  });

  it('fails closed for an unknown or missing role', () => {
    for (const cap of ALL) {
      expect(roleHasCapability(undefined, cap)).toBe(false);
      expect(roleHasCapability('', cap)).toBe(false);
      expect(roleHasCapability('robot', cap)).toBe(false);
    }
  });

  it('viewer and member cannot reach the admin-only rail routes (#174)', () => {
    // Rail gating: audit + settings require ManageVaultSettings, users require
    // ManageUsers, export requires ExportDocuments.
    for (const role of ['viewer', 'member'] as const) {
      expect(roleHasCapability(role, 'ManageVaultSettings')).toBe(false);
      expect(roleHasCapability(role, 'ManageUsers')).toBe(false);
      expect(roleHasCapability(role, 'ExportDocuments')).toBe(false);
    }
    // ...but admin sees all of them.
    expect(roleHasCapability('admin', 'ManageVaultSettings')).toBe(true);
    expect(roleHasCapability('admin', 'ManageUsers')).toBe(true);
    expect(roleHasCapability('admin', 'ExportDocuments')).toBe(true);
  });
});
