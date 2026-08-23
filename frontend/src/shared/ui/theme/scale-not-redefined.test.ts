import { readFileSync, readdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * A product redefines slots, never the scale they choose from.
 *
 * 🔴 The distinction is the whole mechanism. Redefining a slot changes one ship; redefining
 * a step changes what the name means for every ship that reads the kit's scale — and it does
 * it silently, because the name still resolves. A product whose design has no rounded
 * corners points its slots at `--radius-x-none`; it does not set `--radius-x-pill: 0`.
 *
 * 🔴 `npm run slot-values` does not catch this. That rule reads `--*-x-slot-*` declarations
 * and checks their values; a redefined *step* is not a slot declaration at all, so it never
 * reaches the check. Two different failures, two different guards (#395).
 *
 * 🔴 The step names are read from the kit's own theme rather than matched by pattern. The
 * first version of this test used `--(spacing|radius|text|…)-x-` and failed on a clean tree:
 * `x-` is also this product's extension namespace (`--color-x-brass`, `--color-x-ink-deep`),
 * so the pattern claimed every one of those was a redefined step. Reading the kit's file
 * means the list cannot drift from what the kit actually ships, either.
 */
const require = createRequire(import.meta.url);
const KIT_THEME = join(
  dirname(require.resolve('@hideyukimori/nene2-ui/package.json')),
  'themes',
  'default.css',
);

/** Declarations only — a token named inside a comment is prose. */
const declarations = (css: string): string[] =>
  [...css.replace(/\/\*[\s\S]*?\*\//g, '').matchAll(/(--[a-z0-9-]+)\s*:/g)].map((m) => m[1] ?? '');

/** Every `--…-x-…` the kit declares that is not a slot: the scale itself. */
const KIT_STEPS = new Set(
  declarations(readFileSync(KIT_THEME, 'utf8')).filter(
    (name) => /-x-/.test(name) && !name.includes('-x-slot-'),
  ),
);

const THEME_DIR = join(import.meta.dirname, 'themes');
const files = readdirSync(THEME_DIR).filter((f) => f.endsWith('.css'));

describe('kit scale', () => {
  it('reads a non-empty set of steps from the kit, so an empty result is not a pass', () => {
    expect(KIT_STEPS.size).toBeGreaterThan(10);
  });

  it.each(files)('%s redefines no step of the kit scale', (file) => {
    const ours = declarations(readFileSync(join(THEME_DIR, file), 'utf8'));

    expect(ours.filter((name) => KIT_STEPS.has(name))).toEqual([]);
  });
});
