import { z } from 'zod';

// Validate the public frontend env once, here. Only VITE_* values are exposed
// to the browser; never put secrets in frontend env.
const envSchema = z.object({
  apiBaseUrl: z.string().default(''),
  // Mirrors the backend NENE_VAULT_MAX_FILE_SIZE_MB (default 20). If the
  // server env overrides the limit, set VITE_NENE_VAULT_MAX_FILE_SIZE_MB to
  // the same value at build time so the upload hint keeps telling the truth.
  uploadMaxFileSizeMb: z.coerce.number().int().positive().default(20),
});

const rawBaseUrl: unknown = import.meta.env.VITE_NENE_VAULT_API_BASE_URL;
const rawMaxFileSizeMb: unknown = import.meta.env.VITE_NENE_VAULT_MAX_FILE_SIZE_MB;

export const env = envSchema.parse({
  apiBaseUrl: typeof rawBaseUrl === 'string' ? rawBaseUrl : '',
  uploadMaxFileSizeMb: typeof rawMaxFileSizeMb === 'string' ? rawMaxFileSizeMb : undefined,
});
