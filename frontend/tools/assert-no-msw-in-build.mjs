/**
 * Fail if msw reaches the production bundle — as bundled code, or as a file.
 *
 * Two ways it can get there, and they need different checks:
 *
 *   1. **Bundled.** `src/main.tsx` starts a mock worker behind `import.meta.env.DEV &&
 *      import.meta.env.VITE_MSW === '1'`. In a production build `DEV` is a compile-time
 *      `false`, so the branch and its dynamic import are dropped. That is a claim about a
 *      bundler's behaviour and a comment cannot enforce it, so this reads the built output.
 *
 *   2. **Copied.** `msw init` writes `public/mockServiceWorker.js`, and Vite copies `public/`
 *      into `dist/` verbatim. The `vault-strip-msw-worker` plugin in `vite.config.ts` removes
 *      it again; this checks that it actually did.
 *
 * 🔴 This file previously missed (2) completely, and the reason is worth keeping. Its markers
 * were invented — `msw/browser`, `setupWorker`, `mockServiceWorker`, `msw/core` — and then
 * "proved" against a string written by hand in the self-test. Measured 2026-08-28: the real
 * `mockServiceWorker.js` contains **none of those four**. So the checker reported
 * "OK — no msw markers" while the worker sat in `dist/`, and its positive control passed the
 * whole time, because the control tested the matcher against fiction rather than against the
 * artifact. The markers below are now quoted out of the shipped file, and the self-test scans
 * that file.
 *
 * Usage:  node tools/assert-no-msw-in-build.mjs [--self-test]
 */
import { readdir, readFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = new URL('..', import.meta.url).pathname;
const DIST = join(ROOT, 'dist');
const WORKER_SOURCE = join(ROOT, 'public/mockServiceWorker.js');

// Verbatim from `public/mockServiceWorker.js` (msw 2.x) and from msw's browser runtime.
// Not the bare word "msw": it occurs inside unrelated identifiers and would fire on noise.
const CONTENT_MARKERS = [
  'Mock Service Worker', // the worker file's own header comment
  'mswjs/msw', // the @see URL in that header
  'msw/browser', // the module specifier, if the dev branch were ever bundled
  'setupWorker', // its export
];

// A file can carry msw by its name alone — the worker's body never says "mockServiceWorker".
const NAME_MARKERS = ['mockServiceWorker'];

const SCANNABLE = /\.(js|mjs|cjs|css|html|json|map)$/;

const walk = async (dir) => {
  const out = [];
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const p = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...(await walk(p)));
    else out.push(p);
  }
  return out;
};

const inspect = async (file) => {
  const name = file.slice(DIST.length + 1);
  const hits = NAME_MARKERS.filter((m) => name.includes(m)).map((m) => `name:${m}`);
  if (SCANNABLE.test(file)) {
    const text = await readFile(file, 'utf8');
    hits.push(...CONTENT_MARKERS.filter((m) => text.includes(m)).map((m) => `content:${m}`));
  }
  return { name, hits };
};

if (process.argv.includes('--self-test')) {
  // 🔴 The control scans the real artifact. Proving the matcher fires on a hand-written
  // string proves nothing about whether the markers describe what actually ships.
  if (!existsSync(WORKER_SOURCE)) {
    console.error(`self-test FAILED: ${WORKER_SOURCE} not found — run \`npx msw init public/\`.`);
    process.exit(1);
  }
  const text = await readFile(WORKER_SOURCE, 'utf8');
  const contentHits = CONTENT_MARKERS.filter((m) => text.includes(m));
  const nameHits = NAME_MARKERS.filter((m) => 'mockServiceWorker.js'.includes(m));
  if (contentHits.length === 0) {
    console.error(
      'self-test FAILED: no content marker matches the real worker file. ' +
        'The markers describe something other than what ships.',
    );
    process.exit(1);
  }
  if (nameHits.length === 0) {
    console.error("self-test FAILED: no name marker matches 'mockServiceWorker.js'.");
    process.exit(1);
  }
  console.log(
    `self-test OK — the real worker is caught by ${contentHits.length} content marker(s) ` +
      `(${contentHits.join(', ')}) and ${nameHits.length} name marker(s) (${nameHits.join(', ')}).`,
  );
  process.exit(0);
}

if (!existsSync(DIST)) {
  console.error('dist/ not found — run `npm run build` first.');
  process.exit(1);
}

const files = await walk(DIST);
const bad = [];
for (const file of files) {
  const { name, hits } = await inspect(file);
  if (hits.length > 0) bad.push({ name, hits });
}

if (bad.length > 0) {
  console.error('msw reached the production bundle:');
  for (const b of bad) console.error(`  ${b.name} — ${b.hits.join(', ')}`);
  console.error('\nEither the dev-only guard in src/main.tsx or vite.config.ts’s');
  console.error('`vault-strip-msw-worker` plugin is no longer doing its job.');
  process.exit(1);
}

console.log(
  `assert-no-msw-in-build: OK — ${files.length} built file(s), no msw by name or content.`,
);
