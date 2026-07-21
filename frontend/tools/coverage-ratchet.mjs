#!/usr/bin/env node
// Coverage ratchet — fleet T1 (Issue #277; payout #241 型).
//
// Compares the freshly measured coverage summary against the frozen floor in
// coverage.baseline.json. The gate is SHRINK-ONLY: it fails if any metric drops
// below its baseline. It deliberately does NOT enforce a uniform numeric target
// (council G-5) — the floor is whatever the repo already achieves, and it may
// only go up. When coverage improves, raise coverage.baseline.json in the same PR.
//
// Reads:  coverage/coverage-summary.json  (written by `vitest run --coverage`)
// Floor:  coverage.baseline.json
// Exit:   0 = all metrics >= baseline; 1 = a regression or missing input.

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const summaryPath = path.join(root, 'coverage', 'coverage-summary.json');
const baselinePath = path.join(root, 'coverage.baseline.json');

// Float noise guard: v8 coverage is deterministic, but summary pct is rounded to
// two decimals, so allow equality within a hundredth before flagging a drop.
const EPSILON = 0.01;
const METRICS = ['statements', 'branches', 'functions', 'lines'];

function readJson(p, label) {
  try {
    return JSON.parse(readFileSync(p, 'utf8'));
  } catch (err) {
    console.error(`coverage-ratchet: cannot read ${label} at ${p}`);
    console.error(`  ${err.message}`);
    if (label === 'coverage summary') {
      console.error('  Run `npm run coverage` first (it writes coverage/coverage-summary.json).');
    }
    process.exit(1);
  }
}

const summary = readJson(summaryPath, 'coverage summary');
const baseline = readJson(baselinePath, 'coverage baseline');

const total = summary.total;
const floor = baseline.metrics;

let failed = false;
const rows = [];
for (const metric of METRICS) {
  const current = total[metric].pct;
  const min = floor[metric];
  const delta = current - min;
  const ok = delta >= -EPSILON;
  if (!ok) failed = true;
  rows.push(
    `  ${ok ? 'OK  ' : 'FAIL'} ${metric.padEnd(11)} ${current.toFixed(2)}%  (floor ${min.toFixed(2)}%, Δ ${delta >= 0 ? '+' : ''}${delta.toFixed(2)})`,
  );
}

console.log('coverage-ratchet (shrink-only floor):');
console.log(rows.join('\n'));

if (failed) {
  console.error(
    '\ncoverage-ratchet: FAIL — coverage dropped below the frozen floor.\n' +
      '  Add tests to restore it, or, if the drop is intentional and justified,\n' +
      '  lower coverage.baseline.json in the same PR with a reason in the review.',
  );
  process.exit(1);
}
console.log('\ncoverage-ratchet: OK — no metric below its floor.');
