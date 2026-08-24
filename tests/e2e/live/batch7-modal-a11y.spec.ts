import { expect, test } from '@playwright/test';
import { seatAdmin } from './_helpers';

/**
 * Live-target QA — batch 7: the modal actually traps focus and closes on Esc (#415).
 *
 * 🔴 This cannot live in the unit suite. jsdom does not implement `HTMLDialogElement.showModal`
 * (measured by the kit at 0.14.0 and unchanged since), and `showModal` is precisely what puts
 * the dialog in the top layer, makes the rest of the document inert, and routes Esc. A jsdom
 * test would render the markup, assert whatever it likes, and never exercise the behaviour the
 * issue is about — a green that says nothing.
 *
 * 🔴 The defect this guards against shipped, and shipped while looking correct. Before the
 * migration the dialog was a `<div role="dialog" aria-modal="true">`: it announced that the
 * rest of the page was inert while Tab walked straight out of it into the table behind
 * (reproduced on production, 2026-08-25). `aria-modal` without the behaviour is worse than
 * neither, because assistive technology is told something untrue.
 *
 * Five screens use this — two of them (`void`, `restore`) confirm an operation that cannot be
 * undone inside the retention window.
 *
 * Not wired into CI: the live lane never runs there (ruling 2026-07-21).
 */

test.describe.configure({ mode: 'serial' });

test('VLT-A11Y-01: the upload dialog closes on Esc and holds focus', async ({ browser }) => {
  test.setTimeout(3 * 60_000);

  // 🔴 The local build, not production. Production still serves the pre-migration modal —
  // that is what the defect was reproduced against, and pointing this at it would fail
  // forever while reporting nothing about whether the fix works. The comparison harness
  // (batch6) reads production on purpose; this one asserts behaviour we just changed.
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
    locale: 'en-US',
    baseURL: process.env.NENE_VAULT_LIVE_LOCAL_URL ?? 'http://localhost:5186',
  });
  const page = await ctx.newPage();
  await seatAdmin(page);

  await page.locator('nav.rail-nav').getByRole('button', { name: 'Received Documents', exact: true }).click();
  await page.waitForURL(/\/documents$/, { timeout: 15_000 });
  await page.getByRole('button', { name: 'Upload Document' }).click();

  // 🔴 Wait on the *role*, not on `<dialog>`. Waiting on the element makes every assertion
  // below unreachable the moment the element is wrong — pointed at the pre-migration build
  // this timed out on the selector and never exercised Esc or the focus trap at all. A
  // detector that can only fail for one reason is not testing the other reasons.
  const dialog = page.getByRole('dialog');
  await dialog.first().waitFor({ state: 'visible', timeout: 10_000 });

  // A native `<dialog>`, not a div wearing the role. The whole fix is that the browser owns
  // the behaviour, so the element is who is responsible for it.
  expect(
    await dialog.first().evaluate((el) => el.tagName),
    'the dialog is not a native <dialog>, so the browser is not providing the modal behaviour',
  ).toBe('DIALOG');

  // The rest of the document is inert: focus cannot be moved to a control behind the dialog
  // even when something tries to move it there deliberately.
  const inert = await page.evaluate(() => {
    const d = document.querySelector('dialog, [role="dialog"]');
    if (d === null) return null;
    const behind = Array.from(
      document.querySelectorAll<HTMLElement>('table button, nav.rail-nav button'),
    ).filter((el) => !d.contains(el));
    if (behind.length === 0) return null;
    behind[0]?.focus();
    return { candidates: behind.length, moved: document.activeElement === behind[0] };
  });
  expect(inert, 'no control behind the dialog to test against').not.toBeNull();
  expect(inert?.moved, 'focus reached a control behind an open modal').toBe(false);

  // Tab does not walk out either — every stop is inside the dialog, apart from the document
  // itself at the wrap-around point.
  const strayed: string[] = [];
  for (let i = 0; i < 30; i += 1) {
    await page.keyboard.press('Tab');
    const at = await page.evaluate(() => {
      const d = document.querySelector('dialog, [role="dialog"]');
      const a = document.activeElement;
      if (d === null || a === null) return 'none';
      if (d.contains(a)) return 'inside';
      return a === document.body ? 'body' : `${a.tagName}:${(a.textContent ?? '').trim().slice(0, 20)}`;
    });
    if (at !== 'inside' && at !== 'body') strayed.push(at);
  }
  expect(strayed, `Tab reached ${strayed.length} control(s) outside the dialog`).toEqual([]);

  // Esc closes it. The browser fires `cancel`/`close`; the kit routes both to `onClose`.
  await page.keyboard.press('Escape');
  await page.waitForTimeout(600);
  expect(await dialog.count(), 'Esc did not close the dialog').toBe(0);

  await ctx.close();
});
