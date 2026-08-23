import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * The alert tones must stay distinguishable by sight.
 *
 * 🔴 This replaces the tone assertions that lived in `Callout.test.tsx` until #390 wave 3.
 * That component is gone — its job is `@hideyukimori/nene2-ui`'s `InlineAlert` now — but the
 * guarantee it carried is still this product's: a warning and a failure must not look the
 * same. The kit's defaults deliberately give both tones the same three colours (its palette
 * has one alert hue) and expose them as slots so a product can decide otherwise. vault does.
 *
 * 🔴 Asserted against the theme file rather than a render, because that is where the fact
 * lives. jsdom does not compute styles, so a render-level test can only see class names —
 * and the class names are identical in shape whether or not the slots were ever overridden.
 * The failure this guards is exactly "the classes are there and the colours are not", which
 * is the regression the old test was written for (#337).
 */
const THEME = readFileSync(join(import.meta.dirname, 'themes', 'default.css'), 'utf8');

const slot = (name: string): string | null => {
  const match = new RegExp(`--color-x-slot-alert-${name}\\s*:\\s*([^;]+);`).exec(THEME);
  return match?.[1]?.trim() ?? null;
};

describe('alert tones', () => {
  it.each(['bg', 'fg', 'border'])('defines the warn %s slot', (part) => {
    expect(slot(`warn-${part}`)).not.toBeNull();
  });

  it.each(['bg', 'fg', 'border'])('defines the danger %s slot', (part) => {
    expect(slot(`danger-${part}`)).not.toBeNull();
  });

  it.each(['bg', 'fg', 'border'])(
    'gives warn and danger a different %s, so the two are told apart by sight',
    (part) => {
      expect(slot(`warn-${part}`)).not.toBe(slot(`danger-${part}`));
    },
  );

  it('tints the alert background rather than leaving it the page surface', () => {
    expect(slot('warn-bg')).not.toBe('var(--color-surface)');
    expect(slot('danger-bg')).not.toBe('var(--color-surface)');
  });
});
