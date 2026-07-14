import {
  createNene2Transport,
  isNene2ClientError,
  isValidationProblemDetails,
  type Nene2ClientError,
  type TokenStore,
} from '@hideyukimori/nene2-client';
import { authStore } from '@/entities/auth';
import { env } from '@/shared/config/env';
import { AppError, type ProblemDetails } from '@/shared/api/errors';

/**
 * Adapts vault's session-object store (`entities/auth/model.ts`, #148 — the
 * fleet exemplar for the auth module-store pattern, AU-1/AU-4) to the
 * transport's minimal `TokenStore` contract. `authStore` itself is untouched:
 * only its token accessors are handed to the transport.
 */
const tokenStore: TokenStore = {
  getToken: () => authStore.getToken(),
  clearToken: () => {
    authStore.clearSession();
  },
};

/**
 * Fleet-standard transport (`@hideyukimori/nene2-client`, issue #102): every
 * request mirrors the bearer token onto `Authorization` *and*
 * `X-Authorization` (#118 — shared-hosting front proxies strip the standard
 * header) and normalizes RFC 9457 Problem Details. `apiClient` below is a
 * thin adapter that keeps this product's existing surface
 * (`get/post/patch/delete/upload/postBlob/getBlob`) verbatim so call sites
 * did not need to change.
 */
const transport = createNene2Transport({
  baseUrl: env.apiBaseUrl,
  tokenStore,
  credentials: 'include',
  // Look up `fetch` at call time, not bind it once at module load: tests
  // patch `globalThis.fetch` via msw's `server.listen()`, which runs (in a
  // `beforeAll` hook) after this module has already been imported.
  fetch: (input, init) => globalThis.fetch(input, init),
  onForbidden: () => {
    window.location.href = '/forbidden';
  },
  // 401 on an authenticated request clears `tokenStore` automatically (built
  // into the transport); the reactive auth gate (#168, entities/auth) shows
  // the login form in place on the next render — no separate side effect
  // needed here. A 401 on `/auth/login` never reaches this: the request
  // carries no token yet, so the transport does not treat it as a session
  // failure (see `AuthFailureContext.tokenAttached`).
});

/** Maps the package's `Nene2ClientError` to this product's `AppError` (unchanged shape for callers). */
function toAppError(error: Nene2ClientError): AppError {
  const problem = error.problem;
  if (problem === undefined) {
    return new AppError(error.status, null);
  }
  const mapped: ProblemDetails = {
    type: problem.type,
    title: problem.title,
    status: problem.status,
  };
  if (problem.detail !== undefined) {
    mapped.detail = problem.detail;
  }
  if (problem.instance !== undefined) {
    mapped.instance = problem.instance;
  }
  if (isValidationProblemDetails(problem)) {
    mapped.errors = problem.errors;
  }
  return new AppError(error.status, mapped);
}

async function unwrap<T>(promise: Promise<T>): Promise<T> {
  try {
    return await promise;
  } catch (error) {
    if (isNene2ClientError(error)) {
      throw toAppError(error);
    }
    throw error;
  }
}

export interface BlobDownload {
  blob: Blob;
  /** Server-suggested filename from Content-Disposition, when present. */
  filename: string | null;
}

export const apiClient = {
  get<T>(path: string, signal?: AbortSignal): Promise<T> {
    return unwrap(transport.get<T>(path, { signal }));
  },
  post<T>(path: string, body?: unknown): Promise<T> {
    return unwrap(transport.post<T>(path, body));
  },
  patch<T>(path: string, body?: unknown): Promise<T> {
    return unwrap(transport.patch<T>(path, body));
  },
  delete<T>(path: string): Promise<T> {
    return unwrap(transport.delete<T>(path));
  },
  upload<T>(path: string, formData: FormData): Promise<T> {
    return unwrap(transport.upload<T>(path, formData));
  },
  postBlob(path: string, body?: unknown): Promise<BlobDownload> {
    return unwrap(transport.postBlob(path, body));
  },
  async getBlob(path: string, signal?: AbortSignal): Promise<Blob> {
    const { blob } = await unwrap(transport.getBlob(path, { signal }));
    return blob;
  },
};
