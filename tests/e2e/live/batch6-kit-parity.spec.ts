import { expect, test, type Browser, type Page } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
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
 * 🔴 OBSERVATION, NOT PASS/FAIL — with two exceptions, below. Same reason `batch5-visual`
 * reports rather than asserts.
 *
 * 🔴 KNOW WHAT THE REFERENCE IS BEFORE READING A ROW. Production is not "the current design";
 * it is whatever was last deployed. Measured 2026-08-24, nene-vault's demo still serves
 * `class="btn btn-primary"` — the component-class era, which ended at `a941a87` on
 * **2026-07-22**, a month before the kit migration. So a row here can mean any of three
 * things and the table cannot tell them apart:
 *
 *   1. a regression the migration introduced   → fix it (three were, and were)
 *   2. a change made deliberately since        → expected; the drain rounded 7px gaps to 8px
 *   3. **a change nobody noticed at the time**  → the interesting one
 *
 * The third is why this is worth running even when everything is "fine": the `sm` button's
 * line-height went 18.6px → 16px at that same drain, because `.btn` had `font: inherit` and
 * the utility that replaced it carries Tailwind's companion line-height. That has been in
 * `main` since July and no test noticed, because nothing compared against a rendered page.
 *
 * 🔑 Reading a row therefore means finding which build introduced the difference, not
 * assuming the newer side is wrong. `git log -S` on the value is usually enough.
 *
 * ⚠️ A ship pointing this at its own site must establish the same thing first: not "is
 * production the current design" but "which build is production, and what changed since".
 * Hard assertions on top of an unexamined reference turn this into pressure to revert.
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
/**
 * 🔴 Resolved from Playwright's `rootDir`, not from the working directory and not from
 * `import.meta`.
 *
 * Playwright runs with its config's directory as CWD — `frontend/` here — so the bare relative
 * path this started with wrote the report to `frontend/docs/qa/`, a directory that should not
 * exist and that nobody would look in. `batch5-visual.spec.ts` has the same bare path and its
 * screenshots have been landing there too; raised separately rather than changed from here.
 *
 * ⚠️ The obvious fix, `import.meta.url`, does not work: the nearest package.json is not
 * `type: module`, so this file is loaded as CJS and `import.meta` is a syntax error — which
 * Playwright reports as **"No tests found"**, not as a broken file. Another spelling of today's
 * shape: the failure presented as an empty result rather than an error.
 */
function reportDir(rootDir: string): string {
  return resolve(rootDir, '../../../docs/qa');
}
/** Per-probe ceiling. An element that is not there within this is reported as absent. */
const PROBE_TIMEOUT_MS = 5_000;
/** Per-screen settle ceiling. Exceeding it is logged, never fatal. */
const SETTLE_TIMEOUT_MS = 10_000;

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
const FILL = ['background-color', 'border-color', 'box-shadow', 'display', 'gap', 'align-items'];

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
    rail: 'Audit Log',
    urlPattern: /\/audit$/,
    probes: [
      // 🔴 The `sm` size lives here, not on /documents — the pagination is the only place this
      // product renders one. The first inventory named no `sm` probe at all, so a size whose
      // difference #398 had already reported was outside the reach of a file whose whole job
      // is to find differences in buttons. The second attempt put them on /documents behind a
      // `nav` ancestor that this product's own Pagination does not have, and they resolved to
      // nothing on both sides — which the report shows as "measured nothing", not as agreement.
      { name: 'sm button (Previous)', selector: 'button:has-text("Previous")', props: [...TYPE, ...FILL, ...BOX] },
      { name: 'sm button (Next)', selector: 'button:has-text("Next")', props: [...TYPE, ...FILL, ...BOX] },
      // ⚠️ Not `button[type="submit"]`. There is no `<form>` on this screen, so production's
      // buttons are `submit` only because a bare `<button>` defaults to it and the kit's
      // default is `button`. Inert either way with no form to submit, but it made the probe
      // resolve on one side and not the other.
      { name: 'secondary button (Clear)', selector: 'button:has-text("Clear")', props: [...TYPE, ...FILL] },
      { name: 'primary button (Search)', selector: 'button:has-text("Search")', props: [...TYPE, ...FILL] },
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
    rail: 'Vault Settings',
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

/**
 * 🔴 Every wait in here is bounded, and a probe that does not resolve becomes `null` rather
 * than stalling the run.
 *
 * The second real run of this file spent its entire ten-minute budget inside this function
 * and died with "waiting for locator(...)". The cause was that `count()` and `evaluate()` are
 * two different moments: `count()` does not wait, so it answered 1, and by the time
 * `evaluate()` ran the element had been replaced by a re-render — and `evaluate()` *does*
 * wait, with no timeout of its own, so it waited until the test died. Checking existence
 * before acting reads like the careful version and is exactly what introduced the hang.
 *
 * So: one bounded call, and the absence of an element is a result, not an exception.
 */
async function read(page: Page, probes: Probe[]): Promise<ScreenReading> {
  const out: ScreenReading = {};
  for (const probe of probes) {
    const started = Date.now();
    try {
      out[probe.name] = await page.locator(probe.selector).first().evaluate(
        (node, props: string[]) => {
          const cs = getComputedStyle(node as Element);
          const m: Record<string, string> = {};
          for (const p of props) m[p] = cs.getPropertyValue(p).trim();
          return m;
        },
        probe.props,
        { timeout: PROBE_TIMEOUT_MS },
      );
    } catch {
      out[probe.name] = null;
    }
    const ms = Date.now() - started;
    // Printed per probe because the failing runs gave no way to see where the time went —
    // only that all of it was gone. A slow probe and a hung one look identical in a total.
    if (ms > 1_000) console.log(`      slow probe ${ms}ms · ${probe.name}`);
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
  const seatStarted = Date.now();
  try {
    await seatAdmin(page);
  } catch (cause) {
    // 🔴 Say why, not just that it did not happen. `seatAdmin` waits 30s for `nav.rail-nav`
    // and then reports the selector — which is true and useless: the first local run failed
    // this way and the actual answer, "Demo mode is not enabled on this instance", was sitting
    // in the API's problem-details the whole time. Ask the endpoint and pass on what it says.
    let detail = '(the demo endpoint gave no readable answer)';
    try {
      const res = await page.request.get(`${baseURL}/demo/standard`);
      const body: unknown = await res.json();
      if (typeof body === 'object' && body !== null && 'detail' in body) {
        detail = `${res.status()} — ${String((body as { detail: unknown }).detail)}`;
      }
    } catch {
      /* keep the fallback */
    }
    throw new Error(
      `could not seat a demo session at ${baseURL}: ${detail}\n` +
        'The comparison seats BOTH sides through /demo/standard so it needs no credentials. ' +
        'A local target therefore has to have demo mode on: DEMO_MODE=1 docker compose up -d, ' +
        'then seed it (docs/demo.md). Production has it on already.',
      { cause },
    );
  }
  console.log(`  seated at ${baseURL} in ${Date.now() - seatStarted}ms`);
  const readings: Record<string, ScreenReading> = {};
  let kitClasses = 0;
  for (const screen of SCREENS) {
    if (screen.rail !== null) {
      const rail = page.locator('nav.rail-nav');
      const button = rail.getByRole('button', { name: screen.rail, exact: true });
      // 🔴 Ask whether the button exists before clicking it. Playwright's click waits for the
      // selector and then reports a timeout, which names the locator but not the reason — the
      // first real run of this file spent 60s on `Audit Trail` and never said that the rail
      // offers `Audit Log`. A harness whose inventory is written by hand will have wrong
      // labels in it; what it must not do is take a minute to say so without saying which.
      if ((await button.count()) === 0) {
        const available = await rail.getByRole('button').allInnerTexts();
        throw new Error(
          `rail has no button "${screen.rail}" on ${baseURL}. Available: ${available.map((t) => JSON.stringify(t.trim())).join(', ')}`,
        );
      }
      await button.click();
      if (screen.urlPattern !== null) await page.waitForURL(screen.urlPattern, { timeout: 15_000 });
      // 🔴 `networkidle` is not guaranteed to arrive. A screen that polls never goes idle, and
      // the wait then runs to the test timeout — the same unbounded shape as the probe above.
      // Bounded, and a screen that never settles is still measured.
      await page.waitForLoadState('networkidle', { timeout: SETTLE_TIMEOUT_MS }).catch(() => {
        console.log(`      ${screen.name}: never reached networkidle, measuring anyway`);
      });
    }
    const t0 = Date.now();
    readings[screen.name] = await read(page, screen.probes);
    // 🔴 Counted here, not right after seating. The probes above auto-wait, so by the time
    // they have all answered the screen has actually rendered; a count taken immediately
    // after `seatAdmin` sees the loading skeleton and reports 0 kit classes on a page that
    // is full of them. Measured 2026-08-24: 132 elements at that moment against a rendered
    // screen's several hundred — and 0 became "the build is unstyled", on both sides.
    kitClasses += await kitClassCount(page);
    console.log(`    ${screen.name} read in ${Date.now() - t0}ms`);
  }
  readings.__kitClasses = { count: { value: String(kitClasses) } };
  await ctx.close();
  return readings;
}

/**
 * Which build of the kit the local target is actually serving.
 *
 * 🔴 The working tree is not the target. The local frontend runs in a container whose
 * `node_modules` is a named volume populated at image build, so a host-side `npm install`
 * does not reach it — measured 2026-08-24: the repo declared `^0.14.0`, the host had 0.14.0
 * installed, and the dev server was serving **0.11.0**. The comparison ran, produced 37
 * differences, and every one of them was a true statement about a build nobody meant to
 * measure. A stale target does not look like an error; it looks like a design report.
 *
 * Vite serves files under its `fs.allow` root, so the package manifest of the build in use
 * can be read over HTTP from the target itself — the one place that cannot be stale.
 */
async function servedKitVersion(page: Page, baseURL: string): Promise<string | null> {
  const candidates = [
    '/node_modules/@hideyukimori/nene2-ui/package.json',
    '/@fs/app/frontend/node_modules/@hideyukimori/nene2-ui/package.json',
  ];
  for (const path of candidates) {
    try {
      const res = await page.request.get(`${baseURL}${path}`);
      if (!res.ok()) continue;
      const body: unknown = await res.json();
      if (typeof body === 'object' && body !== null && 'version' in body) {
        const v = (body as { version: unknown }).version;
        if (typeof v === 'string') return v;
      }
    } catch {
      // Not JSON (an SPA index.html fallback answers 200 for anything) — try the next.
    }
  }
  return null;
}

/**
 * The floor this repo declares.
 *
 * ⚠️ Both paths are tried because the working directory depends on how the run was started:
 * Playwright resolves it from the config, which lives in `frontend/`. The first version of
 * this read only the repo-root path, returned null from the wrong directory, and the guard
 * below **skipped itself and printed "(unreadable)"** — a check that does not run looks
 * exactly like a check that passed. Returning null is a real answer here, so it is logged.
 */
function declaredKitFloor(): string | null {
  for (const path of ['package.json', 'frontend/package.json']) {
    try {
      const pkg: unknown = JSON.parse(readFileSync(path, 'utf8'));
      const deps = (pkg as { dependencies?: Record<string, string> }).dependencies ?? {};
      const range = deps['@hideyukimori/nene2-ui'];
      if (range !== undefined) return range.replace(/^[\^~]/, '');
    } catch {
      // wrong directory, or not this repo's manifest — try the next
    }
  }
  return null;
}

/**
 * Whether the bundle the browser actually imports was built from the kit that is installed.
 *
 * 🔴 The manifest is not the bundle, and this is the layer the version check above cannot
 * see. Vite pre-bundles dependencies into `node_modules/.vite/deps/`, and that cache is not
 * invalidated by files appearing underneath it. Measured 2026-08-24: after the container's
 * `node_modules` was refreshed to 0.14.0, `package.json` said 0.14.0, the raw `dist/` said
 * 0.14.0 — and the bundle the page imported was still the one built the previous afternoon
 * from 0.11.0. Every button rendered as `display: block`, which is 0.11.0's Button exactly.
 *
 * 🔑 So the guard compares the served `dist/` against the served bundle instead of comparing
 * either against a number. No version literal to keep up to date: whatever the installed kit
 * puts in its Button must be in what the browser runs, or the cache is stale.
 */
async function bundleMatchesInstalledKit(
  page: Page,
  baseURL: string,
): Promise<{ ok: boolean; missing: string[] } | null> {
  const text = async (path: string): Promise<string | null> => {
    try {
      const res = await page.request.get(`${baseURL}${path}`);
      return res.ok() ? await res.text() : null;
    } catch {
      return null;
    }
  };
  const dist = await text('/node_modules/@hideyukimori/nene2-ui/dist/primitives/Button.js');
  const bundle = await text('/node_modules/.vite/deps/@hideyukimori_nene2-ui.js');
  if (dist === null || bundle === null) return null;
  const tokens = [...new Set(dist.match(/[a-z-]*x-slot-[a-z-]+/g) ?? [])];
  if (tokens.length === 0) return null;
  const missing = tokens.filter((t) => !bundle.includes(t));
  return { ok: missing.length === 0, missing };
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
  // 🔴 The config's 60s is sized for a single-screen live check. This test seats two sites and
  // walks four screens on each, over the real network — it does not fit, and the first run
  // died mid-walk on the config default. Raised here rather than in the config so the rest of
  // the live lane keeps its tighter budget.
  test.setTimeout(10 * 60_000);

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

  // 🔴 Second hard failure, for the same reason as the first: a comparison against the wrong
  // build is worse than no comparison, because its output is indistinguishable from findings.
  const versionCtx = await browser.newContext();
  const versionPage = await versionCtx.newPage();
  const served = await servedKitVersion(versionPage, LOCAL_URL);
  await versionCtx.close();
  const declared = declaredKitFloor();
  console.log(`kit — declared ${declared ?? '(unreadable)'} · local target serves ${served ?? '(unreadable)'}`);
  // 🔴 Say so out loud when the guard cannot run. Silence here is what let a whole comparison
  // be published about the wrong build.
  if (served === null || declared === null) {
    console.log(
      '      ⚠️ version guard did NOT run — this comparison is not known to be about the ' +
        'build this repo declares.',
    );
  }
  if (served !== null && declared !== null) {
    expect(
      served,
      `the local target at ${LOCAL_URL} is serving nene2-ui ${served} while this repo declares ` +
        `${declared}. Every difference below would be about the wrong build. The dev container ` +
        'keeps node_modules in a named volume, so a host-side `npm install` does not reach it. ' +
        'Refresh it with `docker compose exec frontend npm install --no-package-lock` — the ' +
        'repo is mounted read-only there, so plain `npm install` still installs but errors ' +
        'trying to write the lockfile — then `docker compose restart frontend`.',
    ).toBe(declared);
  }

  const freshCtx = await browser.newContext();
  const freshPage = await freshCtx.newPage();
  const fresh = await bundleMatchesInstalledKit(freshPage, LOCAL_URL);
  await freshCtx.close();
  if (fresh === null) {
    console.log('      ⚠️ bundle-freshness guard did NOT run (could not read dist or bundle).');
  } else {
    expect(
      fresh.missing,
      `the bundle the local target serves was built from an older kit than the one installed: ` +
        `${fresh.missing.join(', ')} are in dist/ but not in the pre-bundled dependency. ` +
        'Clear it and restart: `docker compose exec frontend rm -rf node_modules/.vite && ' +
        'docker compose restart frontend`.',
    ).toEqual([]);
  }

  const production = await walk(browser, test.info().project.use.baseURL ?? 'https://vault.ayane.co.jp');
  const local = await walk(browser, LOCAL_URL);

  // 🔴 The one hard failure, and it applies to the LOCAL side only.
  //
  // Production is the design this compares *against* — for nene-vault that is the build from
  // before the kit migration, whose buttons are `btn btn-primary`. It has no kit classes and
  // is not supposed to; measured 2026-08-24, production reports 0 on every screen. Comparing
  // local against production's count, as the first version of this did, therefore asserts
  // `0 > 0` and fails a healthy run. The reference having none of the kit is the premise of
  // the comparison, not a fault in it.
  const prodKit = Number(production.__kitClasses?.count?.value ?? '0');
  const localKit = Number(local.__kitClasses?.count?.value ?? '0');
  expect(
    localKit,
    `local rendered ${localKit} kit-classed elements across ${SCREENS.length} screens. ` +
      'Zero means the kit CSS was never generated — check the `@source` line in ' +
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
  const outDir = reportDir(test.info().config.rootDir);
  mkdirSync(outDir, { recursive: true });
  writeFileSync(`${outDir}/kit-parity-latest.json`, `${JSON.stringify(report, null, 2)}\n`);

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
  console.log(`\nreport written to ${outDir}/kit-parity-latest.json`);
});
