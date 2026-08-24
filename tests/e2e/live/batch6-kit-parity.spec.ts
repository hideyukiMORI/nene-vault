import { expect, test, type Browser, type Page } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { seatAdmin } from './_helpers';

/**
 * Live-target QA — batch 6: kit-parity comparison (#402, harness home ruled 2026-08-24).
 *
 * Compares the PRODUCTION demo against a LOCAL build, screen by screen, using computed
 * styles. It exists because a migration onto a shared UI kit changes rendering in ways no
 * unit test sees: a slot the product never set, a class the kit stopped shipping, a step of
 * a scale that does not exist upstream. Six such differences were found this way on
 * 2026-08-23 and three of them were regressions this repo had introduced itself.
 *
 * 🔴 OBSERVATION, NOT PASS/FAIL — with one exception, below. The comparison assumes the
 * production demo is still the current design. That assumption holds for this ship today and
 * will not hold forever: the moment production is the older design, a hard assertion turns
 * this harness into pressure to revert. Same reason `batch5-visual` reports rather than
 * asserts. Another ship pointing this at its own site must check that assumption first.
 *
 * 🔴 The one hard failure is the unstyled build. If the local side renders with none of the
 * kit's classes, every comparison "differs" and the report reads like a redesign instead of a
 * broken build — that is the `@source` regression (#387), where the build stays green and
 * generates none of the kit's CSS. `npm run source-probe` catches it in CI; this catches it
 * here, because a run that silently compares an unstyled page to production wastes the run.
 *
 * NOT wired into CI, and it must not be: CI never touches production (裁定 2026-07-21, the
 * same one that put this whole directory outside the hermetic lane).
 *
 * Credentials: none. Both sides are seated through `/demo/standard`, which provisions a
 * disposable org and signs itself in. Nothing here reads a secret.
 *
 * ⚠️ The harness lived in a session scratchpad until 2026-08-24 and did not survive the
 * session that wrote it. Same shape as issues #113 (COLD's tools under `scratch/`): a working
 * instrument with no home is a working instrument for one day.
 *
 * Usage:
 *   # bring the local target up first (API 8600 / frontend 5186)
 *   docker compose up -d && npm run dev --prefix frontend
 *   npm run e2e:live --prefix frontend -- batch6
 *
 *   # or point at a preview build / another environment
 *   NENE_VAULT_PARITY_LOCAL_URL=http://localhost:4173 npm run e2e:live --prefix frontend -- batch6
 */

const LOCAL_URL = process.env.NENE_VAULT_PARITY_LOCAL_URL ?? 'http://localhost:5186';
const REPORT_DIR = 'docs/qa';

/** One thing to look at, and the properties that decide whether it matches. */
interface Probe {
  /** Reported name. Keep it the words a person would use, not the selector. */
  name: string;
  selector: string;
  props: string[];
}

/** A screen, reached from the rail, and what to measure on it. */
interface Screen {
  name: string;
  /** Rail button label, or null for the landing screen. */
  rail: string | null;
  urlPattern: RegExp | null;
  probes: Probe[];
}

const BOX = ['width', 'height', 'padding', 'border-width', 'border-radius'];
const TYPE = ['font-size', 'font-weight', 'line-height', 'color'];
const FILL = ['background-color', 'border-color', 'display', 'gap', 'align-items'];

/**
 * 🔴 The inventory is explicit, not discovered. A crawler would compare whatever it found and
 * report hundreds of rows, most of them this product's own markup, which matched on every
 * element both times it was measured. The differences live in the kit's components, so those
 * are what this names. A row that stops resolving is a signal too — it means the markup moved.
 */
const SCREENS: Screen[] = [
  {
    name: 'documents',
    rail: 'Received Documents',
    urlPattern: /\/documents$/,
    probes: [
      { name: 'primary button (Upload)', selector: 'button:has-text("Upload Document")', props: [...TYPE, ...FILL, ...BOX] },
      { name: 'secondary button (Clear)', selector: 'button:has-text("Clear")', props: [...TYPE, ...FILL, ...BOX] },
      { name: 'submit button (Search)', selector: 'button[type="submit"]', props: [...TYPE, ...FILL, ...BOX] },
      { name: 'search text input', selector: 'input[type="text"]', props: [...TYPE, ...FILL, ...BOX] },
      { name: 'search select', selector: 'select', props: [...TYPE, ...FILL, ...BOX] },
      { name: 'choice label (include voided)', selector: 'label:has(input[type="checkbox"])', props: [...TYPE, ...FILL] },
      { name: 'choice box', selector: 'input[type="checkbox"]', props: [...BOX, 'accent-color'] },
      { name: 'field label', selector: 'form label:not(:has(input))', props: TYPE },
      { name: 'rail nav (own markup — control row)', selector: 'nav.rail-nav', props: [...FILL, 'width'] },
    ],
  },
  {
    name: 'audit',
    rail: 'Audit Trail',
    urlPattern: /\/audit$/,
    probes: [
      { name: 'primary button', selector: 'button[type="submit"]', props: [...TYPE, ...FILL] },
      { name: 'table header cell', selector: 'table thead th', props: [...TYPE, 'padding', 'border-color'] },
      { name: 'table body cell', selector: 'table tbody td', props: [...TYPE, 'padding', 'border-color'] },
    ],
  },
  {
    name: 'export',
    rail: 'Export',
    urlPattern: /\/export$/,
    probes: [
      { name: 'radio label', selector: 'label:has(input[type="radio"])', props: [...TYPE, ...FILL] },
      { name: 'radio box', selector: 'input[type="radio"]', props: [...BOX, 'accent-color'] },
      { name: 'checkbox label', selector: 'label:has(input[type="checkbox"])', props: [...TYPE, ...FILL] },
      { name: 'export button', selector: 'button:has-text("Export")', props: [...TYPE, ...FILL, ...BOX] },
    ],
  },
  {
    name: 'settings',
    rail: 'Settings',
    urlPattern: /\/settings$/,
    probes: [
      { name: 'number input (retention)', selector: 'input[type="number"]', props: [...TYPE, ...FILL, ...BOX] },
      { name: 'field hint', selector: 'form span', props: TYPE },
      { name: 'save button', selector: 'button[type="submit"]', props: [...TYPE, ...FILL, ...BOX] },
    ],
  },
];

type Measurement = Record<string, string>;
/** `null` means the selector matched nothing — reported, never silently skipped. */
type ScreenReading = Record<string, Measurement | null>;

async function read(page: Page, probes: Probe[]): Promise<ScreenReading> {
  const out: ScreenReading = {};
  for (const probe of probes) {
    const el = page.locator(probe.selector).first();
    if ((await el.count()) === 0) {
      out[probe.name] = null;
      continue;
    }
    out[probe.name] = await el.evaluate((node, props: string[]) => {
      const cs = getComputedStyle(node as Element);
      const m: Record<string, string> = {};
      for (const p of props) m[p] = cs.getPropertyValue(p).trim();
      return m;
    }, probe.props);
  }
  return out;
}

/**
 * Count the kit's own classes on the page.
 *
 * 🔴 This is the unstyled-build detector. The kit's classes all carry the `x-slot` infix, so
 * their absence from a rendered page means the CSS for them was never generated — which is
 * exactly what a missing `@source` produces, with a green build (#387).
 */
async function kitClassCount(page: Page): Promise<number> {
  return page.evaluate(() => {
    let n = 0;
    for (const el of Array.from(document.querySelectorAll<HTMLElement>('*'))) {
      const cls = typeof el.className === 'string' ? el.className : '';
      if (cls.includes('x-slot')) n += 1;
    }
    return n;
  });
}

async function walk(browser: Browser, baseURL: string): Promise<Record<string, ScreenReading>> {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'en-US', baseURL });
  const page = await ctx.newPage();
  await seatAdmin(page);
  const readings: Record<string, ScreenReading> = {};
  readings.__kitClasses = { count: { value: String(await kitClassCount(page)) } };
  for (const screen of SCREENS) {
    if (screen.rail !== null) {
      await page.locator('nav.rail-nav').getByRole('button', { name: screen.rail, exact: true }).click();
      if (screen.urlPattern !== null) await page.waitForURL(screen.urlPattern, { timeout: 15_000 });
      await page.waitForLoadState('networkidle');
    }
    readings[screen.name] = await read(page, screen.probes);
  }
  await ctx.close();
  return readings;
}

interface Row {
  screen: string;
  element: string;
  property: string;
  production: string;
  local: string;
}

test.describe.configure({ mode: 'serial' });

test('VLT-K1-01: kit parity — production vs local, computed styles', async ({ browser }) => {
  // Reachability first, and skip rather than fail: the local target is a developer's choice,
  // and a run that cannot find it has measured nothing — which is not the same as a difference.
  const probe = await browser.newContext();
  const probePage = await probe.newPage();
  let reachable = true;
  try {
    await probePage.goto(LOCAL_URL, { timeout: 10_000 });
  } catch {
    reachable = false;
  }
  await probe.close();
  test.skip(
    !reachable,
    `local target ${LOCAL_URL} is not reachable — start it (docker compose up -d && npm run dev --prefix frontend) or set NENE_VAULT_PARITY_LOCAL_URL`,
  );

  const production = await walk(browser, test.info().project.use.baseURL ?? 'https://vault.ayane.co.jp');
  const local = await walk(browser, LOCAL_URL);

  // 🔴 The one hard failure. Compare against production's count rather than a literal: the
  // number moves as screens change, and a threshold written today is a threshold wrong later.
  const prodKit = Number(production.__kitClasses?.count?.value ?? '0');
  const localKit = Number(local.__kitClasses?.count?.value ?? '0');
  expect(
    localKit,
    `local rendered ${localKit} kit-classed elements against production's ${prodKit}. ` +
      'Near-zero means the kit CSS was never generated — check the `@source` line in ' +
      'src/shared/ui/theme/index.css and run `npm run source-probe` (#387).',
  ).toBeGreaterThan(0);

  const rows: Row[] = [];
  const unresolved: string[] = [];
  let matched = 0;

  for (const screen of SCREENS) {
    const p = production[screen.name] ?? {};
    const l = local[screen.name] ?? {};
    for (const probeDef of screen.probes) {
      const pm = p[probeDef.name];
      const lm = l[probeDef.name];
      if (pm === null || pm === undefined || lm === null || lm === undefined) {
        unresolved.push(
          `${screen.name} / ${probeDef.name} — ${pm == null ? 'production' : ''}${pm == null && lm == null ? ' and ' : ''}${lm == null ? 'local' : ''} matched nothing`,
        );
        continue;
      }
      for (const prop of probeDef.props) {
        if (pm[prop] === lm[prop]) {
          matched += 1;
          continue;
        }
        rows.push({ screen: screen.name, element: probeDef.name, property: prop, production: pm[prop], local: lm[prop] });
      }
    }
  }

  const stamp = new Date().toISOString();
  const report = { measuredAt: stamp, productionKitClasses: prodKit, localKitClasses: localKit, matched, differing: rows.length, unresolved, rows };
  mkdirSync(REPORT_DIR, { recursive: true });
  writeFileSync(`${REPORT_DIR}/kit-parity-latest.json`, `${JSON.stringify(report, null, 2)}\n`);

  console.log(`\nkit parity — matched ${matched} / differing ${rows.length} (measured ${stamp})`);
  console.log(`kit-classed elements: production ${prodKit} · local ${localKit}\n`);
  for (const r of rows) {
    console.log(`  ${r.screen} · ${r.element} · ${r.property}\n      production ${r.production}\n      local      ${r.local}`);
  }
  if (unresolved.length > 0) {
    // 🔴 Printed as its own list. A probe that resolves on neither side reads as "no difference"
    // in a diff count, which is the failure mode this whole file exists to avoid: something
    // that measured nothing looking exactly like something that measured agreement.
    console.log('\nunresolved probes (measured nothing — NOT agreement):');
    for (const u of unresolved) console.log(`  ${u}`);
  }
  console.log(`\nreport written to ${REPORT_DIR}/kit-parity-latest.json`);
});
