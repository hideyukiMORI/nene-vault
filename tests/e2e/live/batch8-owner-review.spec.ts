import { expect, test, type Browser, type Page, type TestInfo } from '@playwright/test';
import { execSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { seatAdmin } from './_helpers';

/**
 * Live-target QA — batch 8: owner-review material (#439).
 *
 * Produces what the owner looks at before a kit-migration wave goes to production
 * (owner ruling 2026-08-23: "本番デプロイ前に僕の目視確認を義務化" — once per wave, not per PR).
 * Full-page screenshots of the same screens on PRODUCTION and on a LOCAL build, side by
 * side in one HTML file. A person opens `index.html`, looks, and records GO / NG per screen
 * on the wave's tracking issue. Nothing here decides anything.
 *
 * 🔴 NOT A COMPARISON. `batch6-kit-parity` measures computed styles and reports differences;
 * this batch only shows pictures. A row that "looks different" is expected — the design
 * constraint was lifted on 2026-08-23 — and the question the owner answers is "does it work
 * and does it look put together", not "is it identical".
 *
 * 🔴 KNOW WHAT THE LEFT COLUMN IS. Production is whatever was last deployed, not "the current
 * design". `meta.json` records the local HEAD and kit version; the production build is
 * whatever `vault.ayane.co.jp` serves at the time of the run — record it in the issue.
 *
 * Both sides are seated through `/demo/standard` (admin seat, disposable org, no credentials).
 * One org per side per run: the viewport is changed on the same page rather than minting
 * again (the demo rate-limits rapid minting, VLT-A6-04). The disposable org reseeds per
 * request, so the *content* differs between the two columns by design — look at the chrome.
 *
 * 🔴 The unstyled build is the one hard failure (the `@source` regression, #387): a local page
 * with none of the kit's slot utilities would produce a "redesign" that is really a broken
 * build. Checked once per side before any screenshot is taken.
 *
 * NOT wired into CI, and it must not be: CI never touches production (裁定 2026-07-21).
 *
 * Output: `docs/qa/owner-review/<YYYY-MM-DD>/` — PNGs, `index.html`, `meta.json`.
 * The directory is gitignored; the material is per-wave and disposable. What persists is the
 * verdict on the issue and this generator.
 *
 * Usage:
 *   # local target up first (API 8600 with DEMO_MODE=1 / frontend 5186), then
 *   npm run e2e:live --prefix frontend -- batch8
 *   # another local build / preview
 *   NENE_VAULT_PARITY_LOCAL_URL=http://localhost:4173 npm run e2e:live --prefix frontend -- batch8
 *   # fixed output directory (default: today's date)
 *   NENE_VAULT_OWNER_REVIEW_DIR=w1 npm run e2e:live --prefix frontend -- batch8
 */

const LOCAL_URL = process.env.NENE_VAULT_PARITY_LOCAL_URL ?? 'http://localhost:5186';

/** Resolved from Playwright's `rootDir` (= tests/e2e/live), never from CWD — see batch6. */
function outDir(rootDir: string, name: string): string {
  return resolve(rootDir, '../../../docs/qa/owner-review', name);
}

const VIEWPORTS = [
  { name: 'desktop', width: 1280, height: 800 },
  { name: 'mobile', width: 375, height: 812 },
] as const;

/** One screen: how to reach it from the seated landing page, and how to know we are there. */
interface Screen {
  name: string;
  /** Rail button label, or null for the landing screen. */
  rail: string | null;
  urlPattern: RegExp | null;
  /** Extra steps after arriving (open a modal, open a row). */
  then?: (page: Page) => Promise<void>;
  /**
   * Viewport-only capture. A modal is fixed to the viewport; a full-page capture of the
   * screen behind it renders the dialog as a sliver at the top of a 7,000px column (measured
   * 2026-08-25 at 375px) and shows nothing the owner is meant to look at.
   */
  viewportOnly?: boolean;
}

const SCREENS: Screen[] = [
  { name: 'home', rail: null, urlPattern: null },
  { name: 'documents', rail: 'Received Documents', urlPattern: /\/documents$/ },
  {
    name: 'upload-modal',
    rail: 'Received Documents',
    urlPattern: /\/documents$/,
    viewportOnly: true,
    then: async (page) => {
      await page.getByRole('button', { name: 'Upload Document' }).click();
      // Kit `Modal` is a native <dialog>; the pre-migration one was `.modal`. Either is fine —
      // the point of this screen is that the owner sees whichever one the build renders.
      await page
        .locator('dialog[open], .modal')
        .first()
        .waitFor({ state: 'visible', timeout: 10_000 });
    },
  },
  {
    name: 'document-detail',
    rail: 'Received Documents',
    urlPattern: /\/documents$/,
    then: async (page) => {
      const row = page.locator('table tbody tr').first();
      await row.waitFor({ state: 'visible', timeout: 10_000 });
      await row.getByRole('button', { name: 'Details' }).click();
      await page.waitForURL(/\/documents\/[^/]+$/, { timeout: 10_000 });
      await page.waitForLoadState('networkidle');
    },
  },
  { name: 'audit', rail: 'Audit Log', urlPattern: /\/audit$/ },
  { name: 'export', rail: 'Export', urlPattern: /\/export$/ },
  { name: 'settings', rail: 'Vault Settings', urlPattern: /\/settings$/ },
];

interface Shot {
  side: 'prod' | 'local';
  screen: string;
  viewport: string;
  file: string | null;
  /** Why there is no picture — reported, never silently skipped. */
  note: string | null;
}

async function goHome(page: Page, base: string): Promise<void> {
  await page.goto(`${base}/`, { waitUntil: 'networkidle' });
  await page.locator('nav.rail-nav').waitFor({ state: 'visible', timeout: 15_000 });
}

async function reach(page: Page, base: string, screen: Screen): Promise<void> {
  await goHome(page, base);
  if (screen.rail) {
    await page
      .locator('nav.rail-nav')
      .getByRole('button', { name: screen.rail, exact: true })
      .click();
    if (screen.urlPattern) await page.waitForURL(screen.urlPattern, { timeout: 10_000 });
    await page.waitForLoadState('networkidle');
  }
  if (screen.then) await screen.then(page);
  // Let transitions and lazy images settle; bounded, never fatal.
  await page.waitForTimeout(400);
}

/** The unstyled-build guard: the kit's slot utilities must be on a kit-bearing screen (local side only). */
async function assertStyled(page: Page, side: string): Promise<void> {
  const slots = await page.locator('[class*="x-slot"]').count();
  expect(
    slots,
    `${side}: no kit slot utility on the page — unstyled build? (#387)`,
  ).toBeGreaterThan(0);
}

async function shootSide(
  browser: Browser,
  side: 'prod' | 'local',
  base: string,
  dir: string,
  guardStyled: boolean,
): Promise<Shot[]> {
  const shots: Shot[] = [];
  const ctx = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    locale: 'en-US',
  });
  const page = await ctx.newPage();
  // Seat once per side. `seatAdmin` navigates relative to baseURL, so give it the absolute URL.
  await page.goto(`${base}/demo/standard`, { waitUntil: 'networkidle' });
  try {
    await page.waitForSelector('nav.rail-nav', { timeout: 30_000 });
  } catch {
    await page.goto(`${base}/demo/standard`, { waitUntil: 'networkidle' });
    await page.waitForSelector('nav.rail-nav', { timeout: 30_000 });
  }
  // 🔴 Guard on /documents, not on the landing page: the landing is this product's own
  // markup (rail, quick-access cards) and carries no kit utility even when fully styled —
  // measured 2026-08-25, 158 classes and 0 `x-slot` on a page that rendered correctly.
  // The first kit component a visitor meets is the Upload button on /documents.
  if (guardStyled) {
    await reach(page, base, SCREENS[1]);
    await assertStyled(page, side);
  }

  for (const vp of VIEWPORTS) {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    for (const screen of SCREENS) {
      const file = `${side}-${screen.name}-${vp.name}.png`;
      try {
        await reach(page, base, screen);
        await page.screenshot({ path: resolve(dir, file), fullPage: !screen.viewportOnly });
        shots.push({
          side,
          screen: screen.name,
          viewport: vp.name,
          file,
          note: null,
        });
      } catch (e) {
        const note = (e as Error).message.split('\n')[0].slice(0, 200);
        console.log(`${side} ${screen.name} @${vp.name}: not captured — ${note}`);
        shots.push({
          side,
          screen: screen.name,
          viewport: vp.name,
          file: null,
          note,
        });
      }
    }
  }
  await ctx.close();
  return shots;
}

function sh(cmd: string): string {
  try {
    return execSync(cmd, { encoding: 'utf8' }).trim();
  } catch {
    return 'unmeasured';
  }
}

function kitVersion(rootDir: string): string {
  try {
    const p = resolve(
      rootDir,
      '../../../frontend/node_modules/@hideyukimori/nene2-ui/package.json',
    );
    return (JSON.parse(readFileSync(p, 'utf8')) as { version: string }).version;
  } catch {
    return 'unmeasured';
  }
}

function esc(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function renderIndex(meta: Record<string, string>, shots: Shot[]): string {
  const cell = (s: Shot | undefined): string => {
    if (!s) return '<td class="miss">not run</td>';
    if (!s.file) return `<td class="miss">not captured<br><small>${esc(s.note ?? '')}</small></td>`;
    return `<td><a href="${s.file}" target="_blank"><img src="${s.file}" alt="${esc(s.file)}" loading="lazy"></a></td>`;
  };
  const rows = VIEWPORTS.flatMap((vp) =>
    SCREENS.map((sc) => {
      const prod = shots.find(
        (s) => s.side === 'prod' && s.screen === sc.name && s.viewport === vp.name,
      );
      const local = shots.find(
        (s) => s.side === 'local' && s.screen === sc.name && s.viewport === vp.name,
      );
      return `<tr><th scope="row">${esc(sc.name)}<br><small>${vp.name} ${vp.width}×${vp.height}</small></th>${cell(prod)}${cell(local)}</tr>`;
    }),
  ).join('\n');
  const metaRows = Object.entries(meta)
    .map(([k, v]) => `<tr><th scope="row">${esc(k)}</th><td>${esc(v)}</td></tr>`)
    .join('\n');
  return `<!doctype html>
<meta charset="utf-8">
<title>Owner review — ${esc(meta.date)}</title>
<style>
  body{font:14px/1.5 system-ui,sans-serif;margin:24px;color:#222;background:#fafafa}
  h1{font-size:20px;margin:0 0 4px}
  p.lede{margin:0 0 16px;color:#555;max-width:72ch}
  table{border-collapse:collapse;width:100%}
  th,td{border:1px solid #ddd;padding:8px;vertical-align:top;text-align:left}
  thead th{background:#eee;position:sticky;top:0}
  td img{max-width:100%;height:auto;display:block;border:1px solid #ccc;background:#fff}
  td.miss{color:#a00;background:#fff4f4}
  .meta{margin:0 0 20px;width:auto}
  .meta th{white-space:nowrap;background:#f3f3f3}
  small{color:#666}
</style>
<h1>Owner review — kit migration, ${esc(meta.date)}</h1>
<p class="lede">Left: production as served at run time. Right: the local build named below.
Look for "does it work, does it look put together" (2026-08-23). Differences are expected;
record GO / NG per screen on the wave's issue.</p>
<table class="meta"><tbody>${metaRows}</tbody></table>
<table>
<thead><tr><th>screen</th><th>production<br><small>${esc(meta.prod)}</small></th><th>local<br><small>${esc(meta.local)}</small></th></tr></thead>
<tbody>
${rows}
</tbody>
</table>
`;
}

test.describe.configure({ mode: 'serial' });

test('OWNER-REVIEW: production vs local, every screen, two viewports', async ({
  browser,
}, testInfo: TestInfo) => {
  test.setTimeout(10 * 60_000);
  const rootDir = testInfo.config.rootDir;
  const date = new Date().toISOString().slice(0, 10);
  const name = process.env.NENE_VAULT_OWNER_REVIEW_DIR ?? date;
  const dir = outDir(rootDir, name);
  mkdirSync(dir, { recursive: true });

  const prodBase = (testInfo.project.use.baseURL ?? 'https://vault.ayane.co.jp').replace(/\/$/, '');
  const localBase = LOCAL_URL.replace(/\/$/, '');

  const meta: Record<string, string> = {
    date,
    'measured at (UTC)': new Date().toISOString(),
    prod: prodBase,
    local: localBase,
    'local HEAD': sh('git rev-parse --short HEAD'),
    'local branch': sh('git rev-parse --abbrev-ref HEAD'),
    'local nene2-ui': kitVersion(rootDir),
  };

  // Local first: if the build is unstyled we learn it before minting a production org.
  const local = await shootSide(browser, 'local', localBase, dir, true);
  const prod = await shootSide(browser, 'prod', prodBase, dir, false);
  const shots = [...prod, ...local];

  writeFileSync(resolve(dir, 'meta.json'), JSON.stringify({ meta, shots }, null, 2));
  writeFileSync(resolve(dir, 'index.html'), renderIndex(meta, shots));

  const captured = shots.filter((s) => s.file).length;
  console.log(`owner-review: ${captured}/${shots.length} captured → ${dir}/index.html`);
  // The material is only useful if both sides produced something; partial is reported, not hidden.
  expect(
    local.some((s) => s.file),
    'no local screenshot at all',
  ).toBe(true);
  expect(
    prod.some((s) => s.file),
    'no production screenshot at all',
  ).toBe(true);
});
