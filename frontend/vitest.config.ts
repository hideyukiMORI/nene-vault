import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const dirname = path.dirname(fileURLToPath(import.meta.url));

// No Vite React plugin here: vitest bundles its own Vite, and the app's
// @vitejs/plugin-react types clash with it. JSX is transformed by esbuild from
// tsconfig (jsx: react-jsx), which is sufficient for jsdom component tests.
export default defineConfig({
  resolve: {
    alias: {
      '@': path.resolve(dirname, './src'),
      '@tests': path.resolve(dirname, './tests'),
      '@locales': path.resolve(dirname, '..', 'locales'),
    },
  },
  esbuild: {
    jsx: 'automatic',
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/setup/vitest-setup.ts'],
    css: false,
    coverage: {
      provider: 'v8',
      // json-summary feeds the shrink-only ratchet (tools/coverage-ratchet.mjs);
      // text-summary is for humans, json for drill-down. Never write html into the repo.
      reporter: ['text-summary', 'json-summary', 'json'],
      reportsDirectory: './coverage',
      // Measure the application source only. Test/story/mock/setup files and
      // type-only barrels are not units under test.
      include: ['src/**/*.{ts,tsx}'],
      exclude: [
        'src/**/*.test.{ts,tsx}',
        'src/**/*.stories.{ts,tsx}',
        'src/**/*.d.ts',
        'src/**/index.ts',
        'src/main.tsx',
        'src/**/__mocks__/**',
      ],
    },
  },
});
