# Database Self-Review Checklist

Use for: migrations, repositories, schema changes, soft delete, audit_events.

---

## Migration

- [ ] Migration file name: `YYYYMMDDHHMMSS_snake_description.php`.
- [ ] Migration is in `database/migrations/`.
- [ ] Schema snapshot updated in `database/schema/{table}.sql`.
- [ ] Migration is reversible (`down()` method is correct).
- [ ] New tenant-scoped tables include `organization_id NOT NULL`.
- [ ] Retention-related tables include `retention_expires_at DATE NOT NULL` where applicable.
- [ ] `audit_events` table has no soft-delete column — append only.
- [ ] Money columns use `INT` (not DECIMAL or FLOAT) with `*_cents` suffix and allow `NULL` where the value is optional.
- [ ] Hash columns use `VARCHAR(64)` with `*_sha256` suffix.
- [ ] Status columns use ENUM matching terminology.md values.

## Repository

- [ ] All SQL is inside `Pdo*Repository` classes only — none in UseCase, Handler, or Domain.
- [ ] Every query on a tenant-scoped table includes `WHERE organization_id = ?`.
- [ ] No cross-tenant reads (cross-tenant is superadmin only, enforced by middleware context).
- [ ] Repository returns domain objects or primitives — not raw PDO result rows.
- [ ] Row values are cast to typed PHP values on the way out of the repository.
- [ ] `findById` (and similar) returns `null` for "not found" — does not throw.
- [ ] `save` / `insert` returns the new ID.
- [ ] `DatabaseQueryExecutorInterface` from NENE2 is used — not raw PDO.

## Soft delete / void

- [ ] Vault documents use `status = 'voided'` + `voided_at` + `voided_by` + `void_reason` — not `is_deleted`.
- [ ] Default search queries exclude `status = 'voided'` unless `include_voided = true` is requested.
- [ ] Voided documents are still subject to retention enforcement.

## Audit events

- [ ] `audit_events` is append-only; no UPDATE or DELETE SQL on this table.
- [ ] Every `INSERT INTO audit_events` is in the same transaction as the mutation it records.
- [ ] `before_json` and `after_json` do not contain secrets (passwords, tokens, file paths).
- [ ] DB user has INSERT on `audit_events`, NOT UPDATE or DELETE (verify in migration or setup doc).

## Indexes

- [ ] Indexes are added on columns used in WHERE clauses (especially `organization_id`, `transaction_date`, `counterparty_name`).
- [ ] Index names follow `idx_{table}_{columns}` convention.
- [ ] Unique constraints follow `uniq_{table}_{columns}` convention.

---

## Verification

```bash
composer check
composer migrations:migrate
```
