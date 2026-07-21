import { expect, test, type Page } from '@playwright/test';
import { seatGuided } from './_helpers';

// Live-target QA — batch 5: visual / responsive / dark-mode lane (Issue #287).
// Guided fixed seat (no mint). Captures programmatic observations + screenshots
// for the 施主/hub judgment ledger (docs/qa). These are OBSERVATIONS, not hard
// pass/fail — only a genuine break (body horizontal overflow, crash) fails.

const SHOTS = 'docs/qa/screenshots';

async function bodyOverflow(page: Page): Promise<{ scrollW: number; innerW: number; overflow: boolean }> {
  return page.evaluate(() => {
    const scrollW = document.documentElement.scrollWidth;
    const innerW = window.innerWidth;
    return { scrollW, innerW, overflow: scrollW > innerW + 1 };
  });
}

test.describe.configure({ mode: 'serial' });

for (const vp of [
  { name: '375', width: 375, height: 812 },
  { name: '768', width: 768, height: 1024 },
]) {
  test(`VLT-E2-01 @${vp.name}px: no body horizontal overflow`, async ({ browser }) => {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, locale: 'en-US' });
    const page = await ctx.newPage();
    await seatGuided(page);
    await page.screenshot({ path: `${SHOTS}/e2-${vp.name}-home.png`, fullPage: true });
    await page.locator('nav.rail-nav').getByRole('button', { name: 'Received Documents', exact: true }).click();
    await page.waitForURL(/\/documents$/, { timeout: 10_000 });
    await page.waitForTimeout(500);
    const o = await bodyOverflow(page);
    console.log(`E2-${vp.name} overflow:`, JSON.stringify(o));
    await page.screenshot({ path: `${SHOTS}/e2-${vp.name}-documents.png`, fullPage: true });
    // The body itself must not scroll horizontally (tables may scroll inside).
    expect(o.overflow, `body overflows at ${vp.name}px (scrollW ${o.scrollW} > innerW ${o.innerW})`).toBe(false);
    await ctx.close();
  });
}

test('VLT-E1-01: OS dark mode renders light-fixed without breakage', async ({ browser }) => {
  const ctx = await browser.newContext({ colorScheme: 'dark', locale: 'en-US' });
  const page = await ctx.newPage();
  await seatGuided(page);
  await page.screenshot({ path: `${SHOTS}/e1-dark-home.png`, fullPage: true });
  // The app is light-fixed (no runtime theme toggle). Under OS dark it must still
  // render readable — assert the shell is present and the computed background is
  // not transparent/black-on-black (a real break would be an unstyled page).
  const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
  console.log('E1 dark-mode body background:', bg);
  await expect(page.locator('nav.rail-nav')).toBeVisible();
  expect(bg, 'body has a real background under OS dark').not.toBe('rgba(0, 0, 0, 0)');
  await ctx.close();
});

test('VLT-F1-01: first-impression — guided lands on the sell (docs + search)', async ({ browser }) => {
  const ctx = await browser.newContext({ locale: 'en-US' });
  const page = await ctx.newPage();
  await seatGuided(page);
  await page.locator('nav.rail-nav').getByRole('button', { name: 'Received Documents', exact: true }).click();
  await page.waitForURL(/\/documents$/, { timeout: 10_000 });
  await page.waitForTimeout(500);
  // A first-timer should immediately see the core sell: a documents table with
  // rows and a search affordance.
  const rows = await page.locator('table tbody tr').count();
  const hasSearch = await page.getByRole('button', { name: /search|検索/i }).count();
  console.log('F1 rows:', rows, 'search affordance:', hasSearch);
  await page.screenshot({ path: `${SHOTS}/f1-documents.png`, fullPage: true });
  expect(rows, 'seeded documents visible on landing').toBeGreaterThan(0);
  expect(hasSearch, 'search affordance present').toBeGreaterThan(0);
  await ctx.close();
});
