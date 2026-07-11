import { apiClient } from '@/shared/api/client';

/**
 * The download endpoint is keyed by the version's ULID (not its ordinal
 * version_number) and is the single authenticated path to the file bytes —
 * the storage path is never exposed (Hard rule).
 *
 * A plain <a href> link cannot be used for this: it sends no Authorization
 * header, and the backend is JWT-only (no session cookie), so the bytes must
 * be fetched through the shared API client and saved from an object URL (#179).
 */
function downloadPath(documentId: string, versionId: string): string {
  return `/admin/vault/documents/${documentId}/versions/${versionId}/download`;
}

/** Authenticated fetch of the raw file bytes (bearer token via the api client). */
export function fetchDocumentBlob(documentId: string, versionId: string): Promise<Blob> {
  return apiClient.getBlob(downloadPath(documentId, versionId));
}
