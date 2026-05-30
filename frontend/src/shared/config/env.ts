import { z } from 'zod';

// Validate the public frontend env once, here. Only VITE_* values are exposed
// to the browser; never put secrets in frontend env.
const envSchema = z.object({
  apiBaseUrl: z.string().default(''),
});

const rawBaseUrl: unknown = import.meta.env.VITE_NENE_VAULT_API_BASE_URL;

export const env = envSchema.parse({
  apiBaseUrl: typeof rawBaseUrl === 'string' ? rawBaseUrl : '',
});
