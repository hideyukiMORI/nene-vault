import { expectCatalogParity } from '@hideyukimori/nene2-i18n/testing';
import { describe, expect, it } from 'vitest';
import { catalogs, dynamicMessageKey } from './catalogs';

// Flatten a nested catalog to `{ 'a.b.c': value }`. nene2-i18n 0.2.0's
// `expectCatalogParity` operates on a FLAT `Record<string, string>` catalog
// (the skeleton MessageCatalog); the nested "vault JSON form" (DotPaths) is a
// 0.3.0 / W0b feature (see catalog.d.ts) and is not yet natively accepted. This
// thin adapter feeds the shared parity logic the real leaf keys so shape and
// lazy-copy checks run on the actual message keys, not just the top-level groups.
function flatten(
  value: unknown,
  prefix = '',
  out: Record<string, string> = {},
): Record<string, string> {
  if (value === null || typeof value !== 'object') {
    out[prefix] = String(value);
    return out;
  }
  for (const [key, child] of Object.entries(value)) {
    flatten(child, prefix ? `${prefix}.${key}` : key, out);
  }
  return out;
}

describe('catalogs', () => {
  it('exposes both supported locales', () => {
    expect(Object.keys(catalogs).sort()).toEqual(['en', 'ja']);
  });

  it('ja/en share an identical key shape and en is not a lazy copy of ja (#137/#166)', () => {
    // Fleet-shared parity guard (nene2-i18n `./testing`, AM-17): shape 100% vs the
    // authority (ja) plus a lazy-copy ratio check. ja is the authority (ADR 0008).
    // `_meta` carries locale self-description (label/direction/note) that is
    // legitimately identical or per-locale; allowlist the keys that are the same
    // in every locale by design (direction; the shared English note).
    const flat = { ja: flatten(catalogs.ja), en: flatten(catalogs.en) };
    expectCatalogParity(flat, {
      authority: 'ja',
      identicalAllowlist: ['_meta.direction', '_meta.note'],
    });
  });
});

describe('dynamicMessageKey', () => {
  it('returns the key unchanged (runtime identity escape hatch)', () => {
    const key = 'audit_event.action.document.uploaded';
    expect(dynamicMessageKey(key)).toBe(key);
  });
});
