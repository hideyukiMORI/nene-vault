import { afterEach, describe, expect, it } from 'vitest';
import { authStore } from './model';

const SESSION = {
  token: 'test-jwt',
  userId: 1,
  email: 'admin@example.com',
  role: 'admin',
  orgId: 1,
};

afterEach(() => {
  sessionStorage.clear();
});

describe('authStore.setSession / getSession', () => {
  it('persists a session and retrieves it', () => {
    authStore.setSession(SESSION);
    expect(authStore.getSession()).toEqual(SESSION);
  });

  it('returns null before any session is set', () => {
    expect(authStore.getSession()).toBeNull();
  });

  it('overwrites an existing session', () => {
    authStore.setSession(SESSION);
    const updated = { ...SESSION, email: 'other@example.com' };
    authStore.setSession(updated);
    expect(authStore.getSession()?.email).toBe('other@example.com');
  });
});

describe('authStore.getToken', () => {
  it('returns the token when a session exists', () => {
    authStore.setSession(SESSION);
    expect(authStore.getToken()).toBe('test-jwt');
  });

  it('returns null when no session exists', () => {
    expect(authStore.getToken()).toBeNull();
  });
});

describe('authStore.clearSession', () => {
  it('removes the session', () => {
    authStore.setSession(SESSION);
    authStore.clearSession();
    expect(authStore.getSession()).toBeNull();
    expect(authStore.getToken()).toBeNull();
  });

  it('is idempotent when called with no session', () => {
    expect(() => {
      authStore.clearSession();
    }).not.toThrow();
  });
});

describe('authStore.subscribe', () => {
  it('notifies on setSession and clearSession, and unsubscribes cleanly', () => {
    let calls = 0;
    const unsubscribe = authStore.subscribe(() => {
      calls += 1;
    });

    authStore.setSession(SESSION);
    expect(calls).toBe(1);

    authStore.clearSession();
    expect(calls).toBe(2);

    unsubscribe();
    authStore.setSession(SESSION);
    expect(calls).toBe(2);
  });
});

describe('authStore resilience', () => {
  it('returns null when sessionStorage contains malformed JSON', () => {
    sessionStorage.setItem('nene_vault_token', '{bad json');
    expect(authStore.getSession()).toBeNull();
  });
});
