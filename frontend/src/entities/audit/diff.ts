// Pure audit-event diff utilities. Compares the before/after JSON snapshots that
// the API records on every mutation and produces a per-field change list used by
// the audit-log change summary and detail drawer.

export interface AuditDiffField {
  key: string;
  /** 'add' = field only present in the after snapshot (e.g. an upload). */
  kind: 'add' | 'mod';
  before: unknown;
  after: unknown;
}

/** Renders a value the way it appears in the diff/JSON views. */
export function formatAuditValue(value: unknown): string {
  if (value === null || value === undefined) {
    return 'null';
  }
  if (typeof value === 'string') {
    return `"${value}"`;
  }
  return JSON.stringify(value);
}

/**
 * Key-order-independent deep equality. Two objects with the same entries in a
 * different key order are equal; arrays stay order-sensitive. Used instead of
 * `JSON.stringify` comparison so a change in the server's JSON serialization
 * order does not surface as a spurious `mod` in the audit change summary.
 */
function deepEqual(a: unknown, b: unknown): boolean {
  if (a === b) {
    return true;
  }
  if (typeof a !== 'object' || typeof b !== 'object' || a === null || b === null) {
    return false;
  }
  const aIsArray = Array.isArray(a);
  const bIsArray = Array.isArray(b);
  if (aIsArray !== bIsArray) {
    return false;
  }
  if (aIsArray && bIsArray) {
    const aArr = a as unknown[];
    const bArr = b as unknown[];
    return aArr.length === bArr.length && aArr.every((item, i) => deepEqual(item, bArr[i]));
  }
  const aObj = a as Record<string, unknown>;
  const bObj = b as Record<string, unknown>;
  const aKeys = Object.keys(aObj);
  const bKeys = Object.keys(bObj);
  return (
    aKeys.length === bKeys.length &&
    aKeys.every(
      (key) => Object.prototype.hasOwnProperty.call(bObj, key) && deepEqual(aObj[key], bObj[key]),
    )
  );
}

/**
 * Returns the fields that changed between two snapshots. When `before` is null
 * (a creation event), every field of `after` is reported as an addition.
 */
export function diffAuditEvent(
  before: Record<string, unknown> | null,
  after: Record<string, unknown> | null,
): AuditDiffField[] {
  const fields: AuditDiffField[] = [];

  if (before === null) {
    if (after === null) {
      return fields;
    }
    for (const key of Object.keys(after)) {
      fields.push({ key, kind: 'add', before: null, after: after[key] });
    }
    return fields;
  }

  const afterObj = after ?? {};
  const keys = [...new Set([...Object.keys(before), ...Object.keys(afterObj)])];
  for (const key of keys) {
    const b = before[key];
    const a = afterObj[key];
    if (!deepEqual(b, a)) {
      fields.push({ key, kind: 'mod', before: b, after: a });
    }
  }
  return fields;
}
