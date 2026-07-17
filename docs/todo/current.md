# Current TODO

**All roadmap phases (0–4) complete; compliance gate approved. Remaining work is the
pre-production go-live gate (税理士 Review 3) and Tier A live testing.**

> **2026-07-10: the prospect demo is live at `https://vault.ayane.co.jp`**
> (org `ayane`, seeded via `tools/seed-demo.php` — ~20 generated received
> invoices, void/restore history; runbook [`docs/demo.md`](../demo.md)).
> Shipping it surfaced and fixed four real Tier A bugs the same day:
> #120 installer unreachable with the documented docroot (ported to the
> invoice/clear wizard shape in `public_html/`), #122 release zip shipped no
> framework (path-repo symlink never dereferenced), #124 `.env` never reached
> raw `getenv()` readers (org resolution 404'd on shared hosting), and the
> HETEML `Authorization`-stripping fix (#118, `X-Authorization` mirror).
> Owner cron step: register `~/bin/reseed-vault-demo.sh` (nightly) in the
> HETEML panel.
>
> **2026-07-10 (late): disposable-org demo shipped (#141).** The #118 blocker
> was resolved by claim-based tenant resolution: `AdminApiAuthMiddleware` now
> runs before `OrgResolverMiddleware`, and a verified bearer's `org_id` claim
> resolves the tenant ahead of the host/env strategy. `/demo/standard` is the
> disposable-org distribution link (admin seat, upload showcase, TTL 3 h);
> the fixed viewer seat moved to `/demo/guided`. Owner cron step: register
> `~/bin/sweep-vault-demo.sh` (hourly, minute :40) in the HETEML panel.
>
> **2026-07-11: structural-alignment audit recorded** — fleet-wide audit
> findings filed as #148 (session posture, security) / #149 (frontend
> generation gap) / #150 (consolidated checklist); summary with strengths in
> `docs/review/structural-alignment-audit-2026-07-11.md`.
>
> **2026-07-13/14: first live-fire security reports — `EXPOSED 0` both rounds.**
> Round 1 (#194) is a broad black-box ATK battery; round 2 (#198) verifies by
> live fire that the two vulnerability types sibling **nene-records** demonstrated
> against itself — in its own self-assessment, and fixed there the same week — are
> absent here. Both are authorized self / maintainer-run
> assessments against a disposable local stack — **not** third-party penetration
> tests, and no production host was targeted. See `docs/security/`.
>
> **2026-07-14 → 07-16: fleet frontend standards (W1) adopted** — NENE2 client
> transport, Core Token Contract v1 vocabulary, theme file split, and generated
> types wired through to the entities. No product behaviour change.

## Done

- [x] Governance bootstrap, product definition (PR #1, #2)
- [x] Coding standards + review checklists (PR #4)
- [x] `docs/terms.md` canonical identifier registry (PR #6)
- [x] Multi-tenancy foundation — Auth / Organization / VaultSettings (PR #8)
- [x] Audit logging — before/after on all mutations (PR #10)
- [x] ja/en locale files (PR #12)
- [x] OpenAPI 3.1 contract — all Phase 1 endpoints (PR #14)
- [x] Document upload + storage + get detail (PR #16)
- [x] Document search — date/amount/counterparty/category (PR #18)
- [x] Metadata edit + void/restore + history (PR #20)
- [x] Version download — SHA-256 verified (PR #22)
- [x] User CRUD endpoints (PR #24)
- [x] Manifest export — CSV (PR #26)
- [x] Tax advisor review package prepared (Issue #27)

## Compliance gate — Resolved ✅

- [x] **税理士 review gate** — licensed professional sign-off recorded 2026-05-31
      (辻村 拓也 / 辻村総合会計事務所 / 公認会計士・税理士). Gate status: 🟢 Approved.
      See `docs/compliance-review/signoff-record.md`.
      **Condition:** pre-production Review 3 required before operators go live.

## Phase 2 — Done

- [x] Admin UI scaffold — React + Vite + ja/en locale integration (PR #31, #37)
- [x] Frontend pages — Documents, Detail, Upload, Audit, Users, Settings, Export (PR #39–#48)
- [x] Frontend tests — MSW + unit coverage (PR #51–#56)
- [x] Docker Compose dev environment (PR #57, #58)
- [x] Export ZIP bundling — manifest CSV + document files in single archive (PR #64)

## Phase 3 — Done

- [x] Operator guide (storage, backup, retention, search, export) — `docs/operator/` (PR #66)
- [x] 事務処理規程 template — `docs/operator/jimu-shokirei-template.md` (PR #66)
- [x] Web installer + release ZIP (Tier A shared hosting) — `install.php` + `tools/build-release.sh` (PR #68)

## Phase 4 — Done

- [x] MCP read tools — `searchVaultDocuments`, `getVaultDocumentById`, `getVaultDocumentHistory`, `listVaultAuditEvents` (PR #70)
- [x] MCP write tools + OCR/export integration guide (PR #80)
- [x] S3-compatible storage adapter — `S3DocumentStorage` + Sig V4, select via `NENE_VAULT_STORAGE_ADAPTER=s3` (PR #72)
- [x] Optional email inbound (mailbox → auto-upload via IMAP/MIME parser) — `src/Email/` + `tools/email-inbound.php` (PR #74)
- [x] OCR assist — suggest metadata from PDF/image, human confirm (PR #76)

## Audit remediation 2026-07-11 — Done (one stop-gated follow-up)

Fleet structural-alignment audit remediation (#148 / #149 / #150), all merged
same day:

- [x] **#148 session posture** (PR #154) — token TTL 24 h → 1 h, sessionStorage
      (SPA + both demo seats), `PdoLoginThrottle` (5/15 min per email+IP, 429),
      `users.status = 'active'` check + timing equalization. Refresh-session
      upgrade (invoice ADR 0014 shape) filed as #153.
- [x] **#150 backend standardization** — JWT claims fleet schema `sub`/`org` +
      ClockInterface (#155/PR #156), NENE2 `BearerTokenMiddleware` blocklist +
      `PublicSurfaceBoundaryTest` (#157/PR #158), NENE2 → Packagist `^1.10` +
      release SHA-256 sidecar (#159/PR #160), UTC `created_at` write + UTC sweep
      parse + conversion migration `20260711000002` with 2-TZ acceptance
      (#161/PR #162), NENE2 health checks + symfony/uid (#163/PR #164).
- [x] **#149 frontend generation** (PR #165) — React 19 / react-router 7 /
      Vite 8 / TS 6 / Storybook 10 / Node >=22 (deal's version set); typed i18n
      `MessageKey` keeping ADR 0005 JSON as source (#166/PR #167); 401 in-place
      login via reactive auth gate (#168/PR #169).
- [ ] **Base-path / subdirectory deployment** (invoice ADR 0015 port) —
      stop-gated per the work order: Phase 0 estimate = 3 PRs; porting plan in
      **#170** (includes the deferred 403 hard redirect).

> Deploy note: apply migration `20260711000002` **together with** this code on
> the live demo host, before the next hourly sweep tick (docblock has details).

## Security assessment 2026-07-13 / 07-14 — Done (two follow-ups open)

The first live-fire security reports for this repository; prior security work
was the `docs/review/middleware-security.md` self-review checklist only. Both
rounds are **authorized self / maintainer-run** assessments run against a
disposable local Docker stack (`docs/security/harness/`) — not third-party
penetration tests. No production host (`vault.ayane.co.jp` or any live system)
was targeted, and no destructive/DoS payloads were used.

- [x] **Round 1 — black-box live ATK** (#194 / PR #195) —
      `docs/security/2026-07-13-assessment.md`. 48 live attack assertions across
      11 categories covering JWT verification, cross-org isolation, RBAC,
      upload/download, export, storage-path disclosure and the compliance hard
      rules: **0 Critical / 0 High / 0 Medium / 0 Low `EXPOSED`**. Response-surface
      hardening merged with the report.
- [x] **Round 2 — targeted red team** (#198 / PR #199) —
      `docs/security/2026-07-14-redteam-assessment.md`. Two vulnerability types
      that sibling **nene-records**' own 2026-07-13 self-assessment demonstrated
      as exploitable *there* — and which nene-records fixed the same week — were
      probed live here and are **absent** (all 11 admin GET endpoints return 401
      unauthenticated via fail-closed blocklist auth; no JWT cross-tenant read).
      Org binding hardened in depth against the sibling's root cause, and one
      round-1 probe gap corrected. **`EXPOSED 0`.**
- [ ] **#197 users repository org scope** — add `organization_id` at the
      repository layer as well (defence in depth).
- [ ] **#196 at-rest encryption of stored files** — application-layer
      (`Encryptor` equivalent), under consideration.

## Frontend standards alignment (fleet W1) 2026-07-14 → 07-16 — Done

Adoption of the fleet-wide frontend conventions. No product behaviour change.

- [x] **NENE2 client transport** (#204 / PR #205) — `shared/api/client.ts` runs on
      `@hideyukimori/nene2-client`.
- [x] **X-Authorization fallback retired** (#209 / PR #210) — the hand-rolled
      HETEML `Authorization`-stripping workaround (#118) replaced by the NENE2
      standard opt-in.
- [x] **Core Token Contract v1** (#206 / PR #207) — colour-vocabulary codemod
      (`nene2-tokens` VAULT_TABLE 1.0.0).
- [x] **Theme split to the convention shape** (#211 / PR #212) — the single
      2,005-line theme became token-only `default.css` (`@theme`) plus
      `default.components.css` (all rules under `@layer components`); the layer
      double-import was then removed (#213 / PR #214).
- [x] **Generated types wired to the entities** (#217 / PR #218) — after the
      response contract's `required` was corrected to match the runtime
      (#215 / PR #216).

## Frontend CSS standards, structure and gate (W-Spec / A1 / Lane1) 2026-07-16 → 07-17

Continuation of the fleet frontend sweep. No product behaviour change.

- [x] **W-Spec #236 — selector specificity rewrite** (PR #237) — the 48
      `selector-max-specificity` violations plus the 1 `!important` in
      `default.components.css` rewritten to the standard (ancestor-selector
      removal, variable lift, element-tag drop). Fleet core CSS rule violations
      are now **0** (`selector-max-specificity` 0 / `declaration-no-important` 0),
      so the gate can be wired green without a grandfather baseline.
- [x] **A1 — `hooks/` → `model/` relocation** (#240 / PR #241) — the feature
      `hooks/` directories moved to `model/` (codemod, directory move only).
- [ ] **Lane1 — stylelint gate wiring** (#238) — 🅿️ **parked** on the fleet
      central registry. The wiring (`.stylelintrc.json` extending
      `@hideyukimori/nene2-standards/stylelint` + the `check` chain) is prepared
      on branch `chore/238-stylelint-gate` (not merged, no PR). Real-wiring
      measurement: core rules 0/0 green, but `nene2/layer-components-allowlist`
      is red — **156** distinct component classes (409 instances) not yet in the
      central registry. That count matches the fleet's expected seed
      (`vault156`); resolving it is fleet#65's central-registry work, not a
      vault-side change (no rule-disable / exclude-glob workaround was applied).
      Restart when fleet#65 lands: re-measure → if green, prove red-on-mutation →
      revert → PR. Seed report:
      `_work/reports/2026-07-17-vault-components-allowlist-seed.md`.

Journals recording this burst: `docs/journal/2026-07-16.md`,
`docs/journal/2026-07-17.md` (#242 / PR #243).

## Demo operations, distribution and docs 2026-07-11 → 07-16 — Done

- [x] **Frontend fixes surfaced by the live demo** — change history refreshes
      after edit/void/restore (#172 / PR #176); export routed through the shared
      API client with raw `fetch` banned by lint (#173 / PR #177); retention
      warning shown live while typing (#175 / PR #178); authenticated download
      keyed by the version ULID rather than the ordinal (#179 / PR #180);
      role-gated rail nav with a Forbidden escape hatch (#174 / PR #181) and
      role-gated HomePage cards (#182 / PR #183).
- [x] **Demo analytics** (#184 / PR #185) — env-gated cookieless SPA-shell beacon
      plus a server-side entry log, later moved to a `var/` file sink so it is
      readable over SSH (#192 / PR #193).
- [x] **Installer** — the probe's `.env` read unified on phpdotenv, since a
      `parse_ini_file` warning turned a 403 into a 200 (#144); `install.php` and
      `installer.js` now self-delete once installation is detected, permanently
      preventing deploy-borne leftovers (#200 / PR #201).
- [x] **README** — static status/phase badges removed (#188 / PR #189) and raw PR
      ranges dropped with local ports centralized (#190 / PR #191). Maturity is
      stated by the Status table, not by badges.
- [x] **Workflow discipline made explicit** (#221 / PR #222) — the Issue-driven
      flow and the journal rule are restated in `CLAUDE.md` (the file a session
      reads first), with a drift-detection anchor in `AGENTS.md`. Journals:
      `docs/journal/2026-07-14.md`, `docs/journal/2026-07-16.md`.

## Go-live gate — Open 🔲

These are the only items between the current (complete, all-green) codebase and
production use by operators. Both come from Review 2's recorded conditions
(`docs/compliance-review/signoff-record.md`).

- [ ] **税理士 Review 3** — pre-production final verification of test-environment
      output samples (manifest CSV, export ZIP, retention dates) before any operator
      goes live. Engineering package: `docs/compliance-review/2026-review-3-preprod-package.md`.
- [ ] **Tier A live testing** — verify `install.php` + release ZIP on real shared
      hosting alongside sibling products (roadmap Phase 0/3 "pending Tier A testing").
- [ ] **Standing P0 watch** — on any 電帳法 amendment / 国税庁 guidance, open a P0
      Issue and add a new review block to `signoff-record.md` (Review 2 condition 2).

Last updated: 2026-07-17 (W-Spec #236 done via #237, A1 hooks→model #240, Lane1
#238 stylelint gate parked on fleet#65, 07-17 journal #242 recorded; #244)
