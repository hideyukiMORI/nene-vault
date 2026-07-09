# Demo Environment Runbook

One page for running, showing, and resetting the NeNe Vault prospect demo
(#118). The demo is a **fixed, pre-seeded organization** with hand-out
credentials — no self-signup, no auth-code changes. Reset = rerun the seeder.

## 1. Prepare

`.env` essentials (see `.env.example`):

```dotenv
NENE2_LOCAL_JWT_SECRET=<64 hex chars>       # required — fail-close without it
TENANT_RESOLUTION=single
ORG_SLUG=demo                               # the app serves the demo org directly
NENE_VAULT_STORAGE_PATH=storage/vault
NENE_VAULT_DEMO_ADMIN_PASSWORD="…12+ chars…"
NENE_VAULT_DEMO_VIEWER_PASSWORD="…12+ chars…"
```

Schema: `php docker/bootstrap-schema.php` (sqlite) or phinx/installer (MySQL).

## 2. Seed / reset

```bash
php tools/seed-demo.php --force
```

**Destructive for the demo org only**: reaps every DB row **and stored file**
of the org with slug `demo`, then reseeds ~20 generated received-invoice PDFs
(9 fictional Japanese vendors across the construction / building-maintenance /
creative industries, 適格請求書 T+13-digit registration numbers) dated over
the past 12 months relative to the run day, with void→restore history so the
audit trail has movement. All uploads go through the real use cases — SHA-256,
version rows and audit events are authentic. PDF bodies are romanized
(base-14 fonts carry no CJK); the Japanese vendor names live in the searchable
metadata, which is what the 電帳法 search showcase exercises.

## 2.5 Auto-login (`/demo/standard`, #127)

With `DEMO_MODE=1` in `.env`, opening `https://…/demo/standard` seats the
visitor straight into the demo org as **`demo-viewer` (read-only, #130)** —
one URL, land signed in. Read covers the showcase (search, SHA-256 verified
download, audit trail, export); the upload demo uses the hand-out **admin**
credentials on the login form. The seat never mints an admin token: all
visitors share the one fixed org, so a public admin session would be a
public upload endpoint. Fail-close: 404 while `DEMO_MODE` is unset or the
demo viewer is not seeded.

## 3. Showcase walkthrough (the 見せ場)

1. **Search** — filter by counterparty (e.g. 大和建設株式会社), by period
   (電帳法の期間検索), by amount range.
2. **Detail + SHA-256** — open a document, download the version, note the
   verified hash.
3. **History / audit** — the void→restore documents show a full trail; the
   audit page shows a year of activity.
4. **Upload** — drop in any PDF; duplicate detection and retention calculation
   run live.
5. **Export** — CSV manifest of the filtered set.
6. **Reset** — rerun `php tools/seed-demo.php --force`; clean again.

## 4. Known limitations

- Disposable per-visitor orgs (`/demo/{template}`, invoice-style) are **not
  possible on a single-domain deployment**: Vault resolves the tenant from the
  host (TENANT_RESOLUTION), so every request lands on the fixed `ORG_SLUG`
  org. Wildcard subdomains or a tenancy design decision are prerequisites —
  the seeder/reaper here are already org_id-parameterized for that future
  round.
- OCR / email-inbound stay disabled in the demo (external dependencies).
- Sessions last 24 h (normal token TTL).

## 5. Shared-hosting (HETEML) deployment sketch

Target `vault.ayane.co.jp` (same shape as invoice/clear; DB `_nene_vault`).

1. `bash tools/build-release.sh` on the dev machine (vendor `--no-dev`, SPA
   built into `public_html/`, installer included; the zip keeps `.htaccess`).
2. rsync so only `public_html/` is inside the docroot (vendor one level up).
3. Open `https://…/install.php` (it now lives in `public_html/`, #120) and
   walk the wizard: requirements → database (connection-tested) → org/admin.
   It self-unlinks on success.
4. `.env`: MySQL `_nene_vault` credentials, `APP_DEBUG=false`, real
   `NENE2_LOCAL_JWT_SECRET`, `TENANT_RESOLUTION=single`, `ORG_SLUG=demo`,
   demo passwords as above.
5. Seed over SSH: `php8.4 tools/seed-demo.php --force`; register a nightly
   reset cron with the same command.

The `Authorization` header is stripped by HETEML's front proxy — the SPA
mirrors the token into `X-Authorization` and the front controller adopts it
when the standard header is absent (#118), the same fix proven on invoice and
clear.
