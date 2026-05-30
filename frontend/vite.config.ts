import path from 'node:path';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig, loadEnv } from 'vite';

const dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ mode }) => {
  // Read NENE_VAULT_PORT from the project-root .env (one level up from frontend/)
  // so the dev proxy stays in sync without duplicating the value.
  const projectEnv = loadEnv(mode, path.resolve(dirname, '..'), '');
  const appPort = projectEnv['NENE_VAULT_PORT'] ?? '8080';
  const target = `http://localhost:${appPort}`;

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
      fs: { allow: [path.resolve(dirname, '..')] },
      proxy: {
        '/admin': { target, changeOrigin: true },
        '/health': { target, changeOrigin: true },
      },
    },
  };
});
