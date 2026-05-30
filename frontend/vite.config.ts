import path from 'node:path';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig, loadEnv } from 'vite';

const dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ mode }) => {
  // Read NENE_VAULT_PORT and NENE_VAULT_APP_HOST from the project-root .env
  // (one level up from frontend/) so the dev proxy stays in sync.
  // NENE_VAULT_APP_HOST defaults to 'localhost' but is set to 'app' inside Docker Compose.
  const projectEnv = loadEnv(mode, path.resolve(dirname, '..'), '');
  const appHost =
    process.env['NENE_VAULT_APP_HOST'] ?? projectEnv['NENE_VAULT_APP_HOST'] ?? 'localhost';
  const appPort = process.env['NENE_VAULT_PORT'] ?? projectEnv['NENE_VAULT_PORT'] ?? '8080';
  const target = `http://${appHost}:${appPort}`;

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': path.resolve(dirname, './src'),
        '@tests': path.resolve(dirname, './tests'),
        // locales/ at the repo root is the single source of truth (ADR 0005).
        '@locales': path.resolve(dirname, '..', 'locales'),
      },
    },
    server: {
      host: true,
      fs: { allow: [path.resolve(dirname, '..')] },
      proxy: {
        '/admin': { target, changeOrigin: true },
        '/health': { target, changeOrigin: true },
      },
    },
  };
});
