import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * The touch-device font floor must not be lowered.
 *
 * 🔴 iOS Safari zooms the page when a focused control's font-size is under 16px. The kit
 * carries that as `--text-x-slot-control-touch-size`, applied under `pointer-coarse:` so it
 * reaches phones and landscape tablets without touching the desktop size this product
 * chooses in `--text-x-slot-control-size`.
 *
 * 🔴 Why a test and not the `slot-values` rule. Lowering the floor to a smaller step is
 * still `var()` into the scale, so it is *legal* by that rule and passes silently. The rule
 * guards against inventing values; this guards against choosing the wrong one. They are
 * different failures and only one of them is about syntax.
 *
 * 🔑 The floor is a device constraint, not a design value — the one number in the theme that
 * is not the product's to pick. vault is the kit's first consumer, so what happens here is
 * what the next ship copies (#393).
 */
const THEME = readFileSync(join(import.meta.dirname, 'themes', 'default.css'), 'utf8');

/** Declarations only — a slot named inside a comment is prose, not a value. */
const CSS = THEME.replace(/\/\*[\s\S]*?\*\//g, '');

const override = (slot: string): string | null =>
  new RegExp(`--${slot}\\s*:\\s*([^;]+);`).exec(CSS)?.[1]?.trim() ?? null;

describe('control touch floor', () => {
  it('does not lower the touch size below the kit floor', () => {
    const value = override('text-x-slot-control-touch-size');

    // Not overriding at all is the expected state: the kit's default is the floor itself.
    if (value === null) {
      return;
    }

    // If it is overridden, the only defensible value is the floor.
    expect(value).toBe('var(--text-x-ios-floor)');
  });

  it('still chooses its own pointer-fine size, so the two are not conflated', () => {
    expect(override('text-x-slot-control-size')).toBe('var(--text-x-sm)');
  });
});
