# ADR 0007: Product Identity — NeNe Vault

## Status

accepted

## Context

The portfolio needs a distinct name and repo for received-document archive —
not "Invoice expansion" or "Clear attachment module."

## Decision

| Field | Value |
| --- | --- |
| **Public product name** | **NeNe Vault** |
| **Tagline (EN)** | Store received documents. Find them when it matters. |
| **Tagline (JA, marketing)** | 受取書類を、自分のサーバーに残す。 |
| **Domain** | Received-document archive (電子帳簿保存法-oriented storage & search) |
| **Canonical repo** | [`hideyukiMORI/nene-vault`](https://github.com/hideyukiMORI/nene-vault) (public) |
| **Framework** | NENE2 (PHP 8.4) |
| **License (when public)** | MIT |
| **PHP namespace** | `NeneVault\` |

## Consequences

- README, OpenAPI, MCP tools use "NeNe Vault" consistently.
- Do not market as a full bundled accounting suite — storage/search wedge only.

## Related

- [`../explanation/product-vision.md`](../explanation/product-vision.md)
- publication-strategy `docs/products/nene-vault.md`
