# Commit Message Conventions

Inherited from [NENE2](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/commit-conventions.md). Uses Conventional Commits.

## Format

```text
<type>(<optional scope>): <description> (#<issue>)

[optional body]

[optional footer]
```

## Language

- Keep `type`, `scope`, `BREAKING CHANGE`, and other Conventional Commits keywords in English.
- Write the description and body in Japanese or English (Japanese preferred for maintainer commits; English for contributions expecting international review).
- Include the related GitHub Issue number in the subject when practical.

Example:

```text
docs(governance): NENE2 準拠のコーディング規約を追加する (#3)
```

## Types

| Type | Use |
| --- | --- |
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `refactor` | Code change without feature or bug fix |
| `test` | Test additions or changes |
| `build` | Dependency or build setup |
| `ci` | CI configuration |
| `chore` | Maintenance |

## Body

Use the body when the reason is not obvious from the subject. Explain why the change exists, what trade-off was chosen, and whether follow-up work remains.

## Breaking Changes

Use `!` or a `BREAKING CHANGE:` footer when public API, configuration, CLI, or documented behavior changes incompatibly.

## Scope examples (vault-specific)

| Scope | What it covers |
| --- | --- |
| `governance` | ADRs, compliance docs, scope-contract |
| `document` | vault_document domain |
| `retention` | Retention policy, ADR 0004 |
| `audit` | audit_event schema, ADR 0014 |
| `auth` | JWT, roles, capabilities |
| `storage` | File storage, document_version |
| `export` | Manifest CSV, ZIP export |
| `openapi` | OpenAPI contract |
| `migration` | Database migrations |
