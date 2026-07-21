import { describe, expect, it } from 'vitest';
import { diffAuditEvent, formatAuditValue } from './diff';

describe('formatAuditValue', () => {
  it('renders null and undefined as "null"', () => {
    expect(formatAuditValue(null)).toBe('null');
    expect(formatAuditValue(undefined)).toBe('null');
  });

  it('wraps strings in double quotes', () => {
    expect(formatAuditValue('hello')).toBe('"hello"');
    expect(formatAuditValue('')).toBe('""');
  });

  it('renders numbers and booleans via JSON', () => {
    expect(formatAuditValue(42)).toBe('42');
    expect(formatAuditValue(0)).toBe('0');
    expect(formatAuditValue(true)).toBe('true');
    expect(formatAuditValue(false)).toBe('false');
  });

  it('renders arrays and objects as JSON', () => {
    expect(formatAuditValue([1, 2])).toBe('[1,2]');
    expect(formatAuditValue({ a: 1 })).toBe('{"a":1}');
  });
});

describe('diffAuditEvent', () => {
  it('returns an empty list when both snapshots are null', () => {
    expect(diffAuditEvent(null, null)).toEqual([]);
  });

  it('reports every field as an addition on a creation event (before null)', () => {
    const after = { amount_cents: 1000, counterparty_name: 'ACME' };

    expect(diffAuditEvent(null, after)).toEqual([
      { key: 'amount_cents', kind: 'add', before: null, after: 1000 },
      { key: 'counterparty_name', kind: 'add', before: null, after: 'ACME' },
    ]);
  });

  it('reports only the fields that changed between two snapshots', () => {
    const before = { amount_cents: 1000, counterparty_name: 'ACME', note: 'x' };
    const after = { amount_cents: 2000, counterparty_name: 'ACME', note: 'x' };

    expect(diffAuditEvent(before, after)).toEqual([
      { key: 'amount_cents', kind: 'mod', before: 1000, after: 2000 },
    ]);
  });

  it('excludes fields whose values are deeply equal', () => {
    const before = { tags: ['a', 'b'], meta: { n: 1 } };
    const after = { tags: ['a', 'b'], meta: { n: 1 } };

    expect(diffAuditEvent(before, after)).toEqual([]);
  });

  it('treats objects with the same entries in a different key order as equal', () => {
    const before = { meta: { a: 1, b: 2 } };
    const after = { meta: { b: 2, a: 1 } };

    expect(diffAuditEvent(before, after)).toEqual([]);
  });

  it('keeps array element order significant', () => {
    const before = { tags: ['a', 'b'] };
    const after = { tags: ['b', 'a'] };

    expect(diffAuditEvent(before, after)).toEqual([
      { key: 'tags', kind: 'mod', before: ['a', 'b'], after: ['b', 'a'] },
    ]);
  });

  it('detects changes inside nested structures', () => {
    const before = { meta: { n: 1 } };
    const after = { meta: { n: 2 } };

    expect(diffAuditEvent(before, after)).toEqual([
      { key: 'meta', kind: 'mod', before: { n: 1 }, after: { n: 2 } },
    ]);
  });

  it('reports a key present only in the after snapshot as a modification', () => {
    const before = { amount_cents: 1000 };
    const after = { amount_cents: 1000, counterparty_name: 'ACME' };

    expect(diffAuditEvent(before, after)).toEqual([
      { key: 'counterparty_name', kind: 'mod', before: undefined, after: 'ACME' },
    ]);
  });

  it('reports a removed field (after null) as a modification to undefined', () => {
    const before = { amount_cents: 1000, note: 'x' };

    expect(diffAuditEvent(before, null)).toEqual([
      { key: 'amount_cents', kind: 'mod', before: 1000, after: undefined },
      { key: 'note', kind: 'mod', before: 'x', after: undefined },
    ]);
  });

  it('does not treat a field that is absent on both sides as a change', () => {
    const before = { amount_cents: 1000 };
    const after = { amount_cents: 1000 };

    expect(diffAuditEvent(before, after)).toEqual([]);
  });
});
