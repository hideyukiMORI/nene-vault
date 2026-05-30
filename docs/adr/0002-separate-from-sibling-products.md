# ADR 0002: Separate Product from Sibling NeNe Applications

## Status

accepted

## Context

NeNe Vault is a **received-document archive**. Siblings own billing, reconciliation,
and CSV normalization.

## Decision

- Independent repository and deployable unit.
- Optional HTTP reference links to Invoice/Clear — no required upstream.
- No shared database with any sibling.
- MCP tools map to Vault OpenAPI only.

## Related

- ADR 0009
- [`../integrations/sibling-products.md`](../integrations/sibling-products.md)
