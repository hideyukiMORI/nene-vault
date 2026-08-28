/**
 * Screenshot the Button harness stories from a built Storybook.
 *
 * 🔴 What these images are: the kit's Button rendered against this product's theme, in a real
 * browser. That is enough to see what a slot value paints, and it is the only way to see it —
 * measured 2026-08-28, none of this product's eight checks can detect a dead slot name.
 *
 * 🔴 What these images are NOT: evidence about any screen. A story renders the primitive in
 * isolation; the cascade, specificity, containing block and layer order of a real page are
 * not reproduced. Filenames carry `harness-` and the manifest carries `isRealScreen: false`
 * so that an image which outlives this conversation cannot be mistaken for a page shot.
 * The owner's visual bundle needs real screens — see `docs/qa/owner-review/README.md`.
 *
 * Usage:  npm run build-storybook && node tools/shoot-button-harness.mjs [outDir]
 */
import { chromium } from 'playwright';
import { createServer } from 'node:http';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { extname, join, normalize } from 'node:path';

const ROOT = new URL('..', import.meta.url).pathname;
const STATIC_DIR = join(ROOT, 'storybook-static');
const OUT_DIR = process.argv[2] ?? join(ROOT, 'storybook-shots');

const SHOTS = [
  { id: 'primitives-button--two-axis-matrix', name: 'two-axis-matrix', width: 1000, height: 520 },
  { id: 'primitives-button--vault-call-sites', name: 'vault-call-sites', width: 900, height: 560 },
  { id: 'primitives-button--narrow-viewport', name: 'narrow-375', width: 375, height: 420 },
];

const MIME = {
  '.html': 'text/html',
  '.js': 'text/javascript',
  '.mjs': 'text/javascript',
  '.css': 'text/css',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
  '.woff2': 'font/woff2',
  '.png': 'image/png',
};

if (!existsSync(STATIC_DIR)) {
  console.error('storybook-static/ not found — run `npm run build-storybook` first.');
  process.exit(1);
}

// A plain static server. Storybook's own `serve` is not a dependency here and the built
// output is just files; refusing anything that escapes the directory keeps it honest.
const server = createServer(async (req, res) => {
  const rel = normalize(decodeURIComponent(req.url.split('?')[0])).replace(/^(\.\.[/\\])+/, '');
  const path = join(STATIC_DIR, rel === '/' ? 'index.html' : rel);
  if (!path.startsWith(STATIC_DIR)) {
    res.writeHead(403).end();
    return;
  }
  try {
    const body = await readFile(path);
    res.writeHead(200, { 'content-type': MIME[extname(path)] ?? 'application/octet-stream' });
    res.end(body);
  } catch {
    res.writeHead(404).end();
  }
});

await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;

const browser = await chromium.launch();
await mkdir(OUT_DIR, { recursive: true });

const shot = async ({ id, name, width, height }) => {
  const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 2 });
  await page.goto(`${base}/iframe.html?id=${id}&viewMode=story`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#storybook-root button');
  await page.evaluate(() => document.fonts.ready);
  const file = join(OUT_DIR, `harness-${name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  await page.close();
  return { id, file: `harness-${name}.png`, viewport: `${width}x${height}`, scale: 2 };
};

const shots = [];
for (const s of SHOTS) shots.push(await shot(s));

/**
 * Read back what the browser actually computed, so the claims about this upgrade are numbers
 * rather than impressions. Eyes are good at "that looks the same" and bad at "the border is
 * now transparent"; both danger buttons here are confirm buttons for a destructive action.
 */
const probePage = await browser.newPage({ viewport: { width: 900, height: 560 } });
await probePage.goto(`${base}/iframe.html?id=primitives-button--vault-call-sites&viewMode=story`, {
  waitUntil: 'networkidle',
});
await probePage.waitForSelector('#storybook-root button');
const probe = await probePage.evaluate(() =>
  [...document.querySelectorAll('#storybook-root button')].map((el) => {
    const s = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return {
      label: el.textContent.trim(),
      color: s.color,
      backgroundColor: s.backgroundColor,
      borderTopColor: s.borderTopColor,
      borderTopWidth: s.borderTopWidth,
      boxShadow: s.boxShadow,
      height: Math.round(r.height * 100) / 100,
    };
  }),
);
await probePage.close();

const git = (args) => execFileSync('git', args, { cwd: ROOT, encoding: 'utf8' }).trim();
const uiVersion = JSON.parse(
  await readFile(join(ROOT, 'node_modules/@hideyukimori/nene2-ui/package.json'), 'utf8'),
).version;

// 🔴 The tree state, not just HEAD. A manifest that records only the commit describes a build
// that may never have existed: the working tree can carry uncommitted changes and the image
// would still be filed under a clean-sounding SHA (#443, the same defect in batch8's meta).
const dirty = git(['status', '--porcelain']);

await writeFile(
  join(OUT_DIR, 'meta.json'),
  `${JSON.stringify(
    {
      isRealScreen: false,
      kind: 'storybook-harness',
      whatThisIsNot:
        'Not a screen shot. Story-isolated primitives on this product theme; page cascade not reproduced. Do not file as production verification.',
      capturedAt: new Date().toISOString(),
      head: git(['rev-parse', 'HEAD']),
      branch: git(['rev-parse', '--abbrev-ref', 'HEAD']),
      workingTreeClean: dirty === '',
      uncommittedFiles: dirty === '' ? [] : dirty.split('\n').map((l) => l.slice(3)),
      nene2UiVersion: uiVersion,
      computedStyles: probe,
      shots,
    },
    null,
    2,
  )}\n`,
);

await browser.close();
server.close();
console.log(`wrote ${shots.length} shots + meta.json to ${OUT_DIR}`);
