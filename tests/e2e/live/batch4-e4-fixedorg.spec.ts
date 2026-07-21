import { expect, test, type Browser } from '@playwright/test';
import { seatGuided } from './_helpers';

// Live-target QA — VLT-E4 direct proof on the GUIDED fixed org (Issue #287).
// Guided is a fixed viewer seat (no mint → unaffected by the standard rate-limit),
// so the SAME document can be opened under two timezones. formatDateTime renders
// uploaded_at in the browser-local zone; a UTC-day-boundary time shifts a calendar
// day between UTC and JST. This is the #228 direct evidence batch1 could not get
// (standard mints a different org per visit).

interface DocSnap {
  id: string;
  counterparty: string;
  // The formatDateTime-rendered timestamps (MM/DD/YYYY, HH:MM AM/PM) on the detail.
  localDateTimes: string[];
  // The raw-ISO dates (transaction_date / retention_expires_at).
  isoDates: string[];
}

async function openFirstDoc(browser: Browser, tz: string): Promise<DocSnap> {
  const ctx = await browser.newContext({ timezoneId: tz, locale: 'en-US' });
  const page = await ctx.newPage();
  await seatGuided(page);
  await page.locator('nav.rail-nav').getByRole('button', { name: 'Received Documents', exact: true }).click();
  await page.waitForURL(/\/documents$/, { timeout: 10_000 });
  await page.locator('table tbody tr').first().getByRole('button', { name: 'Details' }).click();
  await page.waitForURL(/\/documents\/[^/]+$/, { timeout: 10_000 });
  await page.waitForLoadState('networkidle');
  // Wait for the detail metadata to render before scraping (async content).
  await page.getByRole('button', { name: 'Download', exact: true }).waitFor({ state: 'visible', timeout: 10_000 });
  await page.waitForTimeout(600);
  const id = page.url().split('/documents/')[1];
  const body = await page.locator('body').innerText();
  await ctx.close();
  return {
    id,
    counterparty: '',
    localDateTimes: [...body.matchAll(/\d{1,2}\/\d{1,2}\/\d{4},?\s*\d{1,2}:\d{2}(?:\s*[AP]M)?/g)].map((m) => m[0]),
    isoDates: [...body.matchAll(/\b\d{4}-\d{2}-\d{2}\b/g)].map((m) => m[0]),
  };
}

async function openDocById(browser: Browser, tz: string, id: string): Promise<DocSnap> {
  const ctx = await browser.newContext({ timezoneId: tz, locale: 'en-US' });
  const page = await ctx.newPage();
  await seatGuided(page);
  await page.goto(`/documents/${id}`, { waitUntil: 'networkidle' });
  await page.waitForLoadState('networkidle');
  await page.getByRole('button', { name: 'Download', exact: true }).waitFor({ state: 'visible', timeout: 10_000 });
  await page.waitForTimeout(600);
  const body = await page.locator('body').innerText();
  await ctx.close();
  return {
    id,
    counterparty: '',
    localDateTimes: [...body.matchAll(/\d{1,2}\/\d{1,2}\/\d{4},?\s*\d{1,2}:\d{2}(?:\s*[AP]M)?/g)].map((m) => m[0]),
    isoDates: [...body.matchAll(/\b\d{4}-\d{2}-\d{2}\b/g)].map((m) => m[0]),
  };
}

test('VLT-E4-fixed: guided document uploaded_at is TZ-stable — host-local naive (#228)', async ({ browser }) => {
  // DIRECT proof on the SAME fixed-org document, opened under UTC and JST.
  const utc = await openFirstDoc(browser, 'UTC');
  expect(utc.id, 'opened a document on the guided fixed org').toBeTruthy();
  const jst = await openDocById(browser, 'Asia/Tokyo', utc.id);

  console.log('E4-fixed doc id:', utc.id);
  console.log('E4-fixed UTC localDateTimes:', JSON.stringify(utc.localDateTimes));
  console.log('E4-fixed JST localDateTimes:', JSON.stringify(jst.localDateTimes));
  console.log('E4-fixed UTC isoDates:', JSON.stringify(utc.isoDates));
  console.log('E4-fixed JST isoDates:', JSON.stringify(jst.isoDates));

  expect(jst.id).toBe(utc.id);
  // FINDING (#228, corrected): on the SAME document, both the formatDateTime
  // uploaded_at AND the raw-ISO transaction_date render IDENTICALLY under UTC and
  // JST. The document timestamps are host-local NAIVE (no Z): new Date() parses
  // them as browser-local and Intl re-formats as browser-local, so the wall-clock
  // digits round-trip unchanged — no TZ shift for document timestamps. (The
  // batch-1 apparent "shift" was two DIFFERENT docs across disposable orgs, not a
  // real TZ effect.) The genuine mixed-zone concern is the AUDIT trail, stored in
  // UTC (with Z) → those DO shift with browser TZ, mixing against host-local
  // document times; but audit needs ManageVaultSettings, invisible to this viewer.
  expect(jst.isoDates, 'transaction_date/retention are raw ISO → identical').toEqual(utc.isoDates);
  expect(jst.localDateTimes, 'document uploaded_at is host-local naive → TZ-stable').toEqual(
    utc.localDateTimes,
  );
});
