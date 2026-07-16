# Timezone-to-UTC Migration Plan (#228)

**Status: DESIGN ONLY. Nothing here has been executed.** The migration described
below is **not** committed as a runnable Phinx file, on purpose: a file under
`database/migrations/` runs on the next `phinx migrate` (CI, a laptop, a deploy),
and this touches real data on the live demo host (`vault.ayane.co.jp`). The
schema-code block in §4 is the skeleton to lift into a real migration **when the
owner gives a post-launch execution GO** — not before.

This plan is the design artefact requested for Issue #228 (audit trail written in
UTC, the documents it audits written in host-local time). It supersedes nothing;
it is the "how" that #228's execution GO will follow.

---

## 1. The problem, precisely

One database, two timezone conventions. `audit_events` and `organizations` are
written in **UTC** (`ClockInterface => UtcClock`, `AuditServiceProvider.php:28`).
The core document tables are written in **host-local time** — JST on the live
demo host — because their repositories call bare `date()`.

Nothing is breaking today: no time-based reaping path touches documents, and the
one sweep that does time comparisons (`sweep-demo.php`) reads only
`organizations`, already UTC. The defect is latent and correctness-of-record:
for a 電子帳簿保存法 archive whose hard rule is *"every mutation goes through
`AuditRecorder`"*, the audit trail (UTC) and the act it records (JST) disagree by
the UTC offset — nine hours on JST.

This plan converts the host-local columns to UTC and aligns the writers, using
the same DST-correct shift the `organizations` conversion already used
(`20260711000002`, #161).

---

## 2. Column classification — the core of the design

Not every timestamp column may be shifted. #161 was simple because
`organizations.created_at/updated_at` are pure wall-clock `datetime`. Here the
columns fall into three groups, and **two of them must be left alone.**

### 2a. SHIFT — wall-clock `datetime` written host-local

| Column | Writer (host-local) |
| --- | --- |
| `users.created_at` | `PdoUserRepository.php:71` `date()` |
| `users.updated_at` | `PdoUserRepository.php:88,95,103,111` `date()` **and** SQL `NOW()` at `:119,137,145,163` — see §3 |
| `users.invite_expires_at` | `PdoUserRepository.php:120` `date('Y-m-d H:i:s', $ts)` |
| `users.password_reset_expires_at` | `PdoUserRepository.php:146` same |
| `vault_settings.updated_at` | `PdoVaultSettingsRepository.php:34,54` `date()` |
| `vault_documents.uploaded_at` | `UploadDocumentUseCase.php:87` `date()` (fallback `PdoVaultDocumentRepository.php:47`) |
| `vault_documents.voided_at` | `PdoVaultDocumentRepository.php:110` `date()` |
| `document_versions.uploaded_at` | `UploadDocumentUseCase.php:87` `$now` (fallback `PdoDocumentVersionRepository.php:41`) |

All eight have **no time-comparing reader** today (verified: invite/reset expiry
checks are unwired — impl called only from tests; no document-retention sweep
exists). So the shift's real-world risk is low; we shift for record correctness,
not to fix a live failure. `invite_expires_at` / `password_reset_expires_at` are
future-instant `datetime`s rather than wall-clock records, but shifting them
keeps them consistent with the writer once it moves to UTC, and their readers are
unwired — safe to include.

### 2b. DO NOT SHIFT — `date`-typed business/derived dates

| Column | Why not |
| --- | --- |
| `vault_documents.transaction_date` (`date`) | **User-entered business data** — the request-body `transaction_date` (`UploadDocumentHandler.php:55-58`, `YYYY-MM-DD` validated). A calendar day has no timezone; shifting it by an offset would corrupt 取引年月日 and move rows across the day boundary in the date-range filter (`PdoVaultDocumentRepository.php:177,182`). |
| `vault_documents.retention_expires_at` (`date`) | **Derived calendar day.** `UploadDocumentUseCase.php:73-74`: `anchor = transaction_date ?? today`, then `anchor + retention_years` (default 10, `:24`). A `date` with no time. Shifting it would desynchronise it from `transaction_date`, its own anchor. Its only reader is a business date presenter; the retention sweep that would compare it does not exist (index `idx_org_retention` is unused). |

`retention_expires_at` has a **separate, real defect** that a shift cannot fix
and must not try to: when `transaction_date` is absent, its anchor is
`date('Y-m-d')` = **today in JST**, so a document uploaded in the JST-evening /
UTC-previous-day window anchors to the wrong calendar day. That is a **code**
fix (compute the anchor from `ClockInterface`), not a data shift. See §6.

### 2c. ALREADY UTC / OUT OF SCOPE

| Column | Note |
| --- | --- |
| `login_attempts.window_started_at`, `locked_until` | Written by `PdoLoginThrottle` via `clock->now()` = **UTC**, and its readers compare in UTC (self-consistent, `:42,68`). Shifting these would **re-break** them exactly as `20260711000002`'s docstring warns. **Exclude the whole table**; its rows are short-lived and self-expire. |
| `login_attempts.created_at` | Never written (INSERT omits it; stays NULL). |
| `audit_events.*`, `organizations.*` | Already UTC (`UtcClock`; organizations migrated by `20260711000002`). |

---

## 3. The `users.updated_at` trap

`users.updated_at` has **two writers in different timezones**:

- `date()` (host-local / JST) at `PdoUserRepository.php:88,95,103,111`
- SQL `NOW()` (the **DB server's** timezone, not necessarily the PHP host's) at
  `:119,137,145,163`

Per row, **which timezone produced the stored value is not recoverable.** A blind
JST→UTC shift is therefore wrong for any row last written by `NOW()` if the DB
server's zone differs from PHP's.

**Consequence for sequencing:** the code must be unified to a single UTC writer
**before or with** the migration, so that (a) all *future* writes are UTC and
(b) the migration's assumption "existing rows are host-local" holds for the
`date()`-written rows. On the live demo host, MySQL runs in the same JST default
as PHP (both host-default), so in practice `NOW()` and `date()` agree there today
— but the migration must not *rely* on that; it must be applied on a host where
PHP and DB share the zone, and the code unification removes the ambiguity going
forward. Record the DB `@@session.time_zone` at migration time in the runbook.

---

## 4. Migration skeleton (DESIGN — do not commit as a runnable file yet)

Same DST-correct `DateTimeImmutable` shift as `20260711000002`, generalised over
the shift-list. Unparseable values are left untouched rather than corrupted
(inherited from #161). `down()` is the symmetric reverse, so the migration is
reversible on a zone-stable host.

```php
<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * #228: convert host-local (JST on the live demo host) wall-clock timestamps to
 * UTC, matching organizations (#161, 20260711000002). ONLY the columns listed in
 * $targets — transaction_date / retention_expires_at (date-typed business dates)
 * and login_attempts (already UTC) are deliberately excluded.
 *
 * DEPLOY ORDER MATTERS (see §5): apply together with the ClockInterface code
 * switch, on a host where PHP and MySQL share the same default timezone. A row
 * already written in UTC by the new code and then shifted here would move by the
 * UTC offset. No-op on a UTC host.
 */
final class ConvertCoreTimestampsToUtc extends AbstractMigration
{
    /**
     * Per-table: primary key, its type, and the datetime columns to shift.
     * The PK differs by table and MUST NOT be assumed (verified from the create
     * migrations):
     *   - users             PK id              (int)
     *   - vault_settings    PK organization_id (int)   -- no `id` column
     *   - vault_documents   PK id              (char/ULID, string)
     *   - document_versions PK id              (char/ULID, string)
     * A `(int)` cast on a ULID collapses to 0, so every row would match the same
     * WHERE and the table would be corrupted — the PK type drives quoting below.
     * date-typed & already-UTC columns are deliberately absent.
     */
    private const TARGETS = [
        'users'             => ['pk' => 'id',              'pkString' => false, 'cols' => ['created_at', 'updated_at', 'invite_expires_at', 'password_reset_expires_at']],
        'vault_settings'    => ['pk' => 'organization_id', 'pkString' => false, 'cols' => ['updated_at']],
        'vault_documents'   => ['pk' => 'id',              'pkString' => true,  'cols' => ['uploaded_at', 'voided_at']],
        'document_versions' => ['pk' => 'id',              'pkString' => true,  'cols' => ['uploaded_at']],
    ];

    public function up(): void   { $this->convertAll(date_default_timezone_get(), 'UTC'); }
    public function down(): void { $this->convertAll('UTC', date_default_timezone_get()); }

    private function convertAll(string $fromTz, string $toTz): void
    {
        if ($fromTz === $toTz) {
            return; // UTC host: no-op
        }
        $from = new DateTimeZone($fromTz);
        $to   = new DateTimeZone($toTz);

        foreach (self::TARGETS as $table => $spec) {
            $pk      = $spec['pk'];
            $columns = $spec['cols'];
            $rows    = $this->fetchAll(sprintf('SELECT %s, %s FROM %s', $pk, implode(', ', $columns), $table));

            foreach ($rows as $row) {
                $sets = [];
                foreach ($columns as $col) {
                    if ($row[$col] === null) {
                        continue; // NULLable expiry / voided_at — leave NULL
                    }
                    $shifted = $this->shift((string) $row[$col], $from, $to);
                    if ($shifted === null) {
                        continue; // unparseable — leave untouched, never corrupt
                    }
                    $sets[] = sprintf("%s = '%s'", $col, $shifted);
                }
                if ($sets === []) {
                    continue;
                }
                // ULID PKs are char(26) [0-9A-Z]; quote as a string. int PKs stay bare.
                $pkValue = $spec['pkString']
                    ? "'" . $this->quote((string) $row[$pk]) . "'"
                    : (string) (int) $row[$pk];
                $this->execute(sprintf(
                    'UPDATE %s SET %s WHERE %s = %s',
                    $table, implode(', ', $sets), $pk, $pkValue,
                ));
            }
        }
    }

    /** ULIDs are [0-9A-Z]{26}; reject anything else rather than build unsafe SQL. */
    private function quote(string $ulid): string
    {
        if (preg_match('/^[0-9A-Z]{26}$/', $ulid) !== 1) {
            throw new RuntimeException("Unexpected non-ULID primary key: {$ulid}");
        }
        return $ulid;
    }

    private function shift(string $value, DateTimeZone $from, DateTimeZone $to): ?string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $from);
        return $dt === false ? null : $dt->setTimezone($to)->format('Y-m-d H:i:s');
    }
}
```

Primary keys were verified from the create migrations and are baked into
`TARGETS` above, because getting them wrong corrupts data:

- `vault_settings` is keyed by `organization_id`, not `id`.
- `vault_documents.id` and `document_versions.id` are `char(26)` **ULIDs**
  (`20260530000005/6`), so their `WHERE` clause must quote a validated string —
  an `(int)` cast would collapse every ULID to `0` and update the whole table on
  the first row. `users` (`int id`) and `vault_settings` (`int organization_id`)
  stay bare. Re-confirm from the schema before lifting this skeleton; do not
  assume `id`/int.

---

## 5. Deploy order and runbook

Inherit `20260711000002`'s warning verbatim: **the migration and the code switch
ship together.** Sequence:

1. **Code first (same release):** inject `ClockInterface` (the bound `UtcClock`)
   into `PdoUserRepository`, `PdoVaultSettingsRepository`,
   `PdoVaultDocumentRepository`, `PdoDocumentVersionRepository`, and the
   `UploadDocumentUseCase` write path; replace every `date()`/`NOW()` timestamp
   write with `clock->now()`. After this, all *new* rows are UTC.
2. **Migration in the same deploy, before the app serves new writes:** run
   `ConvertCoreTimestampsToUtc`. Existing host-local rows shift to UTC exactly
   once.
3. **Never run the migration twice** and never run it after the app has begun
   writing UTC rows without it — a UTC row shifted again ages by the offset.
4. **Record** MySQL `@@session.time_zone` and PHP `date.timezone` at run time in
   the deploy log; the migration assumes they are equal (§3).

Pre-flight (staging / a copy of the demo DB): snapshot the DB, run the migration,
assert every shifted column moved by exactly the offset and that
`transaction_date` / `retention_expires_at` / `login_attempts.*` are **byte-for-byte
unchanged**.

---

## 6. Companion code fix (not a migration)

`retention_expires_at`'s anchor when `transaction_date` is null is
`date('Y-m-d')` = today-in-JST (`UploadDocumentUseCase.php:73`). This is a code
defect independent of the data shift: the anchor should come from
`ClockInterface` so "today" is computed in the same zone as everything else.
Fold this into step 1's `UploadDocumentUseCase` change. Existing `date`-typed
rows are **not** migrated (§2b) — only the writer is corrected, going forward.

---

## 7. Rollback

`down()` is the symmetric UTC→host-local convert, reversible on a zone-stable
host. Operationally, prefer the pre-migration DB snapshot (step "Pre-flight")
for rollback on the live host, since `down()` re-introduces the very ambiguity
this plan removes. The migration is a one-shot; treat the snapshot as the real
undo.

---

## 8. Acceptance (implementation phase)

Match #161's bar: a write-side test exercising the real repository code path in a
TZ-pinned subprocess, run under **both** `Asia/Tokyo` and `UTC`, asserting the
stored value is UTC regardless of host zone. Add an assertion that the excluded
columns (`transaction_date`, `retention_expires_at`, `login_attempts.*`) are
never touched by the migration.

---

## 9. Execution gate

- **This document is design only. No migration file has been added.**
- Execution requires a **post-launch owner GO** because it mutates real data on
  `vault.ayane.co.jp`.
- When that GO comes: lift §4 into `database/migrations/`, implement §1's code
  changes (§5 step 1) and §6, satisfy §8, then deploy per §5.

Refs #228.
