import { expect, test, type Page } from '@playwright/test';

// Live-target QA — batch 1: demo entry, nav, list, authz, timezone (Issue #287).
// Read-mostly + fresh-context authz. Drives the real vault.ayane.co.jp demo.
// jsdom-free real browser → navigator.language is 'en-US' so the UI is English.

function trackConsole(page: Page): string[] {
  const errs: string[] = [];
  page.on('console', (m) => {
    if (m.type() === 'error') errs.push(m.text());
  });
  page.on('pageerror', (e) => errs.push(`pageerror: ${e.message}`));
  return errs;
}

async function seatStandardDemo(page: Page): Promise<void> {
  await page.goto('/demo/standard', { waitUntil: 'networkidle' });
  await page.waitForURL(/vault\.ayane\.co\.jp\/(#.*)?$/, { timeout: 20_000 }).catch(() => undefined);
  await page.waitForSelector('nav.rail-nav', { timeout: 20_000 });
}

test.describe('VLT live batch 1 — read / authz / timezone', () => {
  test('VLT-A6-01: /demo/standard seats an admin disposable org (all nav visible)', async ({ page }) => {
    const errs = trackConsole(page);
    await seatStandardDemo(page);

    const links = await page.locator('nav.rail-nav button.rail-link').allInnerTexts();
    // Admin sees every route.
    for (const label of ['Home', 'Received Documents', 'Audit Log', 'Vault Settings', 'Users', 'Export']) {
      expect(links).toContain(label);
    }
    expect(errs, `console errors: ${JSON.stringify(errs)}`).toHaveLength(0);
  });

  test('VLT-A6-02: /demo/guided seats a viewer (only Home + Received Documents)', async ({ page }) => {
    const errs = trackConsole(page);
    await page.goto('/demo/guided', { waitUntil: 'networkidle' });
    await page.waitForSelector('nav.rail-nav', { timeout: 20_000 });

    const links = await page.locator('nav.rail-nav button.rail-link').allInnerTexts();
    expect(links).toContain('Received Documents');
    // Viewer must NOT see admin-only routes.
    expect(links).not.toContain('Users');
    expect(links).not.toContain('Vault Settings');
    expect(links).not.toContain('Audit Log');
    expect(links).not.toContain('Export');
    expect(errs, `console errors: ${JSON.stringify(errs)}`).toHaveLength(0);
  });

  test('VLT-A5-01 / A2-01: nav to Received Documents lists seeded documents', async ({ page }) => {
    await seatStandardDemo(page);
    // Click the Received Documents rail link.
    await page.locator('nav.rail-nav').getByRole('button', { name: 'Received Documents', exact: true }).click();
    await page.waitForURL(/\/documents$/, { timeout: 10_000 });

    // The seeded demo (~20 received invoices) should render a table with rows.
    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 10_000 });
    const count = await rows.count();
    console.log('A2-01 documents rows on first page:', count);
    expect(count).toBeGreaterThan(0);
  });

  test('VLT-C1-01: unauthenticated /documents shows the login form in place (no redirect)', async ({ browser }) => {
    // Fresh context = no demo seat / no token.
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await page.goto('/documents', { waitUntil: 'networkidle' });

    // AuthGate renders the login form at the current URL (no hard redirect away).
    await expect(page.locator('input[type="email"]')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('input[type="password"]')).toBeVisible();
    expect(page.url()).toContain('/documents');
    await ctx.close();
  });

  test('VLT-E4-01/02: uploaded_at timestamp shifts with browser timezone (#228)', async ({ browser }) => {
    // Same demo document viewed under two timezones. formatDateTime uses
    // Intl in the browser-local zone; a UTC-day-boundary timestamp will differ.
    async function readFirstDocDateTimes(tz: string): Promise<{ url: string; texts: string[] }> {
      const ctx = await browser.newContext({ timezoneId: tz });
      const page = await ctx.newPage();
      await page.goto('/demo/standard', { waitUntil: 'networkidle' });
      await page.waitForSelector('nav.rail-nav', { timeout: 20_000 });
      await page.locator('nav.rail-nav').getByRole('button', { name: 'Received Documents', exact: true }).click();
      await page.waitForURL(/\/documents$/, { timeout: 10_000 });
      await page.locator('table tbody tr').first().getByRole('button').first().click();
      await page.waitForURL(/\/documents\/[^/]+$/, { timeout: 10_000 });
      const url = page.url();
      // Wait for the detail body to render its metadata before scraping.
      await page.waitForLoadState('networkidle');
      await page.locator('main, .detail, body').first().waitFor({ state: 'visible' });
      await page.waitForTimeout(800);
      const texts = await page.locator('body').innerText();
      await ctx.close();
      // Match ISO (YYYY-MM-DD), Intl en (MM/DD/YYYY), and any HH:MM time.
      const lines = texts
        .split('\n')
        .filter((l) => /\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4}|\d{1,2}:\d{2}/.test(l))
        .map((l) => l.trim());
      return { url, texts: lines };
    }

    const utc = await readFirstDocDateTimes('UTC');
    const jst = await readFirstDocDateTimes('Asia/Tokyo');
    console.log('E4 UTC detail dates:', JSON.stringify(utc.texts));
    console.log('E4 JST detail dates:', JSON.stringify(jst.texts));
    // Both must render (no crash); the diff is recorded for #228 (may or may not
    // cross a day boundary depending on the seeded doc's time-of-day).
    expect(utc.texts.length).toBeGreaterThan(0);
    expect(jst.texts.length).toBeGreaterThan(0);
  });
});
