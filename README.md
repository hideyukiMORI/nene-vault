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

## Quick start (Docker)

```sh
composer install            # once, on the host — resolves the ../NENE2 path dependency
cp .env.example .env        # customise ADMIN_EMAIL / ADMIN_PASSWORD if desired
docker compose up           # SQLite (default) + Vite dev server
```

Then open:

| Service  | URL                     | Notes                                  |
|----------|-------------------------|----------------------------------------|
| Frontend | http://localhost:5186   | Vite dev server (proxies API to `app`) |
| API      | http://localhost:8600   | Apache + PHP 8.4                        |

First-run admin credentials come from `.env` (`ADMIN_EMAIL` / `ADMIN_PASSWORD`,
default `admin@example.com` / `changeme123`). A `default` organization, its
vault settings, and a superadmin user are seeded automatically.

**MySQL instead of SQLite:**

```sh
docker compose --profile mysql -f docker-compose.yml -f docker-compose.mysql.yml up
```

### Local port allocation (binding)

NeNe Vault runs alongside sibling products on the same developer machine, so its
host-published ports are **fixed in the "86 lane"** to never collide:

| Service          | NeNe Vault host port | Env var                     |
|------------------|----------------------|-----------------------------|
| API (Apache/PHP) | **8600**             | `NENE_VAULT_PORT`           |
| Frontend (Vite)  | **5186**             | `NENE_VAULT_FRONTEND_PORT`  |
| MySQL            | **3386**             | `NENE_VAULT_MYSQL_PORT`     |

Reserved by siblings — **do not reuse**:

| Product       | API   | Frontend / DB |
|---------------|-------|---------------|
| NENE2         | 82**  | 3316          |
| NeNe Clear    | 83**  | 5173          |
| NeNe Profile  | 84**  | 3409          |
| NeNe Invoice  | 85**  | 5185          |
| NeNe Records  | 180** | —             |

Container-internal ports (8080 / 5173 / 3306) are unchanged; only the host side
differs. If you must temporarily override (e.g. running two Vault checkouts),
set the env vars — but keep `.env` on the fixed allocation above:

```sh
NENE_VAULT_PORT=8601 NENE_VAULT_FRONTEND_PORT=5187 docker compose up
```

## Status

**Phase 1 (Document API) complete** — Auth, Organization, User, VaultSettings, Document upload/search/void/restore/history/download, Export CSV, Audit logging (PR #8–#26, 2026-05-30).
**Phase 2 (Admin UI) in progress** — React/Vite scaffold implemented; page-level UI under development. Docker development environment available.

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
