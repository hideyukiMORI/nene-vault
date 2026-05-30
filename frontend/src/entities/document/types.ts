export type DocumentStatus = 'active' | 'voided';
export type DocumentCategory =
  | 'invoice_received'
  | 'contract'
  | 'receipt'
  | 'delivery_note'
  | 'other';
export type DocumentSource = 'web_upload' | 'email_inbound' | 'api' | 'scan_upload';

export interface VaultDocument {
  id: string;
  organization_id: number;
  status: DocumentStatus;
  transaction_date: string | null;
  amount_cents: number | null;
  counterparty_name: string;
  category: DocumentCategory;
  tags: string[];
  file_sha256: string;
  mime_type: string | undefined;
  original_filename: string | undefined;
  file_size_bytes: number | undefined;
  version_number: number;
  source: DocumentSource;
  uploaded_at: string;
  uploaded_by: number | null;
  voided_at: string | null;
  voided_by: number | null;
  void_reason: string | null;
  date_uncertain: boolean;
  is_metadata_confirmed: boolean;
  retention_years: number;
  retention_expires_at: string;
}

export interface DocumentListResponse {
  items: VaultDocument[];
  total: number;
  limit: number;
  offset: number;
}

export interface SearchDocumentsParams {
  transaction_date_from?: string;
  transaction_date_to?: string;
  amount_min_cents?: number;
  amount_max_cents?: number;
  counterparty_name?: string;
  category?: DocumentCategory;
  include_voided?: boolean;
  limit?: number;
  offset?: number;
}
