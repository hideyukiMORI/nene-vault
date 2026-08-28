/**
 * Fail if msw reaches the production bundle.
 *
 * `src/main.tsx` starts a mock service worker behind `import.meta.env.DEV &&
 * import.meta.env.VITE_MSW === '1'`. In a production build `DEV` is a compile-time `false`,
 * so the branch and its dynamic import are dropped and msw — a devDependency — never ships.
 *
 * 🔴 That is a claim about a bundler's behaviour, and a comment cannot enforce it. A future
 * refactor that lifts the import to the top of the module, or a config change that stops
 * constant-folding `DEV`, would ship an interception layer to real users and break nothing
 * that any existing check looks at. This reads the built output instead.
 *
 * Positive control: `node tools/assert-no-msw-in-build.mjs --self-test` proves the detector
 * can fail, by scanning a fixture that does contain the marker. A checker nobody has seen go
 * red is not evidence.
 */
import { readdir, readFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const DIST = new URL('../dist', import.meta.url).pathname;

// Strings that only appear if msw's browser runtime was bundled. Not the bare word "msw":
// that occurs in unrelated identifiers and would make this checker fire on noise.
const MARKERS = ['msw/browser', 'setupWorker', 'mockServiceWorker', 'msw/core'];

const scan = (text) => MARKERS.filter((m) => text.includes(m));

if (process.argv.includes('--self-test')) {
  const hits = scan('const x = setupWorker(); // from "msw/browser"');
  if (hits.length === 0) {
    console.error('self-test FAILED: the detector did not fire on text that contains markers.');
    process.exit(1);
  }
  console.log(`self-test OK — detector fires on ${hits.length} marker(s): ${hits.join(', ')}`);
  process.exit(0);
}

if (!existsSync(DIST)) {
  console.error('dist/ not found — run `npm run build` first.');
  process.exit(1);
}

const walk = async (dir) => {
  const out = [];
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...(await walk(path)));
    else if (/\.(js|mjs|css|html)$/.test(entry.name)) out.push(path);
  }
  return out;
};

const files = await walk(DIST);
const bad = [];
for (const file of files) {
  const hits = scan(await readFile(file, 'utf8'));
  if (hits.length > 0) bad.push({ file: file.slice(DIST.length + 1), hits });
}

if (bad.length > 0) {
  console.error('msw reached the production bundle:');
  for (const b of bad) console.error(`  ${b.file} — ${b.hits.join(', ')}`);
  console.error('\nThe dev-only guard in src/main.tsx is no longer doing its job.');
  process.exit(1);
}

console.log(`assert-no-msw-in-build: OK — ${files.length} built file(s), no msw markers.`);
