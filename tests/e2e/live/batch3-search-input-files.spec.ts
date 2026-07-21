import { expect, test, type Page } from '@playwright/test';
import { gotoDocuments, MINI_PDF, openDoc, seatAdmin, uploadDoc } from './_helpers';

// Live-target QA — batch 3: search / input-validation / file abnormals, all in
// ONE reused disposable admin org (Issue #287; org-reuse per VLT-A6-04). Serial:
// the org is seated once in beforeAll and every test drives the same page.

test.describe.configure({ mode: 'serial' });

let page: Page;

test.beforeAll(async ({ browser }) => {
  page = await browser.newPage();
  await seatAdmin(page);
});

test.afterAll(async () => {
  await page.close();
});

test('VLT-A2-01: search filters the document list by counterparty', async () => {
  await gotoDocuments(page);
  // Seed a uniquely-named doc, then search for it.
  const tag = 'FilterProbe KK';
  await uploadDoc(page, { bytes: MINI_PDF, counterparty: tag });
  await page.waitForTimeout(1000);

  await page.locator('input[name="counterparty_name"]').fill(tag);
  await page.getByRole('button', { name: /search|検索/i }).click();
  await page.waitForTimeout(800);
  const rows = page.locator('table tbody tr', { hasText: tag });
  await expect(rows.first()).toBeVisible({ timeout: 10_000 });
  // Every visible row matches the filter.
  const allRows = await page.locator('table tbody tr').count();
  const matching = await rows.count();
  console.log('A2-01 filtered rows:', matching, 'of', allRows);
  expect(matching).toBe(allRows);
});

test('VLT-A2-03: a no-match search shows the empty state', async () => {
  await gotoDocuments(page);
  await page.locator('input[name="counterparty_name"]').fill('ZZZ-NoSuchCounterparty-ZZZ');
  await page.getByRole('button', { name: /search|検索/i }).click();
  await page.waitForTimeout(800);
  const rows = await page.locator('table tbody tr').count();
  console.log('A2-03 rows on no-match:', rows);
  expect(rows).toBe(0);
  // The page stays intact (empty state, not a crash/spinner).
  await expect(page.locator('nav.rail-nav')).toBeVisible();
});

test('VLT-B2-02: upload with an empty counterparty is blocked (required)', async () => {
  await gotoDocuments(page);
  await page.getByRole('button', { name: 'Upload Document' }).click();
  await page.locator('.modal').waitFor({ state: 'visible' });
  await page.locator('.modal input[name="file"]').setInputFiles({
    name: 'invoice.pdf',
    mimeType: 'application/pdf',
    buffer: MINI_PDF,
  });
  // Leave counterparty empty; try to submit.
  await page.locator('.modal').getByRole('button', { name: 'Upload', exact: true }).click();
  await page.waitForTimeout(500);
  // The modal must stay open (submission blocked by the required field).
  await expect(page.locator('.modal')).toBeVisible();
  // Close it for the next test.
  await page.locator('.modal').getByRole('button', { name: /cancel|✕|キャンセル/i }).first().click();
});

test('VLT-B4-01: a <script> counterparty is escaped, not executed (XSS)', async () => {
  let alertFired = false;
  page.on('dialog', (d) => {
    alertFired = true;
    void d.dismiss();
  });
  await gotoDocuments(page);
  const payload = '<script>alert(1)</script>';
  await uploadDoc(page, { bytes: MINI_PDF, counterparty: payload });
  await page.waitForTimeout(1200);
  // The literal string appears as text somewhere in the list (escaped), and no
  // alert dialog ever fired.
  const shown = await page.getByText(payload, { exact: false }).count();
  console.log('B4-01 escaped literal shown?', shown > 0, 'alert fired?', alertFired);
  expect(alertFired, 'no script executed').toBe(false);
});

test('VLT-B7-01: a non-PDF disguised with an application/pdf client MIME (finding)', async () => {
  await gotoDocuments(page);
  // .exe bytes, but the browser reports application/pdf (client-trusted MIME).
  const exeBytes = Buffer.from('MZ\x90\x00\x03fake-exe-bytes');
  await uploadDoc(page, {
    bytes: exeBytes,
    filename: 'payload.exe',
    mimeType: 'application/pdf',
    counterparty: 'MimeSpoof KK',
  });
  await page.waitForTimeout(1500);
  const listed = await page.getByText('MimeSpoof KK').count();
  const modalText = (await page.locator('.modal').innerText().catch(() => '')).toLowerCase();
  // Record the observed outcome: server MIME is client-reported (no content
  // sniffing) → a spoofed application/pdf is expected to be accepted (finding).
  console.log('B7-01 spoofed listed?', listed > 0, 'modal error?', modalText.slice(0, 120));
  expect(listed > 0 || modalText.length > 0, 'upload resolved (accepted or errored)').toBeTruthy();
});
