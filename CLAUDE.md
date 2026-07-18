# NeNe Vault — Claude Code Guide

**Received-document archive for Japan SMB (電子帳簿保存法).** Built on PHP 8.4 + NENE2.

## Start here

- **Scope (binding):** `docs/explanation/scope-contract.md` — what Vault does and does NOT do
- **Canonical terms:** `docs/terms.md` — single source of truth for all identifiers
- **Compliance (binding):** `docs/explanation/received-document-compliance.md`
- **Current work:** private `nene-origin/internal-docs/vault/todo/current.md` — the
  operational logs (todo/daily) moved to the private receptacle (P3); read there for
  the live status and handoff
- **Roadmap:** `docs/roadmap.md`
- **Full agent guide:** `AGENTS.md`

## Quick orientation

```
src/           PHP application (NeneVault\)
tests/         PHPUnit tests (mirrors src/)
frontend/      React + Vite admin UI
docs/          ADRs, OpenAPI, operator guide, MCP catalog
docs/mcp/      MCP tool catalog + integration guide
tools/         CLI scripts (MCP server, email inbound, release builder)
locales/       ja.json + en.json (single source of truth for all UI text)
```

## Commands

```bash
composer check          # test + PHPStan + CS + locales + openapi + mcp
npm run check --prefix frontend  # type-check + lint + test + build
composer mcp:server     # start MCP server (stdio JSON-RPC)
docker compose up       # local stack — API :8600 · Frontend :5186 · MySQL :3386
```

## Local ports (fixed, "86 lane")

API **8600** · Frontend **5186** · MySQL **3386** — unique across the NeNe
portfolio; never reuse sibling ports. See README "Local port allocation".

## Workflow

- **Issue-driven**: no Issue, no code or doc change. Branch `type/issue-number-summary`
  from `main`; **never commit directly to `main`**. Then PR. (`AGENTS.md` is the source
  of truth for the full operating rules.)
- **Journal**: record each working day in the private
  `nene-origin/internal-docs/vault/daily/YYYY-MM-DD.md` — English (ADR 0008); the daily
  record moved to the private receptacle (P3). Shape: headline title, lead paragraph, one
  `##` per topic, closing `## Carry-over`. Write mechanics: see the workspace
  daily-report convention (`_work/daily-report-convention.md`).

## Hard rules (never violate without an ADR)

- **No hard-delete** of documents during the retention window
- **No byte overwrite** of stored files — new version only
- **SHA-256** verified on every download
- **Every mutation** goes through `AuditRecorder` in the same DB transaction
- **Every repository query** on a tenant table must include `organization_id`
- **Storage path** must never appear in API responses
- **No invoice issuance, reconciliation, bank CSV, or expense workflows** — see `AGENTS.md`

## MCP tools (read + write)

The MCP server at `tools/local-mcp-server.php` exposes 9 tools.
See `docs/mcp/README.md` for setup and `docs/mcp/tools.json` for the catalog.
