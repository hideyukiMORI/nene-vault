// Auth session store. Same pattern as NeNe Records: the JWT session lives in
// localStorage; the API client reads getToken() and sends it as a Bearer token.

export interface AuthSession {
  token: string;
  userId: number;
  email: string;
  role: string;
  orgId: number | null;
}

const STORAGE_KEY = 'nene_vault_token';

export const authStore = {
  getSession(): AuthSession | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
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
      localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    } catch {
      // ignore persistence failure
    }
  },

  clearSession(): void {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
      // ignore
    }
  },

  getToken(): string | null {
    return this.getSession()?.token ?? null;
  },
};
