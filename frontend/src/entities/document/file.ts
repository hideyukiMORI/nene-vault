import { useEffect, useState } from 'react';
import { apiClient } from '@/shared/api/client';

/**
 * Integrity-checked fetch of a document version's stored bytes for in-browser preview.
 *
 * The download endpoint (keyed by the version's ULID, not its ordinal number) is the
 * single authenticated path to the file bytes — the storage path is never exposed
 * (Hard rule). We re-compute SHA-256 in the browser and compare it to the recorded
 * hash so the operator (and, in a tax audit, the examiner) can see the displayed
 * bytes are the unmodified original.
 */
export type DocumentFileStatus = 'loading' | 'verified' | 'mismatch' | 'error';

export interface DocumentFile {
  url: string | undefined;
  blob: Blob | undefined;
  mimeType: string;
  status: DocumentFileStatus;
}

export interface DocumentFileRequest {
  documentId: string;
  versionId: string;
  sha256: string;
  mimeType: string;
}

function downloadPath(documentId: string, versionId: string): string {
  return `/admin/vault/documents/${documentId}/versions/${versionId}/download`;
}

/** Authenticated fetch of the raw file bytes (bearer token via the api client). */
export function fetchDocumentBlob(documentId: string, versionId: string): Promise<Blob> {
  return apiClient.getBlob(downloadPath(documentId, versionId));
}

async function sha256Hex(buffer: ArrayBuffer): Promise<string> {
  const digest = await crypto.subtle.digest('SHA-256', buffer);
  return Array.from(new Uint8Array(digest))
    .map((byte) => byte.toString(16).padStart(2, '0'))
    .join('');
}

export function useDocumentFile(req: DocumentFileRequest | undefined): DocumentFile {
  const documentId = req?.documentId;
  const versionId = req?.versionId;
  const expectedHash = req?.sha256;
  const mimeType = req?.mimeType ?? '';

  const [state, setState] = useState<DocumentFile>({
    url: undefined,
    blob: undefined,
    mimeType,
    status: 'loading',
  });

  useEffect(() => {
    if (documentId === undefined || versionId === undefined || expectedHash === undefined) {
      return undefined;
    }

    const controller = new AbortController();
    let objectUrl: string | undefined;
    setState({ url: undefined, blob: undefined, mimeType, status: 'loading' });

    void (async () => {
      try {
        const blob = await apiClient.getBlob(
          downloadPath(documentId, versionId),
          controller.signal,
        );
        const hash = await sha256Hex(await blob.arrayBuffer());
        if (controller.signal.aborted) {
          return;
        }
        objectUrl = URL.createObjectURL(blob);
        setState({
          url: objectUrl,
          blob,
          mimeType,
          status: hash === expectedHash ? 'verified' : 'mismatch',
        });
      } catch {
        if (!controller.signal.aborted) {
          setState({ url: undefined, blob: undefined, mimeType, status: 'error' });
        }
      }
    })();

    return () => {
      controller.abort();
      if (objectUrl !== undefined) {
        URL.revokeObjectURL(objectUrl);
      }
    };
  }, [documentId, versionId, expectedHash, mimeType]);

  return state;
}
