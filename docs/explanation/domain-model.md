# Domain Model

NeNe Vault entities (MVP). All tenant-scoped tables include `organization_id`.

## vault_document

Logical document. Points to current `document_version_id`. Metadata fields:
`transaction_date`, `amount_cents` (nullable), `counterparty_name`, `category`, `tags`.

## document_version

Immutable file storage reference: `file_path`, `file_sha256`, `mime_type`, `original_filename`, `version_number`.

## document_link

Optional reference: `sibling_product` (`nene_invoice` | `nene_clear`), `entity_type`, `entity_id`.

## audit_event

`event_type`: `uploaded`, `metadata_changed`, `voided`, `restored`, `exported`.

## Related

- [`requirements.md`](./requirements.md)
- [`terminology.md`](./terminology.md)
