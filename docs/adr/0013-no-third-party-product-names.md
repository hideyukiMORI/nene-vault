# ADR 0013: No Third-Party Product Names in Repository Documentation

## Status

accepted

## Context

Early Phase 0 drafts named specific commercial accounting and storage products
when describing the problem space. That creates trademark risk, reads as competitive
attack copy, and is unnecessary for engineering docs.

## Decision

**Repository documentation must not name third-party commercial products** unless
required by law or an explicit integration contract (none in Vault MVP).

Use generic descriptions instead:

| Avoid | Use |
| --- | --- |
| Named cloud accounting brands | bundled cloud accounting SaaS |
| Named storage modules | storage-only subscription add-ons |
| "X killer" framing | narrow scope wedge; storage + search only |

This applies to: `README.md`, `AGENTS.md`, all of `docs/`, ADRs, OpenAPI text,
Issues/PR templates in this repo.

**Outward marketing** (Zenn, Qiita, etc.) may name competitors only in separate
article assets per publication-strategy decision 0003 — not in this repository.

## Consequences

- Existing docs amended before public re-bootstrap (Issue #1).
- PR review rejects new competitor name mentions in repository docs.

## Related

- Issue: #1 (re-bootstrap)
- publication-strategy decision 0003 (outward article assets)
