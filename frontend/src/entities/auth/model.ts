// Auth session store. The JWT session lives in sessionStorage (fleet-standard
// XSS blast-radius mitigation, #148: the token does not persist across browser
// restarts); the API client reads getToken() and sends it as a Bearer token.

export interface AuthSession {
  token: string;
  userId: number;
  email: string;
  role: string;
  orgId: number | null;
}

const STORAGE_KEY = 'nene_vault_token';

// Reactive layer (#168): mutations notify subscribers so the auth gate can
// re-render via useSyncExternalStore — a 401 clears the session and the login
// form appears IN PLACE (current URL preserved), no hard navigation.
const listeners = new Set<() => void>();

function notify(): void {
  for (const listener of listeners) {
    listener();
  }
}

// [nene2-exemplar:auth-store] — fleet frontend-standards AU-4 module-store exemplar (check:exemplars).
export const authStore = {
  /** Subscribe to session changes; returns the unsubscribe function. */
  subscribe(listener: () => void): () => void {
    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  },

  getSession(): AuthSession | null {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (raw === null) {
        return null;
      }
      return JSON.parse(raw) as AuthSession;
    } catch {
      return null;
    }
  },

  setSession(session: AuthSession): void {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    } catch {
      // ignore persistence failure
    }
    notify();
  },

  clearSession(): void {
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch {
      // ignore
    }
    notify();
  },

  getToken(): string | null {
    return this.getSession()?.token ?? null;
  },
};
