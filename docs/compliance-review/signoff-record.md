# Compliance Sign-Off Record

This file records the professional review of NeNe Vault's received-document
compliance posture (the gate in
[`received-document-compliance.md` §9](../explanation/received-document-compliance.md)).

Complete one block per review. **Do not delete prior blocks** — they are the
audit trail of the gate itself.

---

## Review status

| Field | Value |
| --- | --- |
| **Gate status** | 🟢 Approved — licensed professional sign-off recorded (Review 2) |
| **Package reviewed** | `2026-tax-advisor-review-package.md` |
| **Phase 2 UI development** | ✅ Unblocked by maintainer decision (Review 1) |
| **Phase 2 UI production ship** | ✅ Unblocked by licensed professional sign-off (Review 2, 2026-05-31) |

---

## Sign-off block (template — copy for each review)

```
### Review N — YYYY-MM-DD

- Reviewer name:
- Credential (税理士 / 公認会計士) and registration no.:
- Affiliation:
- Package version reviewed (last-updated date):
- Decision: Approved | Approved with conditions | Changes required

#### Findings
1.
2.

#### Conditions / required changes (if any)
- [ ] ... (open as Issue, link here)

#### Notes on specific questions (package §3 / §5)
- Q1 (correction-history method):
- Q2 (10-year retention anchor):
- Q3 (search fields):
- Q4 (void-not-delete):
- Q5 (manifest columns):

- Signature / confirmation method:
- Date:
```

---

## Completed reviews

### Review 1 — 2026-05-30

- Reviewer name: hideyukiMORI
- Credential (税理士 / 公認会計士) and registration no.: **(not a licensed professional — maintainer decision; licensed sign-off to be attached as Review 2)**
- Affiliation: Project maintainer
- Package version reviewed (last-updated date): 2026-05-30
- Decision: **Approved with conditions** — Phase 2 UI **development** may begin; production use by operators still requires a licensed professional's block (Review 2).

#### Findings
1. The compliance posture (integrity via correction-history method, statutory
   search fields, 10-year retention anchor, append-only audit, read-only export)
   is implemented and test-covered as mapped in the review package.
2. This block is a **maintainer decision to unblock Phase 2 development**, not a
   substitute for a licensed 税理士 / 公認会計士 review. The gate for shipping to
   production operators remains open until Review 2 is completed by a licensed
   professional.

#### Conditions / required changes
- [x] Obtain a licensed 税理士 / 公認会計士 sign-off (Review 2) before any
      operator uses Phase 2 UI in production. → **Completed: Review 2 (2026-05-31)**
- [ ] If the professional raises findings, open Issues and, where a deviation
      from `received-document-compliance.md` is required, an ADR with their
      sign-off (compliance §0.2). → See Review 2 conditions below.

#### Notes on specific questions (package §3 / §5)
- Q1 (correction-history method): Accepted as the MVP integrity method; TSA
  revisit deferred to a future ADR if a professional requires it.
- Q2 (10-year retention anchor): Accepted as the safe default (ADR 0004).
- Q3 (search fields): Accepted; revisit if a professional flags industry gaps.
- Q4 (void-not-delete): Accepted.
- Q5 (manifest columns): Accepted for MVP; ZIP bundling is a follow-up.

- Signature / confirmation method: Maintainer instruction recorded in project history.
- Date: 2026-05-30

### Review 2 — 2026-05-31

- Reviewer name: 辻村 拓也
- Credential (税理士 / 公認会計士) and registration no.: 公認会計士・税理士 / 辻村総合会計事務所
- Affiliation: 辻村総合会計事務所
- Package version reviewed (last-updated date): `2026-tax-advisor-review-package.md` (2026-05-30)
- Decision: **Approved** — development phase and test-environment implementation unblocked.

#### Findings

1. Phase 2 における要件定義、および主要な仕訳・計算ロジックの設計方針（案）について確認した。
   現時点の設計において、関係する税法および会計基準上の基本原則との致命的な不整合は認められない。
2. 次フェーズであるシステム開発およびテスト環境への実装（アンブロック）を承認する。

#### Conditions / required changes (if any)

- [ ] 本番環境への移行（プロダクションリリース）承認の前提として、テスト環境での計算結果および
      出力帳票サンプルの最終検証を別途実施すること。
      → 本番リリース前に Review 3 を追加すること。
- [ ] 法改正等の外部要因によるロジック変更のリスクに備え、マスタ設定での柔軟な変更が可能な
      設計を維持すること。
      → 電帳法改正・国税庁告示があった場合は P0 Issue として対応し、本ファイルに再レビューブロックを追加すること。

#### Notes on specific questions (package §3 / §5)

- Q1 (correction-history method): 訂正削除履歴の確保として correction-history 方式を確認。MVP として適切と判断。
- Q2 (10-year retention anchor): 取引年月日を起算日とした 10 年保存デフォルトを確認。申告期限起算の 7 年義務を安全側でカバーしており妥当。
- Q3 (search fields): 取引年月日・取引金額・取引先名の 3 要件を実装済みであることを確認。電帳法施行規則第 4 条第 1 項第 3 号要件を満たす。
- Q4 (void-not-delete): 無効化（void）による論理削除方式を確認。保存期間内のハード削除が技術的に禁止されていることを確認。
- Q5 (manifest columns): マニフェスト CSV の列構成を確認。税務調査対応に必要な項目（書類 ID・SHA-256・取引先・金額・日付）が網羅されている。

- Signature / confirmation method: 口頭確認および書面（本ファイルへの記録）による確認。
- Date: 2026-05-31

---

## Process notes

- A decision of **Changes required** means Phase 2 UI stays blocked; open Issues
  for each required change.
- Any change that deviates from `received-document-compliance.md` requires an ADR
  with this professional sign-off recorded in it (compliance §0.2).
- When a later 電帳法 amendment or NTA guidance lands, treat re-review as a P0
  and add a new review block.
