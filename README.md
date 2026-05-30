# NeNe Vault

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://www.php.net/)
[![Public](https://img.shields.io/badge/status-public-blue)]()

**Received-document archive — self-hosted for Japan SMB.**

**NeNe Vault** stores, searches, and preserves **received** business documents
(invoices, contracts, receipts from vendors) for **電子帳簿保存法** compliance —
without becoming accounting software or expense workflow. Built on
[NENE2](https://github.com/hideyukiMORI/NENE2), shared hosting or Docker.

> **Separate product.** Vault does **not** issue quotes/invoices ([`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice)),
> reconcile bank deposits ([`nene-clear`](https://github.com/hideyukiMORI/nene-clear)),
> or normalize bank CSV ([`nene-profile`](https://github.com/hideyukiMORI/nene-profile)).
> See [ADR 0009](./docs/adr/0009-separate-from-billing-and-reconciliation.md).

## Domain (binding)

| Product | Repository | What it does |
| --- | --- | --- |
| **NeNe Invoice** | `nene-invoice` | Quote, invoice, payment management — 見積・請求・入金管理 |
| **NeNe Clear** | `nene-clear` | Payment reconciliation & dunning — 入金消込・督促管理 |
| **NeNe Profile** | `nene-profile` | Bank CSV column mapping & normalization |
| **NeNe Vault** | `nene-vault` (this) | Received-document archive — 受取書類の保存・検索 |

## Goals

- **Store received documents** — PDF/image upload, metadata, immutable audit trail
- **Search (検索要件)** — date, amount, counterparty; combinations for 電帳法 visibility
- **Retention** — 7–10 year policy; no silent purge
- **Compliance as structure** — binding rules in [`received-document-compliance.md`](./docs/explanation/received-document-compliance.md)
- **Self-hosted OSS** — MIT; Tier A shared hosting or Tier B Docker/VPS
- **Optional links** — HTTP reference to Invoice/Clear entities; **no shared DB**
- **No third-party product names** in repository docs — [ADR 0013](./docs/adr/0013-no-third-party-product-names.md)
- **UI: Japanese + English only** — [ADR 0005](./docs/adr/0005-ui-language-scope-ja-en.md)

## Documentation (read first)

| Topic | Document |
| --- | --- |
| **Scope contract (GOAL / DO / DON'T)** | [`docs/explanation/scope-contract.md`](./docs/explanation/scope-contract.md) |
| **Domain boundary** | [`docs/explanation/scope-boundary.md`](./docs/explanation/scope-boundary.md) |
| **Product vision** | [`docs/explanation/product-vision.md`](./docs/explanation/product-vision.md) |
| **Requirements** | [`docs/explanation/requirements.md`](./docs/explanation/requirements.md) |
| **Compliance (binding)** | [`docs/explanation/received-document-compliance.md`](./docs/explanation/received-document-compliance.md) |
| **Sibling integration** | [`docs/integrations/sibling-products.md`](./docs/integrations/sibling-products.md) |
| **Agents** | [`AGENTS.md`](./AGENTS.md) |
| **Roadmap** | [`docs/roadmap.md`](./docs/roadmap.md) |

## Status

**Phase 0 complete** — governance and product design done (PR #1, #2 merged 2026-05-30).
Phase 1 runtime scaffold follows Issues #4+.

## Ecosystem

```
NENE2 (framework)
  ├── NeNe Invoice   (issued billing documents)
  ├── NeNe Clear     (reconciliation · dunning)
  ├── NeNe Profile   (bank CSV normalization)
  └── NeNe Vault     (received-document archive)  ← this repo
```

## License

MIT — see [LICENSE](./LICENSE).
