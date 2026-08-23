/**
 * Fails if the built stylesheet does not contain nene2-ui's sentinel class.
 *
 * 🔴 Why this check exists. Tailwind v4's content detection does not walk `node_modules`,
 * and every class the kit ships lives in its `dist/`. Drop the `@source` line from
 * `src/shared/ui/theme/index.css` and the build stays green while generating **none** of
 * them: measured here on 2026-08-23, 47,075 bytes of CSS instead of 59,597, with every
 * gap, focus ring and disabled treatment missing. Type-check passed. All tests passed —
 * jsdom does not compute styles, so no test can see it. On screen the symptom is only
 * "the kit does not seem to do anything" (#387).
 *
 * The sentinel resolves to `padding: 0px` and is applied nowhere, so its sole property is
 * that it exists in the kit's `dist` and nowhere else. If Tailwind emitted it, it scanned
 * the kit; if not, it did not.
 */
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { SOURCE_PROBE_CLASS } from '@hideyukimori/nene2-ui';

const assets = join(import.meta.dirname, '..', 'dist', 'assets');

let files;
try {
  files = readdirSync(assets).filter((f) => f.endsWith('.css'));
} catch {
  console.error(`source-probe: ${assets} not found — run \`npm run build\` first.`);
  process.exit(1);
}

if (files.length === 0) {
  console.error('source-probe: no stylesheet in dist/assets — run `npm run build` first.');
  process.exit(1);
}

const missing = files.filter(
  (f) => !readFileSync(join(assets, f), 'utf8').includes(`.${SOURCE_PROBE_CLASS}`),
);

if (missing.length === files.length) {
  console.error(
    `source-probe: .${SOURCE_PROBE_CLASS} is in no stylesheet (${files.join(', ')}).\n` +
      "  @hideyukimori/nene2-ui is not in Tailwind's @source, so none of its classes were\n" +
      '  generated. Restore the @source line in src/shared/ui/theme/index.css.',
  );
  process.exit(1);
}

console.log(
  `source-probe: OK (.${SOURCE_PROBE_CLASS} present in ${files.length - missing.length}/${files.length})`,
);
