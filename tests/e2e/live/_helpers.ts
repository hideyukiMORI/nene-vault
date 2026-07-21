import { expect, type Page } from '@playwright/test';

// Shared live-QA helpers (Issue #287). The org-reuse pattern: seat ONE disposable
// admin org per spec file and drive many scenarios inside it, so we mint gently
// (1 org / scenario file, not 1 / test) — the demo rate-limits rapid minting
// (VLT-A6-04, observed live). Callers use a serial describe + a page seated once.

export async function seatAdmin(page: Page): Promise<void> {
  // Patient + one retry; never hammer (VLT-A6-04).
  await page.goto('/demo/standard', { waitUntil: 'networkidle' });
  try {
    await page.waitForSelector('nav.rail-nav', { timeout: 30_000 });
  } catch {
    await page.goto('/demo/standard', { waitUntil: 'networkidle' });
    await page.waitForSelector('nav.rail-nav', { timeout: 30_000 });
  }
}

export async function seatGuided(page: Page): Promise<void> {
  await page.goto('/demo/guided', { waitUntil: 'networkidle' });
  await page.waitForSelector('nav.rail-nav', { timeout: 30_000 });
}

export async function railTo(page: Page, name: string): Promise<void> {
  await page.locator('nav.rail-nav').getByRole('button', { name, exact: true }).click();
}

export async function gotoDocuments(page: Page): Promise<void> {
  await railTo(page, 'Received Documents');
  await page.waitForURL(/\/documents$/, { timeout: 10_000 });
}

export interface UploadOpts {
  bytes: Buffer;
  filename?: string;
  mimeType?: string;
  counterparty: string;
}

export async function uploadDoc(page: Page, o: UploadOpts): Promise<void> {
  await page.getByRole('button', { name: 'Upload Document' }).click();
  await page.locator('.modal').waitFor({ state: 'visible' });
  await page.locator('.modal input[name="file"]').setInputFiles({
    name: o.filename ?? 'invoice.pdf',
    mimeType: o.mimeType ?? 'application/pdf',
    buffer: o.bytes,
  });
  await page.locator('.modal input[name="counterparty_name"]').fill(o.counterparty);
  await page.locator('.modal').getByRole('button', { name: 'Upload', exact: true }).click();
}

export async function openDoc(page: Page, counterparty: string): Promise<void> {
  const row = page.locator('table tbody tr', { hasText: counterparty }).first();
  await row.waitFor({ state: 'visible', timeout: 10_000 });
  await row.getByRole('button', { name: 'Details' }).click();
  await page.waitForURL(/\/documents\/[^/]+$/, { timeout: 10_000 });
  await page.waitForLoadState('networkidle');
}

/** Assert no unexpected console errors accumulated (VLT-F4). */
export function expectNoConsoleErrors(errs: string[]): void {
  const unexpected = errs.filter(
    (e) => !/401|403|404|Failed to load resource.*(401|403|404)/.test(e),
  );
  expect(unexpected, `unexpected console errors: ${JSON.stringify(unexpected)}`).toHaveLength(0);
}

export const MINI_PDF = Buffer.from(
  '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n',
);
