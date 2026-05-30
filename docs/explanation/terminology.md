# Terminology

> **Spelling authority moved.** The single source of truth for all canonical
> identifier spellings is **[`docs/terms.md`](../terms.md)**.
>
> This file retains legal and compliance vocabulary (Japanese statute names,
> technical compliance terms) for 士業 review. It is **not** the authority
> for code, database, or API spellings — `docs/terms.md` is.

---

## Legal and compliance terms (for 士業 review)

These terms appear in compliance docs, ADRs, and operator-facing materials.
Their canonical English rendering and Japanese label are registered here for
advisor traceability.

| Term | English rendering | Japanese | Usage context |
| --- | --- | --- | --- |
| Electronic Books and Records Preservation Act | 電子帳簿保存法 (abbreviated 電帳法) | 電子帳簿保存法 | Statutory basis; use full name at first mention, abbreviated thereafter |
| Electronic transaction | 電子取引 | 電子取引 | Category of received electronic documents under 電帳法 第7条 |
| Scanner preservation | スキャナ保存 | スキャナ保存 | Scanned-paper preservation; different requirements from 電子取引 |
| Integrity assurance | 真実性の確保 | 真実性の確保 | Legal requirement; Vault uses correction-history method |
| Visibility assurance | 可視性の確保 | 可視性の確保 | Legibility + search requirements under 電帳法 規則第4条 |
| Correction-history method | 訂正削除の履歴方式 | 訂正削除の履歴が残るシステム | Chosen integrity method for Vault (ADR 0011) |
| Search requirements | 検索要件 | 検索要件 | Date, amount, counterparty — individually and in combination |
| Retention period | 保存期間 | 保存期間 | Minimum years to retain; see ADR 0004 |
| Filing deadline | 申告期限 | 申告期限 | Tax return due date; the statutory anchor for the 7-year retention period |
| Operational procedures document | 事務処理規程 | 事務処理規程 | Written internal procedures required for some integrity methods |
| Tax audit | 税務調査 | 税務調査 | National Tax Agency inspection; Vault must support export for this |
| Received document | 受取書類 | 受取書類 | PDF/image received from a third party — Vault primary corpus |
| Issued document | 発行書類 | 発行書類 | Quote/invoice PDF from operator — NeNe Invoice SSOT |
| Void | 無効化 | 無効化 | Logical deactivation with audit; not hard delete |
| Document version | 文書バージョン | 文書バージョン | Immutable file blob; corrections create a new version |
| Transaction date | 取引年月日 | 取引年月日 | Date on received doc; distinct from upload date |

---

## Related

- Canonical spellings (all identifiers): [`../terms.md`](../terms.md)
- Definitions and meanings: [`./glossary.md`](./glossary.md)
- Naming patterns: [`../development/naming-conventions.md`](../development/naming-conventions.md)

Last updated: 2026-05-30
