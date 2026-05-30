# Locale Guide

NeNe Vault is a **bilingual product (ja + en only)** per
[ADR 0005](../adr/0005-ui-language-scope-ja-en.md). Every string shown to an
operator lives in a locale file — never hard-coded in a component or handler.

## Files

| File | Locale | Role |
| --- | --- | --- |
| `locales/ja.json` | `ja` | **Primary.** Japanese — the default UI language. |
| `locales/en.json` | `en` | **Secondary.** English for non-Japanese operators in Japan. |

No other locale files may be added (ADR 0005). A PR adding a third locale is
rejected with a pointer to that ADR.

## Single source of truth

These two files are the **only** place UI strings are defined. The frontend reads
the active locale file and looks up keys; it never contains literal display text.

## Key structure (binding)

Both files **MUST** have an identical key structure. A key present in one file but
missing in the other is a defect that **blocks merge**. This is enforced by:

```bash
composer locales        # runs tools/validate-locales.php
```

`composer check` runs this automatically. The validator ignores the `_meta` block
(which legitimately differs per locale).

## Top-level namespaces

| Namespace | Contains |
| --- | --- |
| `common` | Buttons, statuses, pagination, confirmation dialogs, table headers shared everywhere |
| `auth` | Login screen, logout, session, auth errors |
| `navigation` | Menu / sidebar labels |
| `organization` | Organization CRUD (list, form, plan/status enums, messages, errors) |
| `user` | User CRUD (form, role names, role descriptions, status, messages, errors) |
| `vault_settings` | Retention and sibling-link settings, retention warning |
| `document` | Upload, search, detail, void/restore, metadata, status/category/source enums |
| `audit_event` | Audit log table, action labels, entity-type labels |
| `export` | Export form and manifest column labels |
| `validation` | `code → message` map for field validation errors |
| `problem` | `problem-type → message` map for API Problem Details |
| `compliance` | Statutory warning dialogs (scanner, retention, date-uncertain) |

## Rules

1. **Statutory field labels stay Japanese in both locales.** 取引年月日, 取引金額,
   取引先名 appear in Japanese even in `en.json` — they are legally traceable
   terms (ADR 0005, ADR 0008). The English file may add a parenthetical gloss,
   e.g. `"取引年月日 (Transaction Date)"`.

2. **API stays English.** Problem Details responses from the API are English
   (ADR 0008). The `problem.*` keys translate those for display only — they do
   not change what the API returns.

3. **Enum values map by code.** Status, category, source, role, and audit-action
   values are looked up by their canonical code (from `docs/terms.md`). Example:
   `document.category.invoice_received`. The code is the key; the label is the value.

4. **Interpolation uses `{{name}}`.** Dynamic values use double-brace
   placeholders, e.g. `"Showing {{from}}–{{to}} of {{total}}"`. Keep placeholder
   names identical across both locales.

5. **Add to both files in the same PR.** Introducing a new string means adding the
   same key to `ja.json` and `en.json` together. `composer locales` will fail
   otherwise.

## Adding a new string

1. Choose the namespace and a descriptive snake_case key.
2. Add the key + Japanese value to `locales/ja.json`.
3. Add the same key + English value to `locales/en.json`.
4. If the string is an enum label, confirm the code matches `docs/terms.md`.
5. Run `composer locales` (or `composer check`).

## Relationship to docs/terms.md

`docs/terms.md` owns **identifier spellings** (DB columns, enum codes,
operationIds). `locales/*.json` owns **display text** keyed by those identifiers.
When you add a new enum value to `docs/terms.md`, add its label to both locale
files in the same PR.
