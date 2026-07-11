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
};
