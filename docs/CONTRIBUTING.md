# Contributing

NeNe Vault is built through small, Issue-driven changes. This document is the shared entry point for humans and AI agents.

## Required Reading

> **Start with `docs/terms.md`.** It is the single source of truth for every
> canonical identifier spelling. Any identifier not in that file, or that differs
> from the registered form, blocks merge.

The following documents exist now and must be read before contributing:

| Topic | Document |
| --- | --- |
| **Canonical terms (binding)** | **`docs/terms.md`** ← single source of truth for all identifier spellings |
| Agent entry point | `AGENTS.md` |
| Scope contract (binding) | `docs/explanation/scope-contract.md` |
| Compliance (binding) | `docs/explanation/received-document-compliance.md` |
| Product vision | `docs/explanation/product-vision.md` |
| Requirements | `docs/explanation/requirements.md` |
| Sibling product boundaries | `docs/integrations/sibling-products.md` |
| Workflow | `docs/workflow.md` |
| Roadmap | `docs/roadmap.md` |
| Current work | `docs/todo/current.md` |

The following documents are also available:

| Topic | Document |
| --- | --- |
| NENE2 inheritance map | `docs/inheritance-from-nene2.md` |
| Coding standards | `docs/development/coding-standards.md` |
| Naming conventions | `docs/development/naming-conventions.md` |
| Backend standards (PHP/API) | `docs/development/backend-standards.md` |
| Commit conventions | `docs/development/commit-conventions.md` |
| ADR operation | `docs/development/adr.md` |
| Self-review policy | `docs/development/self-review.md` |
| Glossary | `docs/explanation/glossary.md` |
| Self-review checklists | `docs/review/` |
| AI tool configuration | `docs/integrations/ai-tools.md` (Phase 1+) |

## Collaboration Policy

Follow [`docs/workflow.md`](workflow.md) — inherited from [NENE2](https://github.com/hideyukiMORI/NENE2/blob/main/docs/workflow.md):

1. Create or reuse a GitHub Issue **before** editing.
2. Branch from `main` as `type/issue-number-summary`.
3. Implement, verify (`composer check` when applicable), commit with `(#issue)`.
4. Push, open PR with `Closes #number`, merge after checks — **do not push directly to `main`**.

- Use one branch and one PR per focused work unit.
- Keep `docs/milestones/`, `docs/roadmap.md`, and `docs/todo/current.md` updated when direction changes.
- Explain intent, impact, verification, and remaining risk in PRs.
- Prefer documentation that helps the next developer or AI agent decide what to do without rereading chat history.

## Secrets

Do not commit passwords, tokens, private URLs, production credentials, or local `.env` files. Commit only non-secret examples such as `.env.example` when needed.

Sensitive keys for this product include:

- Admin JWT secrets
- Storage path configuration (if it leaks internal directory structure)
- Optional bearer tokens for sibling link validation (Invoice API, Clear API)
- SMTP credentials if email inbound is implemented (Phase 3+)

## Engineering Theme

NeNe Vault should stay readable, secure, and self-hostable:

- Strict, typed, explicit boundaries (inherited from NENE2)
- Decoupled use cases and infrastructure
- OpenAPI contracts before client assumptions
- Received-document archive posture enforced at the API layer; no issuance,
  reconciliation, dunning, or CSV logic (ADR 0009)
- MCP access only through documented HTTP boundaries
- **Never** merge into or embed inside sibling products (ADR 0002)

## Upstream Framework

Runtime HTTP, middleware, and DI patterns come from [NENE2](https://github.com/hideyukiMORI/NENE2). When framework behavior is unclear, read NENE2 docs under `vendor/hideyukimori/nene2/docs/` after `composer install`.
