import { describe, expect, it } from 'vitest';
import { lookup, interpolate, translate } from './translate';
import type { LocaleCatalog } from './catalogs';

const catalog = {
  common: {
    buttons: { save: '保存', cancel: 'キャンセル' },
    status: { loading: '読み込み中...' },
  },
  document: {
    category: { invoice_received: '請求書（受取）' },
  },
} as unknown as LocaleCatalog;

describe('lookup', () => {
  it('resolves a dot-notation key to its value', () => {
    expect(lookup(catalog, 'common.buttons.save')).toBe('保存');
  });

  it('resolves a two-level key', () => {
    expect(lookup(catalog, 'common.status.loading')).toBe('読み込み中...');
  });

  it('returns the key itself when not found', () => {
    expect(lookup(catalog, 'no.such.key')).toBe('no.such.key');
  });

  it('returns the key when a node is not an object', () => {
    expect(lookup(catalog, 'common.buttons.save.extra')).toBe('common.buttons.save.extra');
  });
});

describe('interpolate', () => {
  it('replaces {{name}} placeholders', () => {
    expect(interpolate('{{from}}〜{{to}}件', { from: '1', to: '20' })).toBe('1〜20件');
  });

  it('returns template unchanged when params is undefined', () => {
    expect(interpolate('hello world')).toBe('hello world');
  });

  it('leaves unknown placeholders intact', () => {
    expect(interpolate('{{from}}〜{{to}}件', { from: '1' })).toBe('1〜{{to}}件');
  });

  it('converts numeric params to string', () => {
    expect(interpolate('total: {{n}}', { n: 42 })).toBe('total: 42');
  });
});

describe('translate', () => {
  it('looks up a ja string', () => {
    expect(translate('ja', 'document.category.invoice_received')).toBe('受取請求書');
  });

  it('looks up an en string', () => {
    expect(translate('en', 'document.category.invoice_received')).toBe('Invoice Received');
  });

  it('interpolates params in the translated string', () => {
    // Use a key that has {{...}} in the real catalog
    const result = translate('ja', 'common.pagination.showing', {
      from: '1',
      to: '20',
      total: '100',
    });
    expect(result).toContain('1');
    expect(result).toContain('20');
    expect(result).toContain('100');
  });

  it('falls back to the key when the key is missing', () => {
    expect(translate('ja', 'nonexistent.key')).toBe('nonexistent.key');
  });
});
