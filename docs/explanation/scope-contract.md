# Scope Contract — GOAL / DO / DON'T (binding)

**Status: binding (non-negotiable).** Charter for what NeNe Vault **is**, what it
**does**, and what it **must never do**. Every Issue, ADR, and PR is measured
against it. Changing this contract requires an ADR (and, where a row cites law,
professional sign-off).

> Engineering interpretation of 電子帳簿保存法 for **received** documents — **not
> legal advice**. Confirm applicable requirements with a 税理士 before production
> reliance.

Read first: [ADR 0009](../adr/0009-separate-from-billing-and-reconciliation.md),
[`received-document-compliance.md`](./received-document-compliance.md),
[`scope-boundary.md`](./scope-boundary.md).

---

## GOAL

> **NeNe Vault lets a Japan SMB store, search, and preserve received business
> documents electronically — so they can drop **storage-only SaaS add-ons**
> without losing 電子帳簿保存法 visibility or auditability.**

Concretely, the goal is reached when an operator can:

1. Upload or ingest a **received** PDF/image (vendor invoice, contract, receipt).
2. Attach **metadata**: transaction date, amount (if present), counterparty, category tag.
3. **Search** by date range, amount range, and counterparty (and combinations).
4. See **correction/deletion history** — no silent in-place edits of stored files.
5. Retain documents for **7–10 years** without auto-purge.
6. Optionally **link** a vault entry to an Invoice or Clear entity via HTTP reference ID.
7. Hand a tax advisor an export manifest that maps each file to metadata and history.

A reviewing 税理士 is the real acceptance test: **received 証憑 are findable,
immutable, and traceable** — Vault does not pretend to be the ledger.

---

## DO — Vault owns these

| # | Vault does | Grounded in |
| --- | --- | --- |
| D1 | Store **received** electronic documents (PDF, JPEG, PNG) as immutable files | 電子帳簿保存法 (電子取引データ) |
| D2 | Preserve **訂正削除の履歴** for metadata and file lifecycle (void/replace, not silent edit) | 真実性の確保 |
| D3 | Provide **search** by transaction date, amount, counterparty (combinations) | 可視性の確保 · 検索要件 |
| D4 | Record **provenance**: who uploaded, when, source (upload/email/API), file hash | Audit / integrity |
| D5 | Enforce **retention** policy (7 years minimum; 10 where applicable); block auto-delete | 帳簿保存 |
| D6 | Support **export** (ZIP + manifest CSV) for advisor handoff | Operational |
| D7 | Allow **optional HTTP links** to Invoice/Clear entities (reference only, not SSOT) | ADR 0002 |
| D8 | Multi-tenant isolation, RBAC, audit events for upload/void/metadata change | ADR 0006 |
| D9 | Admin UI + REST API + MCP (read-heavy; write with auth) | NENE2 inheritance |
| D10 | Tier A installer + release ZIP beside other NeNe back-office apps | ADR 0003 |

---

## DON'T — Vault must never do these

| # | Vault must NOT | Why (risk) | Belongs to |
| --- | --- | --- | --- |
| X1 | Issue quotes, invoices, qualified-invoice PDFs, or receipts **to customers** | Document issuance is billing | **NeNe Invoice** |
| X2 | Reconcile bank deposits or send dunning notices | Reconciliation domain | **NeNe Clear** |
| X3 | Parse/map bank CSV columns or ship bank-format presets | Normalization engine | **NeNe Profile** |
| X4 | Post journal entries (仕訳) or maintain a general ledger | Vault is evidence store, not ledger | Accounting software |
| X5 | Run **expense reimbursement** approval workflows (申請→承認→精算) | Different product (Petty/Expense) | Future separate product |
| X6 | OCR-as-truth without human review flag for statutory fields | OCR errors → wrong evidence | Operator + optional OCR assist only |
| X7 | **Hard-delete** stored files or metadata without void/replace audit | Destroys 電帳法 evidence | — (void/replace only) |
| X8 | **In-place overwrite** of file bytes | Breaks integrity | — (new version + history) |
| X9 | Share a database with Invoice, Clear, or Profile | Couples schemas | HTTP only (ADR 0002) |
| X10 | Become a **full bundled accounting suite** substitute | Scope explosion | — |
| X11 | Store **issued** outbound documents as SSOT (operator may archive copies, but Invoice remains SSOT for issued docs) | Two truths for same invoice | **NeNe Invoice** |
| X12 | Auto-classify tax treatment or consumption-tax basis | Tax judgment | Operator + 税理士 |

---

## Boundaries that are easy to get wrong

- **Received vs issued.** Vault primary domain is **受取** (inbound from vendors).
  Operators may attach a **copy** of an invoice they issued via Invoice, but
  **NeNe Invoice remains SSOT** for issued billing data (X11).
- **Storage vs workflow.** Vault stores and finds documents. It does **not** replace
  expense approval (X5) or bank matching (X2).
- **Storage wedge, honest marketing.** Vault replaces **storage + search +
  retention** subscription slices — not accounting, payroll, or e-invoice issuance.

---

## Definition of done (rules layer)

- [ ] This contract, compliance doc, and sibling integration docs agree (no conflicts).
- [ ] Every DON'T has statute/ADR citation or explicit owner product.
- [ ] 税理士 review of received-document retention/search posture (before Phase 2 UI).
- [ ] `terminology.md` registers every entity the rules reference.

---

## Related

- Compliance: [`received-document-compliance.md`](./received-document-compliance.md)
- Boundary: [`scope-boundary.md`](./scope-boundary.md)
- ADR 0009: Domain split
- ADR 0010: Received-only posture
- ADR 0011: Electronic-records method (correction history)
- ADR 0012: File storage architecture

Last updated: 2026-05-29
