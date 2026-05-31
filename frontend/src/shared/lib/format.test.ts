import { describe, expect, it } from 'vitest';
import { formatJpy, formatDate, formatDateTime } from './format';

describe('formatJpy', () => {
  it('returns em dash for null', () => {
    expect(formatJpy(null, 'ja')).toBe('—');
    expect(formatJpy(null, 'en')).toBe('—');
  });

  it('formats yen for ja locale', () => {
    const out = formatJpy(110000, 'ja');
    expect(out).toContain('110,000');
    expect(out).toMatch(/[¥￥]/);
  });

  it('formats yen for en locale', () => {
    const out = formatJpy(110000, 'en');
    expect(out).toContain('110,000');
    // en-US renders JPY with the ¥ symbol
    expect(out).toMatch(/¥|JPY/);
  });

  it('has no fractional digits (JPY has no minor unit)', () => {
    expect(formatJpy(1000, 'en')).not.toContain('.00');
  });
});

describe('formatDate', () => {
  it('returns em dash for null', () => {
    expect(formatDate(null)).toBe('—');
  });

  it('returns the ISO date verbatim', () => {
    expect(formatDate('2026-03-31')).toBe('2026-03-31');
  });
});

describe('formatDateTime', () => {
  it('returns em dash for null/undefined/empty', () => {
    expect(formatDateTime(null, 'ja')).toBe('—');
    expect(formatDateTime(undefined, 'en')).toBe('—');
    expect(formatDateTime('', 'ja')).toBe('—');
  });

  it('formats a valid ISO timestamp for ja', () => {
    const out = formatDateTime('2026-03-31T09:05:00Z', 'ja');
    expect(out).toContain('2026');
    expect(out).toContain('03');
  });

  it('formats a valid ISO timestamp for en', () => {
    const out = formatDateTime('2026-03-31T09:05:00Z', 'en');
    expect(out).toContain('2026');
  });

  it('falls back to trimmed ISO for an unparseable value', () => {
    expect(formatDateTime('not-a-date', 'ja')).toBe('not-a-date');
  });
});
