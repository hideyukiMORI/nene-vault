export interface AuditEvent {
  id: number;
  action: string;
  entity_type: string;
  entity_id: string;
  actor_user_id: number | null;
  organization_id: number | null;
  before_json: Record<string, unknown> | null;
  after_json: Record<string, unknown> | null;
  source: string;
  metadata_json: Record<string, unknown> | null;
  created_at: string;
}

export interface AuditEventListResponse {
  items: AuditEvent[];
  total: number;
  limit: number;
  offset: number;
}

export interface DocumentVersion {
  id: string;
  version_number: number;
  file_sha256: string;
  mime_type: string;
  original_filename: string;
  file_size_bytes: number | undefined;
  source: string;
  uploaded_at: string;
  uploaded_by: number | null;
}

export interface DocumentHistoryResponse {
  versions: DocumentVersion[];
  audit_events: AuditEvent[];
}

export interface ListAuditEventsParams {
  entity_type?: string | undefined;
  entity_id?: string | undefined;
  action?: string | undefined;
  actor_user_id?: number | undefined;
  limit?: number | undefined;
  offset?: number | undefined;
}
