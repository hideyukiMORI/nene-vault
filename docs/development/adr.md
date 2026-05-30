# ADR Operation Guide

Inherited from [NENE2](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/adr.md). Use ADRs for decisions that affect architecture, public contracts, compliance, dependency choices, or long-term maintenance.

## When to write an ADR

Write an ADR when:

- A decision affects the **public API contract** (endpoints, schemas, error types)
- A decision touches **compliance** (電帳法 rules, retention, integrity method)
- A decision affects **long-term maintenance** (storage adapter, auth method, DB choice)
- A decision involves a **rejected alternative** worth recording
- A future developer or AI agent would otherwise wonder "why was this done this way?"

Do **not** write an ADR for:

- Trivial implementation choices that follow existing patterns
- Decisions already captured in `received-document-compliance.md` or `scope-contract.md`
- Bugs or minor fixes

## File naming

```text
docs/adr/NNNN-kebab-title.md
```

Sequence: next available 4-digit zero-padded number. Current max: 0014.

## Template

Use `docs/adr/0000-template.md`. Required sections:

- **Status:** `proposed` | `accepted` | `rejected` | `superseded`
- **Context:** problem, constraints, trade-offs
- **Decision:** the choice, in clear direct language
- **Consequences:** benefits, costs, follow-up work

Optional: **Related** (links to other ADRs, compliance docs, Issues).

## Compliance ADRs

Any ADR that deviates from `docs/explanation/received-document-compliance.md` requires:

1. Professional sign-off by a licensed 税理士 or 公認会計士, recorded in the ADR.
2. The sign-off must include the professional's name, credential, and date.
3. No merge without the sign-off.

## Status lifecycle

```
proposed → accepted
         → rejected
         → superseded (by ADR NNNN)
```

When superseding an ADR, update the superseded ADR's status to `superseded by: ADR NNNN`.
