import type { VaultDocument, DocumentListResponse } from '@/entities/document';
import type { AuditEvent, AuditEventListResponse, DocumentHistoryResponse } from '@/entities/audit';
import type { VaultSettings } from '@/entities/vault-settings';
import type { User, UserListResponse } from '@/entities/user';

// ── Document ──────────────────────────────────────────────────────────────────

export const DOCUMENT_ID = 'doc-01J0000000000000000000000';
export const DOCUMENT_ID_2 = 'doc-01J0000000000000000000002';

export const mockDocument: VaultDocument = {
  id: DOCUMENT_ID,
  organization_id: 1,
  status: 'active',
  transaction_date: '2026-03-31',
  amount_cents: 110000,
  counterparty_name: 'Sample Inc.',
  category: 'invoice_received',
  tags: ['q1', 'important'],
  file_sha256: 'a'.repeat(64),
  mime_type: 'application/pdf',
  original_filename: 'invoice.pdf',
  file_size_bytes: 4096,
  version_number: 1,
  source: 'web_upload',
  uploaded_at: '2026-04-01T10:00:00Z',
  uploaded_by: 1,
  voided_at: null,
  voided_by: null,
  void_reason: null,
  date_uncertain: false,
  is_metadata_confirmed: true,
  retention_years: 10,
  retention_expires_at: '2036-03-31',
};

export const mockVoidedDocument: VaultDocument = {
  ...mockDocument,
  id: DOCUMENT_ID_2,
  status: 'voided',
  voided_at: '2026-04-10T12:00:00Z',
  voided_by: 1,
  void_reason: 'Duplicate entry',
};

export const mockDocumentList: DocumentListResponse = {
  items: [mockDocument],
  total: 1,
  limit: 20,
  offset: 0,
};

// ── Audit ─────────────────────────────────────────────────────────────────────

export const mockAuditEvent: AuditEvent = {
  id: 1,
  action: 'document.uploaded',
  entity_type: 'vault_document',
  entity_id: DOCUMENT_ID,
  actor_user_id: 1,
  organization_id: 1,
  before_json: null,
  after_json: { counterparty_name: 'Sample Inc.', amount_cents: 110000 },
  source: 'api',
  metadata_json: null,
  created_at: '2026-04-01T10:00:00Z',
};

export const mockAuditEventList: AuditEventListResponse = {
  items: [mockAuditEvent],
  total: 1,
  limit: 20,
  offset: 0,
};

export const mockDocumentHistory: DocumentHistoryResponse = {
  versions: [
    {
      id: 'ver-01J0000000000000000000001',
      version_number: 1,
      file_sha256: 'a'.repeat(64),
      mime_type: 'application/pdf',
      original_filename: 'invoice.pdf',
      file_size_bytes: 4096,
      source: 'web_upload',
      uploaded_at: '2026-04-01T10:00:00Z',
      uploaded_by: 1,
    },
  ],
  audit_events: [mockAuditEvent],
};

// ── VaultSettings ────────────────────────────────────────────────────────────

export const mockVaultSettings: VaultSettings = {
  organization_id: 1,
  retention_years: 10,
  storage_path_override: null,
  invoice_api_base_url: null,
  clear_api_base_url: null,
  updated_at: '2026-04-01T08:00:00Z',
};

// ── User ─────────────────────────────────────────────────────────────────────

export const mockUser: User = {
  id: 42,
  email: 'member@example.com',
  role: 'member',
  organization_id: 1,
  status: 'active',
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
};

export const mockUserList: UserListResponse = {
  items: [mockUser],
  total: 1,
  limit: 20,
  offset: 0,
};

// ── Problem Details ───────────────────────────────────────────────────────────

export function problemDetails(slug: string, status: number, title: string) {
  return {
    type: `https://nene-vault.dev/problems/${slug}`,
    title,
    status,
    detail: `${title}.`,
    instance: '/test',
  };
}
