# ADR 0003: Dual-Tier Deployment — Shared Hosting (Tier A) and Docker (Tier B)

## Status

accepted

## Context

NeNe Vault targets Japan SMB operators who self-host back-office software rather
than subscribing to bundled cloud SaaS. Two infrastructure patterns dominate this
segment:

- **Shared hosting** (レンタルサーバー) — common among small operators, no root
  access, MySQL provided, PHP via web root, deploy via FTP/ZIP upload.
- **VPS / Docker** — used by developers and agencies running multiple NeNe
  products side-by-side on one server.

Optimising for only one tier excludes a significant part of the target audience.
A single codebase must support both without divergent maintenance.

Alternatives considered:

1. **Shared hosting only** — rejected; developers running Invoice + Clear + Vault
   together on a VPS get no first-class Docker support and cannot use Compose
   networking.
2. **Docker only** — rejected; non-developer operators on shared hosting cannot
   use Docker and would need a separate product or manual setup.
3. **Dual-tier with a single codebase** (chosen) — one PHP application packaged
   two ways; deployment differences handled by env vars and installer tooling,
   not by branching application logic.

## Decision

NeNe Vault ships as a **single PHP codebase** deployable in two official tiers:

### Tier A — Shared Hosting

- Release artifact: versioned **ZIP** containing pre-installed Composer
  dependencies (`vendor/` bundled).
- **Web installer** (`/install`) guides operator through database credentials,
  storage path, and initial superadmin setup; self-destructs after use.
- Database: **MySQL** (minimum version per NENE2 requirement).
- Storage root: local filesystem path configured during install; must be outside
  web root (`storage/vault/` by default).
- Target: operators who deploy via FTP/cPanel without shell access.

### Tier B — Docker / VPS

- **Docker Compose** configuration ships in the repository (`compose.yml`).
- No bundled `vendor/` — `composer install` runs at build time.
- Storage: mounted volume (`./storage/vault`).
- Can run beside `nene-invoice`, `nene-clear`, and `nene-profile` on one host
  with shared Compose network (optional; each product stays a separate container
  with its own database).
- Target: developers and agencies managing multiple NeNe products.

### Shared constraints (both tiers)

- **No third-party object storage in MVP** — local filesystem only; S3-compatible
  adapter is Phase 4+ (ADR 0012).
- **Env-var-driven configuration** — no tier-specific code paths in application
  logic; tier differences live in deployment tooling only.
- **Backup is operator responsibility** — docs mandate filesystem backup + DB
  backup together; Vault does not ship a backup scheduler.

## Consequences

**Benefits**

- Reaches both the non-developer SMB operator (Tier A) and the developer/agency
  audience (Tier B) from one codebase.
- Consistent with sibling product deployment model (Invoice, Clear ship Tier A
  + B).

**Costs**

- Release pipeline must produce two artifacts: ZIP (Tier A) and image/Compose
  config (Tier B).
- Web installer adds a security surface; must self-destruct and be covered by
  CI check.
- `vendor/` bundling for Tier A requires a separate build step.

**Follow-up**

- Phase 3: implement web installer and release ZIP build pipeline.
- Phase 4: `DocumentStorageInterface` adapter for S3-compatible backends.

## Related

- [`../explanation/scope-contract.md`](../explanation/scope-contract.md) D10
- ADR 0012: File storage architecture
- [`../explanation/product-vision.md`](../explanation/product-vision.md) — dual deployment section
- Supersedes: none
- Superseded by: none
