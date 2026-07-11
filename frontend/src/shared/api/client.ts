import { authStore } from '@/entities/auth';
import { env } from '@/shared/config/env';
import { parseProblemDetails } from '@/shared/api/errors';

type HttpMethod = 'GET' | 'POST' | 'PATCH' | 'DELETE';

interface RequestOptions {
  method?: HttpMethod | undefined;
  body?: unknown;
  signal?: AbortSignal | undefined;
}

/** Fail-closed handling for auth errors, shared by all requests. */
function handleAuthError(response: Response, path: string): void {
  // Session expired (not a wrong-credentials 401 from the login endpoint):
  // clear the reactive store — the auth gate shows the login form in place at
  // the current URL (#168), instead of a hard navigation that drops SPA state.
  if (response.status === 401 && !path.includes('/auth/login')) {
    authStore.clearSession();
  }
  if (response.status === 403) {
    window.location.href = '/forbidden';
  }
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const base = env.apiBaseUrl.replace(/\/$/, '');
  const url = `${base}${path}`;
  const headers: Record<string, string> = {};

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  const token = authStore.getToken();
  if (token !== null) {
    headers['Authorization'] = `Bearer ${token}`;
    // Shared-hosting front proxies (HETEML) strip the standard Authorization
    // header; the backend adopts this mirror when it is absent (#118).
    headers['X-Authorization'] = `Bearer ${token}`;
  }

  const init: RequestInit = {
    method: options.method ?? 'GET',
    headers,
    credentials: 'include',
  };
  if (options.body !== undefined) {
    init.body = JSON.stringify(options.body);
  }
  if (options.signal !== undefined) {
    init.signal = options.signal;
  }

  const response = await fetch(url, init);

  if (!response.ok) {
    handleAuthError(response, path);
    throw await parseProblemDetails(response);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

export interface BlobDownload {
  blob: Blob;
  /** Server-suggested filename from Content-Disposition, when present. */
  filename: string | null;
}

function parseContentDispositionFilename(header: string | null): string | null {
  if (header === null) {
    return null;
  }
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header);
  if (match?.[1] === undefined) {
    return null;
  }
  try {
    return decodeURIComponent(match[1]);
  } catch {
    return match[1];
  }
}

/**
 * Authenticated binary download. Goes through the same header contract as
 * request()/uploadFormData() — crucially the X-Authorization mirror (#118) —
 * so downloads keep working behind the shared-hosting proxy that strips the
 * standard Authorization header. Pages must not hand-roll fetch() for this.
 */
async function requestBlob(path: string, options: RequestOptions = {}): Promise<BlobDownload> {
  const base = env.apiBaseUrl.replace(/\/$/, '');
  const url = `${base}${path}`;
  const headers: Record<string, string> = {};

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  const token = authStore.getToken();
  if (token !== null) {
    headers['Authorization'] = `Bearer ${token}`;
    // Shared-hosting front proxies (HETEML) strip the standard Authorization
    // header; the backend adopts this mirror when it is absent (#118).
    headers['X-Authorization'] = `Bearer ${token}`;
  }

  const init: RequestInit = {
    method: options.method ?? 'GET',
    headers,
    credentials: 'include',
  };
  if (options.body !== undefined) {
    init.body = JSON.stringify(options.body);
  }
  if (options.signal !== undefined) {
    init.signal = options.signal;
  }

  const response = await fetch(url, init);

  if (!response.ok) {
    handleAuthError(response, path);
    throw await parseProblemDetails(response);
  }

  const blob = await response.blob();
  const filename = parseContentDispositionFilename(response.headers.get('Content-Disposition'));
  return { blob, filename };
}

async function uploadFormData<T>(path: string, formData: FormData): Promise<T> {
  const base = env.apiBaseUrl.replace(/\/$/, '');
  const url = `${base}${path}`;
  const headers: Record<string, string> = {};

  const token = authStore.getToken();
  if (token !== null) {
    headers['Authorization'] = `Bearer ${token}`;
    // Shared-hosting front proxies (HETEML) strip the standard Authorization
    // header; the backend adopts this mirror when it is absent (#118).
    headers['X-Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(url, {
    method: 'POST',
    headers,
    credentials: 'include',
    body: formData,
  });

  if (!response.ok) {
    handleAuthError(response, path);
    throw await parseProblemDetails(response);
  }

  return (await response.json()) as T;
}

export const apiClient = {
  get<T>(path: string, signal?: AbortSignal): Promise<T> {
    return request<T>(path, { method: 'GET', signal });
  },
  post<T>(path: string, body?: unknown): Promise<T> {
    return request<T>(path, { method: 'POST', body });
  },
  patch<T>(path: string, body?: unknown): Promise<T> {
    return request<T>(path, { method: 'PATCH', body });
  },
  delete<T>(path: string): Promise<T> {
    return request<T>(path, { method: 'DELETE' });
  },
  upload<T>(path: string, formData: FormData): Promise<T> {
    return uploadFormData<T>(path, formData);
  },
  postBlob(path: string, body?: unknown): Promise<BlobDownload> {
    return requestBlob(path, { method: 'POST', body });
  },
};
