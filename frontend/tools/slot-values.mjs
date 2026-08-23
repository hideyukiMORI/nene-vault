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
 * Scope: spacing and radius slots. Those are the ones backed by a locked scale, and the
 * kit's own theme holds literals in the other namespaces (`--text-x-slot-field-label-size`,
 * `--brightness-x-slot-hover`) — so a check over every namespace would fail the kit itself.
 */
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const THEME_DIR = join(import.meta.dirname, '..', 'src', 'shared', 'ui', 'theme');

/** `--spacing-x-slot-foo: <value>;` / `--radius-x-slot-foo: <value>;` */
const SLOT = /--(?:spacing|radius)-x-slot-[a-z0-9-]+\s*:\s*([^;]+);/g;
/** A value is legal when every whitespace-separated part is a var() into the scale. */
const REFERENCE = /^var\(--(?:spacing|radius)-x-[a-z0-9-]+\)$/;

const files = readdirSync(THEME_DIR, { recursive: true })
  .filter((f) => typeof f === 'string' && f.endsWith('.css'))
  .map((f) => join(THEME_DIR, f));

const violations = [];

for (const file of files) {
  const css = readFileSync(file, 'utf8');
  for (const match of css.matchAll(SLOT)) {
    const rawValue = match[1];
    const value = rawValue.replace(/\/\*[\s\S]*?\*\//g, '').trim();
    // A composed value is several references — four-sided padding is just CSS.
    const parts = value.split(/\s+(?![^(]*\))/).filter(Boolean);
    const bad = parts.filter((p) => !REFERENCE.test(p));
    if (bad.length > 0) {
      // From the match's own index — searching for the value text again lands on the
      // first place that string appears anywhere in the file, which is rarely this one.
      const line = css.slice(0, match.index).split('\n').length;
      violations.push(
        `${file.replace(/.*\/src\//, 'src/')}:${line}  ${value}   ← ${bad.join(' ')}`,
      );
    }
  }
}

if (violations.length > 0) {
  console.error('slot-values: a slot holds a literal instead of a scale reference.\n');
  violations.forEach((v) => console.error(`  ${v}`));
  console.error(
    '\n  A slot chooses a step; it may not invent one. Use var(--spacing-x-*) / var(--radius-x-*).',
  );
  process.exit(1);
}

console.log('slot-values: OK — every spacing/radius slot references the scale.');
