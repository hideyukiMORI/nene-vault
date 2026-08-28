/**
 * Screenshot the REAL screens — real router, real cascade, real layer order — against a dev
 * server whose API is answered in-browser by the MSW handlers (`VITE_MSW=1`).
 *
 * 🔴 Why this is not the harness. `Button.stories.tsx` renders the primitive in isolation and
 * cannot speak for a page; this drives the actual application. What it still is not: a
 * production comparison. There is no backend here, the data is fixtures, and nothing has been
 * seen on `vault.ayane.co.jp`. `meta.json` records `isRealScreen: true` and
 * `hasRealBackend: false` so the two can never be conflated later.
 *
 * 🔴 It asserts arrival before it asserts anything else. A selector that finds zero kit
 * buttons on a page reads exactly the same as a page that never loaded — nene-deal measured
 * "0 kit buttons" on three screens on 2026-08-28 and was in fact sitting on the login form
 * the whole time. Every screen below declares a landmark that must be present, and a run that
 * does not reach it fails loudly instead of reporting a clean zero.
 *
 * Usage:  VITE_MSW=1 npx vite --port 5199 &
 *         node tools/shoot-real-screens.mjs [outDir]
 */
import { chromium } from 'playwright';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

const ROOT = new URL('..', import.meta.url).pathname;
const BASE = process.env.VAULT_MSW_URL ?? 'http://localhost:5199';
const OUT_DIR = process.argv[2] ?? join(ROOT, 'real-screens');
const DOCUMENT_ID = 'doc-01J0000000000000000000000';

/**
 * 🔴 `minButtons` is the arrival check that matters, and `landmark` alone is not enough.
 *
 * A landmark says "something rendered", not "the thing I am about to count rendered". Measured
 * on production 2026-08-28: on document-detail the `<h1>` paints before the toolbar, because
 * the toolbar is gated on capabilities that arrive with a later response — so a sweep that
 * waited for `h1` and then counted read **zero kit buttons on a page that has four**. It
 * reported a clean zero, exactly the shape nene-deal hit the same day from a different cause
 * (sitting on the login form). **A screen not finished and a screen with nothing on it return
 * the same number.**
 *
 * So each screen declares how many kit buttons it must end up with, and the run *waits for
 * that count* rather than sampling once. A screen that never reaches it fails loudly.
 * Declaring the number also makes a regression visible: if a screen quietly loses a button,
 * the wait times out instead of silently capturing one fewer.
 */
const SCREENS = [
  { name: 'documents', path: '/documents', landmark: 'table, [role="table"]', minButtons: 1 },
  {
    name: 'document-detail',
    path: `/documents/${DOCUMENT_ID}`,
    landmark: 'h1',
    minButtons: 4,
    openDialog: '無効化',
  },
  { name: 'audit', path: '/audit', landmark: 'h1', minButtons: 1 },
  { name: 'users', path: '/users', landmark: 'h1', minButtons: 1 },
];

const VIEWPORTS = [
  { tag: 'desktop', width: 1280, height: 900 },
  { tag: 'sp375', width: 375, height: 812 },
];

const browser = await chromium.launch();
await mkdir(OUT_DIR, { recursive: true });

const readButtons = (page) =>
  page.evaluate(() =>
    [...document.querySelectorAll('button')]
      .filter((el) => el.className.includes('x-slot-button'))
      .map((el) => {
        const s = getComputedStyle(el);
        const r = el.getBoundingClientRect();
        return {
          label: el.textContent.trim().slice(0, 24),
          color: s.color,
          backgroundColor: s.backgroundColor,
          borderTopColor: s.borderTopColor,
          borderTopWidth: s.borderTopWidth,
          boxShadow: s.boxShadow === 'none' ? 'none' : 'present',
          height: Math.round(r.height * 100) / 100,
          // 🔴 Wrapping is the point of the 375 pass (fleet #501). Count the line boxes the
          // label's own text actually occupies — `Range.getClientRects()` returns one rect
          // per line box. Dividing the button's height by its line-height does NOT work:
          // padding is in that height, so a comfortably single-line button reports 2.
          lines: (() => {
            const node = [...el.childNodes].find(
              (n) => n.nodeType === 3 && n.textContent.trim() !== '',
            );
            if (!node) return null;
            const range = document.createRange();
            range.selectNodeContents(node);
            return range.getClientRects().length;
          })(),
        };
      }),
  );

const login = async (page) => {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', 'admin@example.com');
  await page.fill('input[type="password"]', 'secret');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 15000 });
};

const results = [];
const failures = [];
// 🔴 Counted, not computed. The denominator used to be `SCREENS.length * VIEWPORTS.length`,
// which stopped being true the moment the dialog captures were added — it printed "10/8".
// A ratio whose denominator is a formula drifts away from what the loop actually did.
let attempted = 0;

for (const vp of VIEWPORTS) {
  // 🔴 `locale` matters. The app falls back to the browser's language (`locales.ts`), and
  // Playwright's default is en-US — which would have measured wrapping on English labels
  // while this product ships to Japanese users. The longest label it renders is Japanese.
  const context = await browser.newContext({
    viewport: { width: vp.width, height: vp.height },
    deviceScaleFactor: 2,
    locale: 'ja-JP',
  });
  const page = await context.newPage();
  await login(page);

  for (const screen of SCREENS) {
    attempted += 1;
    await page.goto(`${BASE}${screen.path}`, { waitUntil: 'networkidle' });

    // 🔴 Arrival check, before anything is counted, in two steps: the landmark says the route
    // resolved, and the button count says the part being measured has actually painted.
    const onLogin = page.url().includes('/login');
    const landmark = await page
      .locator(screen.landmark)
      .first()
      .isVisible()
      .catch(() => false);
    if (onLogin || !landmark) {
      failures.push(
        `${screen.name}@${vp.tag}: not reached (url=${page.url()}, landmark=${landmark})`,
      );
      continue;
    }

    // Wait for the declared count instead of sampling once — see the note on SCREENS.
    const arrived = await page
      .waitForFunction(
        (min) =>
          [...document.querySelectorAll('button')].filter(
            (el) => el.className.includes('x-slot-button') && el.getBoundingClientRect().height > 0,
          ).length >= min,
        screen.minButtons,
        { timeout: 15000 },
      )
      .then(() => true)
      .catch(() => false);

    const buttons = await readButtons(page);
    if (!arrived) {
      // Never a clean zero: say what was expected, what was found, and that this is a failure.
      failures.push(
        `${screen.name}@${vp.tag}: reached, but only ${buttons.length} kit button(s) after 15s ` +
          `(expected >= ${screen.minButtons}) — the page did not finish, or the selector is wrong`,
      );
    }

    const file = `real-${screen.name}-${vp.tag}.png`;
    await page.screenshot({ path: join(OUT_DIR, file), fullPage: true });
    results.push({ screen: screen.name, viewport: vp.tag, file, buttons });

    // 🔴 The confirm button for voiding a document lives inside a dialog, so a page-level
    // sweep never sees it — and it is the single most important button in this upgrade
    // (`VoidModal.tsx:77`, the destructive action's confirm). Open it deliberately.
    if (screen.name === 'document-detail' && screen.openDialog) {
      await page.getByRole('button', { name: screen.openDialog }).click();
      const dialog = page.getByRole('dialog');
      if (!(await dialog.isVisible().catch(() => false))) {
        failures.push(`${screen.name}@${vp.tag}: dialog did not open`);
        continue;
      }
      attempted += 1;
      const dialogButtons = await readButtons(page);
      const dialogFile = `real-${screen.name}-dialog-${vp.tag}.png`;
      await page.screenshot({ path: join(OUT_DIR, dialogFile), fullPage: true });
      results.push({
        screen: `${screen.name}-void-dialog`,
        viewport: vp.tag,
        file: dialogFile,
        buttons: dialogButtons,
      });
      // Leave the page clean for the next navigation — a dialog left open swallows clicks.
      await page.keyboard.press('Escape');
    }
  }

  await context.close();
}

const git = (args) => execFileSync('git', args, { cwd: ROOT, encoding: 'utf8' }).trim();
const uiVersion = JSON.parse(
  await readFile(join(ROOT, 'node_modules/@hideyukimori/nene2-ui/package.json'), 'utf8'),
).version;
const dirty = git(['status', '--porcelain']);

await writeFile(
  join(OUT_DIR, 'meta.json'),
  `${JSON.stringify(
    {
      isRealScreen: true,
      hasRealBackend: false,
      kind: 'msw-dev-server',
      whatThisIsNot:
        'Not a production comparison. Real application and real cascade, but the API is MSW fixtures in the browser and nothing was observed on vault.ayane.co.jp.',
      capturedAt: new Date().toISOString(),
      head: git(['rev-parse', 'HEAD']),
      branch: git(['rev-parse', '--abbrev-ref', 'HEAD']),
      workingTreeClean: dirty === '',
      uncommittedFiles: dirty === '' ? [] : dirty.split('\n').map((l) => l.slice(3)),
      nene2UiVersion: uiVersion,
      screensAttempted: attempted,
      screensCaptured: results.length,
      failures,
      results,
    },
    null,
    2,
  )}\n`,
);

await browser.close();

if (failures.length > 0) {
  console.error(`FAILED — ${failures.length} screen(s) not reached or empty:`);
  for (const f of failures) console.error(`  ${f}`);
  process.exit(1);
}
console.log(`captured ${results.length}/${attempted} screens to ${OUT_DIR}`);
