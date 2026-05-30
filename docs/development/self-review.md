# Self-Review Checklist Policy

NeNe Vault uses self-review checklists before push or PR, inherited from [NENE2](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/self-review.md).

## How to use

1. Identify the work type.
2. Open the matching checklist in `docs/review/`.
3. Review every applicable item.
4. Run the narrowest useful verification.
5. Mention the checklist(s) used in the PR body.

Example:

```text
Self-review: compliance, backend-api, database
```

If an item is not applicable, mark it mentally as `N/A`. Do not delete checklist items to pass review.

## Checklist files

| File | Use for |
| --- | --- |
| `compliance.md` | **Binding.** Any change touching document storage, file serving, search, metadata, audit, retention, void/restore, or export |
| `backend-api.md` | Endpoints, handlers, validation, HTTP behavior |
| `openapi-contract.md` | OpenAPI schemas, examples, contract tests |
| `database.md` | Migrations, repositories, soft delete, audit_events |
| `middleware-security.md` | Auth, JWT, CORS, org scoping, rate limits |
| `docs-policy.md` | Workflow, ADRs, roadmap, Cursor rules |
| `frontend.md` | **Phase 2.** Admin React/TypeScript — placement, data flow, styling tokens, i18n, security, testing |

Use `frontend.md` for any change under `frontend/`. Full rules: `docs/development/frontend-standards.md`.

## AI agents

Pick relevant checklists before finalizing changes. Do not claim an item passed if it was not checked.

The **compliance checklist is binding** — any change in its scope without a compliance review is a P0 defect.

If no checklist matches, use `docs/workflow.md`, `docs/development/coding-standards.md`, and `docs/inheritance-from-nene2.md` directly.
