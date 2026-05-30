# Documentation Policy Self-Review Checklist

Use for: workflow changes, ADR additions, roadmap updates, Cursor rules, AGENTS.md, CONTRIBUTING.md.

---

## ADRs

- [ ] ADR file name: `docs/adr/NNNN-kebab-title.md`.
- [ ] Status is one of: `proposed`, `accepted`, `rejected`, `superseded`.
- [ ] Context, Decision, and Consequences sections are present.
- [ ] Related ADRs are linked.
- [ ] If the ADR deviates from `received-document-compliance.md`: professional sign-off is recorded.
- [ ] If the ADR supersedes another: the superseded ADR's status is updated.

## Terms registry

- [ ] **`docs/terms.md` is updated** when any new identifier is introduced or renamed — this is required, not optional.
- [ ] The new identifier does not appear in the "DO NOT use" column of any existing entry.
- [ ] The new identifier is consistent with the patterns in `docs/development/naming-conventions.md`.
- [ ] If it is a product concept with a meaning, `docs/explanation/glossary.md` is also updated.

## Compliance and governance docs

- [ ] Changes to `received-document-compliance.md` have a compliance review sign-off (§0.2).
- [ ] Changes to `scope-contract.md` have an ADR if they add to the DO or DON'T lists.
- [ ] `docs/explanation/terminology.md` is updated only for legal/compliance vocabulary — code spellings go in `docs/terms.md`.

## Roadmap and milestones

- [ ] `docs/roadmap.md` is updated if the change affects phases.
- [ ] `docs/milestones/` is updated if an acceptance criterion changes.
- [ ] `docs/todo/current.md` reflects current in-progress and next items.

## Language policy (ADR 0008)

- [ ] Repository docs (`README.md`, `AGENTS.md`, `CLAUDE.md`, `docs/`) are English.
- [ ] Japanese appears only in: UI locale catalogs, operator guides, or parenthetical statutory labels.
- [ ] OpenAPI descriptions and Problem Details metadata are English.

## UI strings / locales (ADR 0005)

- [ ] Any new operator-facing string was added to **both** `locales/ja.json` and `locales/en.json` with the same key.
- [ ] `composer locales` passes (identical key structure).
- [ ] No hard-coded display text was added to a component or handler.
- [ ] Statutory labels (取引年月日, 取引金額, 取引先名) stay Japanese in both locales.
- [ ] No third locale file was added (ja + en only).

## No third-party names (ADR 0013)

- [ ] No named commercial products in repository docs (use "bundled cloud accounting SaaS", etc.).

## Cursor rules

- [ ] `.cursor/rules/` summaries are short and accurate.
- [ ] No policy text is duplicated between `.cursor/rules/` and `docs/`.

## AGENTS.md / CONTRIBUTING.md

- [ ] New docs added in this PR are linked from `CONTRIBUTING.md` or `AGENTS.md` if appropriate.
- [ ] No references to non-existent files without a `(Phase N+)` note.

---

## Verification

Read the changed docs against the policies above. No automated command.
