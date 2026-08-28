import { rm } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig, loadEnv, type Plugin } from 'vite';

const dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Keep msw's service worker out of production builds.
 *
 * `msw init` puts `mockServiceWorker.js` in `public/`, which is where it has to be for the
 * dev-only mock to register it (`VITE_MSW=1`, see `src/main.tsx`). Vite copies everything in
 * `public/` into `dist/` verbatim, so without this the worker would be deployed and sit at
 * `https://…/mockServiceWorker.js` on the production host.
 *
 * 🔴 It would be inert there — a service worker does nothing until a page registers it, and
 * the only code that registers this one is dropped from production builds. "Inert" is not
 * "should ship": it is a request-interception layer published on the origin that serves
 * received-document archives, and nothing in the product needs it.
 *
 * Verified from the other side by `tools/assert-no-msw-in-build.mjs`, which reads `dist/`
 * after the build rather than trusting this plugin ran.
 */
const stripMswWorker = (): Plugin => ({
  name: 'vault-strip-msw-worker',
  apply: 'build',
  // `closeBundle` runs after Vite has copied `public/`, which `generateBundle` does not.
  closeBundle: async () => {
    await rm(path.resolve(dirname, 'dist/mockServiceWorker.js'), { force: true });
  },
});

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
    plugins: [react(), tailwindcss(), stripMswWorker()],
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
        // The demo entry points are backend routes that hand back an HTML bootstrap page and
        // then land the visitor in this SPA. In production both live on one origin; in dev the
        // SPA is 5186 and the API is 8600, so without this line `/demo/standard` is answered by
        // Vite with index.html — a 200 that is not the demo, which is the worst of the three
        // possible answers. Off unless the backend has DEMO_MODE set; it 404s otherwise.
        '/demo': { target, changeOrigin: true },
      },
    },
  };
});
