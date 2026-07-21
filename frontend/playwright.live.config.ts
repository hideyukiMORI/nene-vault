import { defineConfig, devices } from '@playwright/test';

// Live-target QA harness — demo keystroke QA execution (Issue #287).
//
// This is the SEPARATE live lane (hub 裁定 2026-07-21): it drives the REAL public
// demo (vault.ayane.co.jp) with a real browser so the assertions exercise real
// server behaviour — SHA integrity, CSV neutralization, authz, timezone — which a
// hermetic stub cannot answer. It is DELIBERATELY NOT wired into CI (never let CI
// hit production) and lives apart from the hermetic @smoke config (playwright.config.ts).
//
// Safety: writes happen only inside a disposable/seeded demo org (nightly reset).
// No vault deploy runs during execution (freeze managed by hub).

const BASE_URL = process.env.NENE_VAULT_LIVE_BASE_URL ?? 'https://vault.ayane.co.jp';

export default defineConfig({
  testDir: '../tests/e2e/live',
  fullyParallel: false, // one demo org at a time; keep runs sequential & gentle
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  workers: 1,
  reporter: [['list'], ['html', { outputFolder: 'playwright-report-live', open: 'never' }]],
  timeout: 60_000,
  use: {
    baseURL: BASE_URL,
    trace: 'on',
    screenshot: 'on',
    // Real network to the live demo; no webServer.
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});
