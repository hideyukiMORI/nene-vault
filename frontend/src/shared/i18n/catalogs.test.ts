import { describe, expect, it } from 'vitest';
import { catalogs, dynamicMessageKey } from './catalogs';

/** Collects every dot-notation path to a non-object leaf. */
function leafPaths(value: unknown, prefix = ''): string[] {
  if (value === null || typeof value !== 'object') {
    return [prefix];
  }
  const paths: string[] = [];
  for (const [key, child] of Object.entries(value)) {
    const path = prefix ? `${prefix}.${key}` : key;
    paths.push(...leafPaths(child, path));
  }
  return paths;
}

describe('catalogs', () => {
  it('exposes both supported locales', () => {
    expect(Object.keys(catalogs).sort()).toEqual(['en', 'ja']);
  });

  it('ja and en share an identical key structure (#137 / #166 parity guard)', () => {
    const ja = leafPaths(catalogs.ja).sort();
    const en = leafPaths(catalogs.en).sort();

    expect(ja.length).toBeGreaterThan(0);
    expect(en).toEqual(ja);
  });
});

describe('dynamicMessageKey', () => {
  it('returns the key unchanged (runtime identity escape hatch)', () => {
    const key = 'audit_event.action.document.uploaded';
    expect(dynamicMessageKey(key)).toBe(key);
  });
});
