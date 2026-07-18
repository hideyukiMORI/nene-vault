import { afterEach, describe, expect, it, vi } from 'vitest';

afterEach(() => {
  vi.unstubAllEnvs();
  vi.resetModules();
});

describe('env', () => {
  it('applies defaults when the VITE_ vars are absent', async () => {
    vi.resetModules();
    const { env } = await import('./env');

    expect(env.apiBaseUrl).toBe('');
    expect(env.uploadMaxFileSizeMb).toBe(20);
  });

  it('reads the API base URL and coerces the max file size to an integer', async () => {
    vi.stubEnv('VITE_NENE_VAULT_API_BASE_URL', 'https://api.example.test');
    vi.stubEnv('VITE_NENE_VAULT_MAX_FILE_SIZE_MB', '50');
    vi.resetModules();
    const { env } = await import('./env');

    expect(env.apiBaseUrl).toBe('https://api.example.test');
    expect(env.uploadMaxFileSizeMb).toBe(50);
    expect(Number.isInteger(env.uploadMaxFileSizeMb)).toBe(true);
  });
});
