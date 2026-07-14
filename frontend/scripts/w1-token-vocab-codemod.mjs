#!/usr/bin/env node
/**
 * W1 — Core Token Contract v1 color-vocabulary codemod for nene-vault.
 *
 * Source of truth: the frozen, hide-approved 40-row vault mapping table shipped in
 * `@hideyukimori/nene2-tokens@1.0.0` as `CODEMOD_MAP_V1.tables.vault` (VAULT_TABLE).
 * It is inlined below VERBATIM (snapshot) so this script is self-contained and
 * re-runnable in CI without adding a one-shot dependency — same shape as
 * nene-suite PR #381's `w1-stage1-token-rename.mjs`.
 *
 * Why a repo script instead of `npx nene2-tokens extract --map vault | generate`:
 *   (B-1) vault's themes/default.css is NOT a token-only file — it bakes @theme +
 *         @layer base + @layer components into one ~2000-line stylesheet, so the
 *         token-only parser rejects it and extract/generate/validate all fail-closed.
 *         -> hideyukiMORI/nene2-fleet-tooling (non-token-only theme unsupported).
 *   (B-2) the shipped mapTokenName() checks isContractTokenName() BEFORE the per-repo
 *         table, so it silently drops the table's `surface -> surface-raised` row
 *         (vault's card-face `--color-surface` is itself a contract name) and collides
 *         it with `bg -> surface` (extractTheme throws; a raw rename would duplicate
 *         --color-surface with two values = visual regression).
 *         -> hideyukiMORI/nene2-fleet-tooling (precedence/collision, family of #23/#24).
 *   Applying VAULT_TABLE literally honors the frozen table's declared intent (40/40),
 *   is collision-free (all 40 targets distinct), and preserves appearance.
 *
 * SCOPE: contract color vocabulary only (the 40-row table). Non-color tokens
 *   (font / text / radius / spacing / rail-width / z / content / topbar-height) are
 *   intentionally NOT x-sent this stage: vault is Tailwind v4 and fleet-tooling#17
 *   (mechanical x- send does not preserve TW v4 namespace semantics) is unresolved.
 *
 * Idempotent single pass (independent matches + negative lookahead — no double-apply).
 *
 * Usage:
 *   node scripts/w1-token-vocab-codemod.mjs           # apply
 *   node scripts/w1-token-vocab-codemod.mjs --check    # verify no legacy refs remain (exit 1 if any)
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// ---- frozen VAULT_TABLE, snapshot of @hideyukimori/nene2-tokens@1.0.0 ----
const VAULT_TABLE = {
  bg: 'surface',
  surface: 'surface-raised',
  'surface-2': 'surface-overlay',
  sunk: 'surface-sunken',
  'sunk-2': 'x-sunk-deep',
  text: 'text-primary',
  'text-muted': 'text-muted',
  'text-faint': 'text-faint',
  ink: 'x-ink',
  'ink-2': 'x-ink-deep',
  line: 'border',
  'line-2': 'x-line-mid',
  'line-strong': 'border-strong',
  navy: 'accent',
  'navy-hover': 'accent-hover',
  'navy-soft': 'accent-soft',
  'navy-deep': 'x-navy-deep',
  'navy-line': 'x-navy-line',
  'on-navy': 'on-accent',
  brass: 'x-brass',
  'brass-deep': 'x-brass-deep',
  'brass-soft': 'x-brass-soft',
  'brass-line': 'x-brass-line',
  'on-brass': 'x-on-brass',
  seal: 'x-seal',
  'seal-bright': 'x-seal-bright',
  success: 'success',
  'success-soft': 'success-soft',
  danger: 'danger',
  'danger-soft': 'danger-soft',
  'danger-hover': 'x-danger-hover',
  warning: 'warn',
  'warning-soft': 'warn-soft',
  'warning-ink': 'on-warn',
  rail: 'x-rail',
  'rail-2': 'x-rail-deep',
  'rail-faint': 'x-rail-faint',
  'rail-ink': 'x-rail-ink',
  'rail-line': 'x-rail-line',
  'rail-text': 'x-rail-text',
};

const CHECK = process.argv.includes('--check');
const FRONTEND = path.resolve(fileURLToPath(import.meta.url), '../..');
const SRC = path.join(FRONTEND, 'src');
const CSS = path.join(SRC, 'shared/ui/theme/themes/default.css');

// color-consuming Tailwind v4 utility namespaces
const NS = [
  'text',
  'bg',
  'border',
  'border-t',
  'border-r',
  'border-b',
  'border-l',
  'border-x',
  'border-y',
  'border-s',
  'border-e',
  'ring',
  'ring-offset',
  'outline',
  'fill',
  'stroke',
  'from',
  'via',
  'to',
  'accent',
  'caret',
  'decoration',
  'divide',
  'placeholder',
  'shadow',
];
const nsAlt = [...NS].sort((a, b) => b.length - a.length).join('|');
const changed = Object.keys(VAULT_TABLE)
  .filter((k) => VAULT_TABLE[k] !== k)
  .map((k) => [k, VAULT_TABLE[k]])
  .sort((a, b) => b[0].length - a[0].length);
const esc = (s) => s.replace(/-/g, '\\-');

function walk(dir, acc = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p, acc);
    else if (/\.(tsx|ts)$/.test(e.name)) acc.push(p);
  }
  return acc;
}

// ---- CSS: rename every --color-<key> (definitions + var() refs) verbatim from the table
function renameCss(css) {
  let counts = 0;
  const unknown = new Set();
  const out = css.replace(/--color-([a-zA-Z0-9-]+)/g, (whole, key) => {
    if (!(key in VAULT_TABLE)) {
      unknown.add(whole);
      return whole;
    }
    const to = `--color-${VAULT_TABLE[key]}`;
    if (to !== whole) counts++;
    return to;
  });
  return { out, counts, unknown };
}

// ---- TSX: rename color Tailwind utilities for changed keys
function renameTsx(src) {
  let counts = 0;
  for (const [oldKey, newKey] of changed) {
    const re = new RegExp(`(^|[\\s"'\`:{(])(${nsAlt})-${esc(oldKey)}(?![a-zA-Z0-9-])`, 'g');
    src = src.replace(re, (_m, pre, ns) => {
      counts++;
      return `${pre}${ns}-${newKey}`;
    });
  }
  return { out: src, counts };
}

if (CHECK) {
  // verify no legacy (pre-rename) --color name or color-utility remains
  const oldColor = Object.keys(VAULT_TABLE).filter((k) => VAULT_TABLE[k] !== k);
  const validTargets = new Set(Object.values(VAULT_TABLE));
  let remaining = 0;
  const css = fs.readFileSync(CSS, 'utf8');
  for (const name of new Set([...css.matchAll(/--color-([a-zA-Z0-9-]+)/g)].map((m) => m[1]))) {
    if (!validTargets.has(name)) {
      console.log(`LEGACY css token --color-${name}`);
      remaining++;
    }
  }
  for (const f of walk(SRC)) {
    const src = fs.readFileSync(f, 'utf8');
    for (const oldKey of oldColor) {
      const re = new RegExp(`(^|[\\s"'\`:{(])(${nsAlt})-${esc(oldKey)}(?![a-zA-Z0-9-])`, 'g');
      if (re.test(src)) {
        console.log(`LEGACY util ${oldKey} in ${path.relative(FRONTEND, f)}`);
        remaining++;
      }
    }
  }
  console.log(`REMAINING legacy refs: ${remaining}`);
  process.exit(remaining > 0 ? 1 : 0);
}

// apply — gated on legacy presence so it is idempotent despite the bg/surface swap
// (post-migration `--color-surface` is ambiguous with old card-face `surface`; a fresh
// tree is unambiguous because bg is still `--color-bg`). strictLegacy = changed keys that
// are not themselves a valid target.
const validTargets = new Set(Object.values(VAULT_TABLE));
const strictLegacy = new Set(
  Object.keys(VAULT_TABLE).filter((k) => VAULT_TABLE[k] !== k && !validTargets.has(k)),
);
const cssSrc0 = fs.readFileSync(CSS, 'utf8');
const present = new Set([...cssSrc0.matchAll(/--color-([a-zA-Z0-9-]+)/g)].map((m) => m[1]));
if (![...present].some((k) => strictLegacy.has(k))) {
  console.log('No legacy color tokens present — already applied (idempotent no-op).');
  process.exit(0);
}
const css = renameCss(cssSrc0);
if (css.unknown.size) {
  const truly = [...css.unknown].filter((n) => !validTargets.has(n.slice('--color-'.length)));
  if (truly.length) {
    console.error(
      'FATAL: --color tokens neither in VAULT_TABLE nor a valid target (silent drop prohibited):',
      truly,
    );
    process.exit(2);
  }
}
fs.writeFileSync(CSS, css.out);
let cssOcc = css.counts,
  tsxOcc = 0,
  tsxFiles = 0;
for (const f of walk(SRC)) {
  const r = renameTsx(fs.readFileSync(f, 'utf8'));
  if (r.counts) {
    fs.writeFileSync(f, r.out);
    tsxOcc += r.counts;
    tsxFiles++;
  }
}
console.log(
  `CSS --color renames: ${cssOcc} occ | TSX color-utility renames: ${tsxOcc} in ${tsxFiles} file(s)`,
);
console.log('Applied. Run `npm run format` (deterministic prettier) then `npm run check`.');
