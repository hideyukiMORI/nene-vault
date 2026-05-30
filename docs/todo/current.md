# Current TODO

**Phase 1 — Document API complete; compliance review gate open.**

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

## In progress / gating

- [ ] **税理士 review gate** — package ready in `docs/compliance-review/`;
      awaiting professional sign-off in `signoff-record.md`. **Blocks Phase 2 UI.**

## Next (Phase 2, after sign-off)

- [ ] Admin UI scaffold — React + Vite + ja/en locale integration
- [ ] Export ZIP bundling (currently CSV only)
- [ ] Operator guide (storage, backup, retention, search) + 事務処理規程 template

## Blockers

- Phase 2 admin UI is gated on the compliance sign-off above.

Last updated: 2026-05-30
