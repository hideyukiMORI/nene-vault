/**
 * Fails if a slot in this product's theme holds a literal instead of a scale reference.
 *
 * 🔴 The rule is the kit's, applied to our own file: a slot chooses a step from the scale,
 * it may not invent one. `--spacing-x-slot-field-gap: 0.5625rem` compiles, looks reasonable,
 * and puts back exactly the drift the scale exists to stop — measured in this product before
 * the migration: 128 spacing utilities using 19 distinct values, five of them used once.
 *
 * 🔴 The kit can only police its own theme file; its README asks each product to run the
 * same check. This is that check. vault is the first ship to override a slot, so the shape
 * here is the one the rest of the fleet copies.
 *
 * Scope: the four namespaces that have a scale to reference — `--spacing-*`, `--radius-*`,
 * `--text-*`, `--color-*`. `--brightness-*` and `--opacity-*` are exempt on purpose: a hover
 * darkening of 95% is not a step in a series, and inventing a scale so the rule could cover
 * it would be a scale with one real user (kit README).
 *
 * ⚠️ Until 0.6.0 this check was narrower still — spacing and radius only — because the kit's
 * own theme held literals in `--text-*`. Implementing the rule as the README stated it would
 * have failed the kit itself; that mismatch was reported and 0.6.0 resolved it by adding a
 * type scale. The scope here follows the README's table, not a guess.
 */
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const THEME_DIR = join(import.meta.dirname, '..', 'src', 'shared', 'ui', 'theme');

/** `--spacing-x-slot-foo: <value>;` / `--radius-x-slot-foo: <value>;` */
const SLOT = /--(?:spacing|radius|text|color)-x-slot-[a-z0-9-]+\s*:\s*([^;]+);/g;
/**
 * A legal reference into one of the scales.
 *
 * The check works by removal rather than by splitting: strip every legal reference and the
 * syntax that may join them, and whatever is left is a literal. Splitting on whitespace
 * looks simpler and is wrong — `max(var(--a), var(--b))` contains a space inside the
 * parentheses, so a naive split tears the function apart and rejects a legal value. That
 * bug was in the first version of this file and was caught by a positive control, not by
 * the negative ones: every literal was still detected while legal input was refused too.
 */
const REFERENCE = /var\(--(?:spacing|radius|text|color)-[a-z0-9-]+\)/g;
/** `max()` / `min()` / `clamp()` wrap references; the kit uses max() for the iOS floor. */
const WRAPPER = /\b(?:max|min|clamp)\(/g;

const files = readdirSync(THEME_DIR, { recursive: true })
  .filter((f) => typeof f === 'string' && f.endsWith('.css'))
  .map((f) => join(THEME_DIR, f));

const violations = [];

for (const file of files) {
  // 🔴 Comments first, and by blanking rather than deleting so line numbers survive.
  // A slot written inside a comment — explaining what the kit's default is, say — is not a
  // declaration, but the pattern below cannot tell the two apart. Reported as a violation it
  // is worse than noise: it fails a build over prose. (Found by reading the checker's output
  // instead of its exit code; every negative control still "passed" while it was wrong.)
  const css = readFileSync(file, 'utf8').replace(/\/\*[\s\S]*?\*\//g, (c) =>
    c.replace(/[^\n]/g, ' '),
  );
  for (const match of css.matchAll(SLOT)) {
    const rawValue = match[1];
    const value = rawValue.trim();
    // A composed value is several references — four-sided padding is just CSS.
    const residue = value
      .replace(REFERENCE, ' ')
      .replace(WRAPPER, ' ')
      .replace(/[(),\s]/g, '');
    if (residue !== '') {
      // From the match's own index — searching for the value text again lands on the
      // first place that string appears anywhere in the file, which is rarely this one.
      const line = css.slice(0, match.index).split('\n').length;
      violations.push(`${file.replace(/.*\/src\//, 'src/')}:${line}  ${value}   ← ${residue}`);
    }
  }
}

if (violations.length > 0) {
  console.error('slot-values: a slot holds a literal instead of a scale reference.\n');
  violations.forEach((v) => console.error(`  ${v}`));
  console.error(
    '\n  A slot chooses a step; it may not invent one. Use var() into the spacing, radius,\n  text or colour scale (brightness and opacity are exempt — they have no scale).',
  );
  process.exit(1);
}

console.log('slot-values: OK — every spacing, radius, text and colour slot references a scale.');
