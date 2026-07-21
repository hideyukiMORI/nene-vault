import { defineConfig, devices } from '@playwright/test';

// Playwright smoke harness — fleet T2 (Issue #279; payout #243 型).
//
// The smoke is HERMETIC: it starts the Vite dev server and stubs the API with
// page.route() (see ../tests/e2e/smoke.spec.ts), so it needs no backend, DB, or
// Docker. That keeps it fast and deterministic. It is a deliberately minimal
// boot/login/nav check; it verifies the FRONTEND wiring of the login flow, not
// real server behaviour. The live-target demo QA (docs/qa/) is a separate lane
// and lives under ../tests/e2e/live/ — never conflate the two.
//
// SAFETY: this suite must only ever run against local/staging. The guard below
// hard-fails if pointed at anything that is not localhost — never production.

const E2E_PORT = Number(process.env.NENE_VAULT_E2E_PORT) || 5186;
const BASE_URL = process.env.NENE_VAULT_E2E_BASE_URL ?? `http://localhost:${E2E_PORT}`;

const host = new URL(BASE_URL).hostname;
const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1'];
if (!LOCAL_HOSTS.includes(host) && !host.startsWith('stg.') && !host.endsWith('.local')) {
  throw new Error(
    `Refusing to run e2e against non-local host "${host}". ` +
      'This smoke is local/staging only — never point it at production.',
  );
}

export default defineConfig({
  // e2e specs live at the repo root (../tests/e2e) per the fleet T2 convention;
  // the Playwright config and its node deps stay here in frontend/. Because the
  // specs sit above frontend/node_modules, the `e2e` npm script sets
  // NODE_PATH=./node_modules so their `@playwright/test` import resolves.
  testDir: '../tests/e2e',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: [['list']],
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  // Only start a server when targeting the default local port; a custom
  // NENE_VAULT_E2E_BASE_URL (e.g. staging) is assumed already running.
  webServer: process.env.NENE_VAULT_E2E_BASE_URL
    ? undefined
    : {
        command: `npm run dev -- --port ${String(E2E_PORT)} --strictPort`,
        url: BASE_URL,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
      },
});
