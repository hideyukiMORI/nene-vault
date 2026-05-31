# Operator Guide — NeNe Vault

This directory covers everything an operator needs to run NeNe Vault in production.

## Contents

| Document | Purpose |
|---|---|
| [installation.md](./installation.md) | Docker Compose setup, environment variables, first run |
| [storage.md](./storage.md) | File storage layout, disk requirements, path configuration |
| [backup.md](./backup.md) | Database and file backup strategy |
| [retention.md](./retention.md) | Retention years, per-organization override, deletion eligibility |
| [search.md](./search.md) | 電帳法 search fields, UI walkthrough, quick-search guide |
| [export.md](./export.md) | CSV/ZIP export, tax audit response (電帳法 §10) |
| [jimu-shokirei-template.md](./jimu-shokirei-template.md) | 事務処理規程 template (customize and adopt) |

## Compliance note

NeNe Vault stores received documents under the
**電子帳簿保存法（電帳法）スキャナ保存・電子取引保存** provisions. Before going
live, operators must:

1. Adopt the **事務処理規程** in `jimu-shokirei-template.md` (or an equivalent
   internal policy).
2. Obtain a **税理士 / 公認会計士 sign-off** on the compliance posture — see
   `docs/compliance-review/signoff-record.md`.
3. Confirm the retention period is ≥ 7 years (default is 10; see
   `retention.md`).

See `docs/explanation/received-document-compliance.md` for the full binding
compliance specification.
