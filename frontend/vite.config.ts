import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

// The repository-root locales/ directory is the single source of truth for UI
// strings (ADR 0005). Allow Vite to read it and expose a stable alias.
const projectRoot = resolve(import.meta.dirname, '..');

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@locales': resolve(projectRoot, 'locales'),
    },
  },
  server: {
    fs: {
      // Permit importing locales/ from the repo root (one level above frontend/).
      allow: [projectRoot],
    },
    proxy: {
      // Proxy admin/health API calls to the local backend during development.
      '/admin': 'http://localhost:8080',
      '/health': 'http://localhost:8080',
    },
  },
});
