# ADR 0006: Adopt Multi-Tenancy and Role Hierarchy as Foundational

## Status

accepted

## Context

Early product docs assumed Phase 1 was single-tenant, with multi-tenancy
deferred. In practice the product must support, from the foundation:

- agencies and hosting operators running **one install for multiple client
  organisations**, and
- a **role hierarchy** where a platform operator manages organisations and an
  organisation administrator manages that organisation's users and vault settings.

Sibling product NeNe Records already implements exactly this on NENE2
(per-request organisation resolution, `Role`/`Capability` enums,
`CapabilityMiddleware`, `organization_id` on tenant-scoped tables). Retrofitting
tenancy later would touch every table, repository, and route — far more costly
than building tenant-aware from day one.

Alternatives considered:

1. **Single-tenant first, retrofit later** — rejected; a later `organization_id`
   migration across all vault, document, and audit tables re-opens
   compliance-sensitive code (file storage integrity, audit trail, retention).
2. **Separate install per tenant only** — rejected; does not serve agencies and
   duplicates operations; the data model should still be tenant-aware.
3. **Multi-tenant foundation, mirroring NeNe Records** (chosen) — tenant-aware
   schema and middleware from the start; a single install may still run as one
   organisation via the `single` resolution mode.

## Decision

NeNe Vault is **multi-tenant from the foundation**, adopting the NeNe Records
tenancy architecture.

### Tenancy

- Every tenant-scoped table carries **`organization_id`**
  (`vault_settings`, `vault_documents`, `document_versions`, `document_links`,
  `audit_events`, `users`).
- The **organisation** (`organizations` table) is the tenant. Each organisation
  is an independent operator with its own `vault_settings` (retention years,
  storage path, optional sibling link configuration).
- Per-request **organisation resolution** runs in middleware before authorisation
  (mirroring `OrgResolverMiddleware`). Supported modes: **`single`** (default —
  one organisation per install), `path` (`/{org-slug}/…`), `subdomain`, and
  `custom_domain`. The resolved organisation id is held in a request-scoped holder
  and **every repository query is org-scoped**.

### Roles and capabilities

A `Role` enum and a `Capability` enum, resolved per route by a capability
resolver and enforced by `CapabilityMiddleware`:

| Role | Scope | Capabilities |
| --- | --- | --- |
| **`superadmin`** | Cross-tenant (platform operator) | All, **including `manage_organizations`**. `organization_id` may be `NULL`. |
| **`admin`** | Single organisation | All **except** `manage_organizations` — manages the org's **users**, **vault settings** (retention, storage path, sibling link config), and all document operations. |
| **`member`** | Single organisation | Upload documents (`upload_document`), edit own metadata (`edit_metadata`). Cannot manage users or settings. |
| **`viewer`** | Single organisation | Read-only (`view_documents`) — search, download. Optional; Phase 2+. |

Capabilities (vault-specific): `manage_organizations`, `manage_users`,
`manage_vault_settings`, `upload_document`, `edit_metadata`, `void_document`,
`view_documents`, `export_documents`.

- **Superadmin manages organisations** (`/admin/organizations` — create, list,
  disable tenants).
- **Admin manages users** within the organisation (`/admin/users`).
- Role and capability string values are registered in
  [`../explanation/terminology.md`](../explanation/terminology.md) (binding).

### Compliance interaction

Each organisation's documents, versions, links, and audit events are scoped to
that organisation; cross-tenant reads are prohibited. This does not relax any
rule in [`../explanation/received-document-compliance.md`](../explanation/received-document-compliance.md)
— immutability, retention, and audit apply **per organisation**.

## Consequences

**Benefits**

- Serves agencies/hosting operators and single SMBs from one codebase; `single`
  mode keeps the simple case simple.
- Avoids a high-risk tenancy retrofit across compliance-sensitive document tables.
- Consistent with the NeNe ecosystem; patterns and reviews transfer from
  NeNe Records.

**Costs**

- Every repository query must be org-scoped — a standing review item.
- Auth, org resolution, and RBAC are part of the runtime foundation (Issue #4),
  enlarging it beyond a bare health endpoint.

**Follow-up**

- **Issue #4 (expanded):** runtime foundation — org resolution + JWT auth +
  RBAC wiring + `GET /health`.
- **Issue #4+:** organisation CRUD (superadmin) and user CRUD (admin).

## Related

- Reference implementation: NeNe Records `src/Organization/`, `src/Auth/`
  (Role, Capability, CapabilityResolver, CapabilityMiddleware),
  `src/Organization/Resolution/`
- Requirements: [`../explanation/requirements.md`](../explanation/requirements.md)
- Domain model: [`../explanation/domain-model.md`](../explanation/domain-model.md)
- Terminology: [`../explanation/terminology.md`](../explanation/terminology.md)
- Compliance: [`../explanation/received-document-compliance.md`](../explanation/received-document-compliance.md)
- ADR 0009: [`./0009-separate-from-billing-and-reconciliation.md`](./0009-separate-from-billing-and-reconciliation.md)
- Supersedes: none
- Superseded by: none
