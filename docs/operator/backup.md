# Backup Guide

NeNe Vault has two data stores that must both be backed up: the **database** and
the **file storage directory**.

---

## What to back up

| Store | Contains | Loss impact |
|---|---|---|
| Database | Document metadata, version records, audit log, user/org data | Cannot search, access history, or verify file integrity |
| File storage (`NENE_VAULT_STORAGE_PATH`) | Actual PDF/image files | Cannot download or re-export documents |

Both stores must be backed up together and kept in sync. A database backup
without the corresponding files is incomplete; files without a database cannot
be associated with metadata or audit events.

---

## SQLite (default)

The SQLite database is a single file at `DB_NAME` (default `var/nene_vault.sqlite`).

### Online backup (no downtime)

Take the copy, verify it, and only then move it into place. A backup that is not
asserted on is not a backup — it is a file that will be found wanting during a
restore, which is the one moment you cannot afford to find out.

```sh
#!/bin/sh
set -eu

src=var/nene_vault.sqlite
out=/backups/nene_vault_$(date +%Y%m%d).sqlite
tmp=$out.part

rm -f "$tmp"

php -r '
  $src = $argv[1];
  $tmp = $argv[2];
  (new PDO("sqlite:$src"))->exec(sprintf("VACUUM INTO %s", (new PDO("sqlite::memory:"))->quote($tmp)));

  $check = (new PDO("sqlite:$tmp"))->query("PRAGMA integrity_check")->fetchColumn();
  if ($check !== "ok") {
      fwrite(STDERR, "integrity_check: $check\n");
      exit(1);
  }
' "$src" "$tmp"

mv "$tmp" "$out"
```

The `rm -f` is not tidiness. `VACUUM INTO` refuses a target that already holds
data — `file is not a database` — so a partial file left by a failed run would
block every subsequent backup until someone cleared it by hand. Removing it up
front makes the script re-runnable.

A run that fails the check exits non-zero, leaves the `.part` file behind for
inspection, and **does not touch the backup already in place** — the same
guarantee the MySQL commands give.

If the `sqlite3` CLI is installed, the same shape works with it:

```sh
sqlite3 "$src" ".backup '$tmp'"
[ "$(sqlite3 "$tmp" 'PRAGMA integrity_check;')" = ok ] || exit 1
```

> ⚠️ **Prefer the PHP form.** Any host running Vault has PHP — it is the
> application runtime. The `sqlite3` CLI is a separate package and is frequently
> absent, including on hosts where Vault itself runs perfectly well.

### Verifying a SQLite backup

`PRAGMA integrity_check` returns the single string `ok` on a sound database, and
either a list of problems or an error on a damaged one. Like the MySQL dump
marker, it is useful at two moments.

**1. Immediately after taking it** — shown above.

**2. After the fact, across backups you already hold:**

```sh
set -eu

for f in /backups/nene_vault_*.sqlite; do
  php -r '
    try {
      $r = (new PDO("sqlite:" . $argv[1]))->query("PRAGMA integrity_check")->fetchColumn();
      printf("%-9s %s\n", $r === "ok" ? "OK" : "DAMAGED", $argv[1]);
      exit($r === "ok" ? 0 : 1);
    } catch (Throwable $e) {
      printf("%-9s %s (%s)\n", "UNREADABLE", $argv[1], $e->getMessage());
      exit(1);
    }
  ' "$f" || true   # keep auditing the remaining files
done
```

> ⚠️ The `|| true` above is deliberate and is the **only** place this guide
> permits it: this loop audits a set and must not stop at the first bad file. Never
> use it in the backup itself — see the warning at the end of the MySQL section.

#### Why `integrity_check` and not a size check

🔑 **A truncated SQLite file is not an empty file.** A copy that died at 40 %
still opens as far as the filesystem is concerned, and `[ -s "$f" ]` passes on it
without complaint. `integrity_check` refuses it — SQLite's header records the
database size in pages, so a short file is detected even when the truncation
lands exactly on a page boundary.

#### What `integrity_check` cannot tell you

It answers *"is this file a sound database?"*, not *"is this the data you meant to
back up?"*. A backup that is structurally perfect but taken from the wrong
generation — or taken while writes were still landing — returns `ok` with rows
missing.

🔑 **An empty file is a sound database.** A zero-byte `.sqlite` passes
`integrity_check` with `ok`, because that is exactly what a database with no
tables looks like on disk. So the check cannot, on its own, distinguish a good
backup from a step that ran and produced nothing.

Structure and contents are two different questions. The second one is answered in
the next section, by reconciling the database against the files — which is also
what catches the empty-backup case, since zero rows cannot account for the files
on disk.

## MySQL on shared hosting (pre-deploy dump)

The public demo (`DB_ADAPTER=mysql`) is the one install that is not SQLite. Take a
manual dump before every deploy; the DB user on shared hosting has no `PROCESS`
privilege, so **`--no-tablespaces` is required** or `mysqldump` prints an
`Access denied … PROCESS privilege` line (the dump is still complete, but the
non-zero exit makes a scripted run look failed — measured 2026-08-25 on HETEML).

```bash
# credentials never reach stdout or `ps`: a 0600 defaults file read from .env
umask 077
CNF=~/.my-vault-cnf-$$            # HETEML has no mktemp
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' \
  "$(grep ^DB_USER= .env | cut -d= -f2-)" \
  "$(grep ^DB_PASSWORD= .env | cut -d= -f2-)" \
  "$(grep ^DB_HOST= .env | cut -d= -f2-)" > "$CNF"
OUT=~/backups/vault-pre-<version>-$(date +%Y%m%d-%H%M%S).sql.gz
mysqldump --defaults-extra-file="$CNF" --single-transaction --no-tablespaces \
  --routines --triggers "$(grep ^DB_NAME= .env | cut -d= -f2-)" | gzip > "$OUT"
rm -f "$CNF"
```

Verify four things before deploying — a dump that exists is not a dump that works:

1. `gzip -t "$OUT"` — readable archive
2. `zcat "$OUT" | head -3 | grep -q 'MySQL dump'` — a real dump header
3. `CREATE TABLE` present for `organizations users vault_documents document_versions audit_events phinxlog`
4. `phinxlog` row count equals the number of files in `database/migrations` (pending migrations
   show up here as a mismatch, before `phinx status` is ever run)

Shared hosting also lacks `sha256sum` and `df`; verify an uploaded release ZIP with
`php8.4 -r 'echo hash_file("sha256", "nene-vault-<version>.zip");'` against the sidecar.

### File backup

Copy the entire storage directory:

```sh
rsync -av --progress \
  /var/nene-vault/files/ \
  /backups/nene-vault-files-$(date +%Y%m%d)/
```

### Verifying a file backup

🔑 **Vault verifies SHA-256 on every download.** Backups of those same files are
held to the same standard, or the guarantee stops at the edge of the running
system.

`document_versions` carries `file_path` (relative to `NENE_VAULT_STORAGE_PATH`)
and `file_sha256` for every stored file. That is enough to reconcile a backup
against itself, with no access to the live system:

```php
<?php
// verify-backup.php <backup.sqlite> <backup files root>
[$db, $root] = [$argv[1], rtrim($argv[2], '/')];

$pdo = new PDO("sqlite:$db");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rows = $pdo->query(
    'SELECT file_path, file_sha256 FROM document_versions'
)->fetchAll(PDO::FETCH_ASSOC);

$ok = $missing = $mismatch = 0;
$seen = [];

foreach ($rows as $r) {
    $abs = $root . '/' . $r['file_path'];
    $seen[$r['file_path']] = true;

    if (!is_file($abs)) {
        fwrite(STDERR, "MISSING   {$r['file_path']}\n");
        $missing++;
    } elseif (hash_file('sha256', $abs) !== $r['file_sha256']) {
        fwrite(STDERR, "MISMATCH  {$r['file_path']}\n");
        $mismatch++;
    } else {
        $ok++;
    }
}

$orphan = 0;
$walk = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($walk as $f) {
    if (!$f->isFile()) {
        continue;
    }
    $rel = substr($f->getPathname(), strlen($root) + 1);
    if (!isset($seen[$rel])) {
        fwrite(STDERR, "ORPHAN    $rel\n");
        $orphan++;
    }
}

printf(
    "rows=%d ok=%d missing=%d mismatch=%d orphan=%d\n",
    count($rows), $ok, $missing, $mismatch, $orphan
);

exit(($missing || $mismatch) ? 1 : 0);
```

```sh
php verify-backup.php \
  /backups/nene_vault_20260823.sqlite \
  /backups/nene-vault-files-20260823
```

**Why SHA-256 and not size or count.** A file that is corrupted in transit is
usually the *same length* as the original — a flipped byte changes the content
and nothing else. Counting files, or comparing sizes, passes such a backup. The
hash is the only check that does not.

**MISSING and ORPHAN are not the same failure.** They are the two directions of
the same skew, and the guide's rule that the database and the files must be taken
together is what they enforce:

| Reading | Means | Severity |
|---|---|---|
| `missing > 0` | The database references a file the backup does not hold | 🔴 **Data loss.** Exits non-zero |
| `mismatch > 0` | The file is there but its bytes are not the ones Vault recorded | 🔴 **Silent corruption.** Exits non-zero |
| `orphan > 0` | Files present that no row references | 🟡 Exits **zero** |

An orphan count that is small and stable is normal — deleted-then-restored
versions and stray temporary files both land there. An orphan count that *jumps*
between runs means the database is older than the files: the copies were not
taken together. The opposite skew — files older than the database — appears as
`missing`, which is why that one is fatal and this one is not.

### Recommended schedule

| Frequency | Retention |
|---|---|
| Daily incremental | 30 days |
| Weekly full | 6 months |
| Monthly offsite | 7–10 years (matches retention_years) |

---

## MySQL

Dump to a **temporary file, verify it, and only then promote it** to its final
name. Writing straight to the final name lets a dump that died halfway take the
place of "the latest backup" — and nothing downstream would notice.

```sh
set -euo pipefail

out="/backups/nene_vault_$(date +%Y%m%d).sql"
tmp="$out.part"

mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  --no-tablespaces \
  -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" \
  "$DB_NAME" \
  > "$tmp"

[ -s "$tmp" ] || { echo "backup FAILED: $tmp is empty" >&2; rm -f "$tmp"; exit 1; }
case "$(tail -1 "$tmp")" in
  '-- Dump completed'*) : ;;
  *) echo "backup FAILED: no completion marker in $tmp (truncated dump)" >&2
     rm -f "$tmp"; exit 1 ;;
esac

mv "$tmp" "$out"
echo "backup OK: $out ($(du -h "$out" | cut -f1))"
```

For Docker Compose:

```sh
set -euo pipefail

out="/backups/nene_vault_$(date +%Y%m%d).sql"
tmp="$out.part"

docker compose exec -T mysql \
  mysqldump --single-transaction --routines --triggers --no-tablespaces \
  -u nene_vault -pnene_vault nene_vault \
  > "$tmp"

[ -s "$tmp" ] || { echo "backup FAILED: $tmp is empty" >&2; rm -f "$tmp"; exit 1; }
case "$(tail -1 "$tmp")" in
  '-- Dump completed'*) : ;;
  *) echo "backup FAILED: no completion marker in $tmp (truncated dump)" >&2
     rm -f "$tmp"; exit 1 ;;
esac

mv "$tmp" "$out"
echo "backup OK: $out ($(du -h "$out" | cut -f1))"
```

`-T` is required: without it `docker compose exec` allocates a TTY and mangles
the dump on its way to the redirect.

### Verifying a dump

`mysqldump` writes `-- Dump completed on <timestamp>` as the last line when — and
only when — it finished. That single line is what makes a dump verifiable, at two
different moments.

**1. Immediately after taking it.** Shown in both commands above: the dump lands
on `$tmp`, the marker is asserted, and only a dump that passes gets `mv`'d into
place. A failure leaves the previous backup untouched and removes the partial
file.

**2. After the fact, across dumps you already hold.** This is the marker's real
value — it lets you audit backups taken before anyone thought to check them:

```sh
set -euo pipefail

for f in /backups/nene_vault_*.sql; do
  case "$(tail -1 "$f")" in
    '-- Dump completed'*) echo "OK        $f" ;;
    *)                    echo "TRUNCATED $f" >&2 ;;
  esac
done
```

For gzipped dumps, read the last line through `gunzip` instead:

```sh
case "$(gunzip -c "$f" | tail -1)" in
```

### Why these three things

**`--no-tablespaces`** — MySQL 8's `mysqldump` issues `SHOW CREATE TABLESPACE`,
which requires the `PROCESS` privilege. On shared hosting, or with a
deliberately scoped application user, the dump fails outright with
`Access denied ... PROCESS privilege ... tablespaces`. Vault needs no tablespace
DDL in its dumps, so the flag costs nothing and removes the failure mode.

**The completion marker, not a size check** — 🔑 **a truncated dump is not an
empty file.** `[ -s "$file" ]` passes happily on a dump that died at 40 %, so
size alone will hand you a backup that only fails when you try to restore it.
The `-s` test above is kept as a cheap first gate, but the marker is what decides.

**`set -euo pipefail`** — the commands above redirect with `>` rather than piping,
so nothing is currently hidden by a pipe. The moment you add one — compressing
with `mysqldump ... | gzip > "$tmp"` is the obvious next step — the exit status
becomes **gzip's**, and a failed `mysqldump` disappears into a successful-looking
pipeline. `pipefail` is what keeps that failure visible.

> ⚠️ **Never write `... || true` in a backup script.** It converts "the command
> was not found" and "the container is not running" into exit 0, so cron reports
> success every night while producing nothing at all. A backup that fails loudly
> is recoverable; a backup that does nothing and calls it success is not
> discovered until a restore. Assert on the **artifact**, not on the exit status.

---

## Docker Compose volume backup

If you use Docker volumes for the database or storage, back up the volume data:

```sh
# Stop the container first for a consistent snapshot
docker compose stop app

# Backup SQLite via volume
docker run --rm \
  -v nene_vault_data:/data \
  -v /backups:/backups \
  alpine tar czf /backups/nene-vault-data-$(date +%Y%m%d).tar.gz /data

docker compose start app
```

---

## Restore

### SQLite

```sh
# Stop the app
docker compose stop app

# Restore the backup
cp /backups/nene_vault_20260101.sqlite var/nene_vault.sqlite

# Restore files
rsync -av /backups/nene-vault-files-20260101/ /var/nene-vault/files/

docker compose start app
```

### MySQL

```sh
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
  < /backups/nene_vault_20260101.sql
```

---

## Retention window and hard delete

NeNe Vault **never hard-deletes document files** during the retention window
(`retention_expires_at`). The backup strategy must preserve files for the full
retention period (≥ 7 years; default 10 years).

After `retention_expires_at`, documents may be purged by an authorized operator
action (not yet implemented — Phase 4+). Until then, retain all backups.
