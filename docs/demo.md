# Demo Environment Runbook

One page for running, showing, and resetting the NeNe Vault prospect demos
(#118, #141). Two demo models coexist on one deployment:

| | Disposable org (`/demo/standard`) | Fixed showcase org (`/demo/guided`) |
| --- | --- | --- |
| Audience | The distribution link handed to prospects | Guided walkthroughs, README screenshots |
| Seat | **Admin** of a private, freshly provisioned org | **Viewer** of the shared showcase org (#130) |
| Writes | Full upload / void / restore / export — the showcase | Read-only (a public admin seat would be a public upload endpoint) |
| Lifetime | `DEMO_TTL_HOURS` (default 3 h), swept hourly by cron | Permanent; reset nightly by `tools/seed-demo.php` |
| Reset | Re-open the link → brand-new org | Rerun the seeder |

Both are gated by `DEMO_MODE` (strict opt-in; anything else 404s) and share
one dataset: the org_id-parameterized `DemoDataSeeder` (~20 generated
received-invoice PDFs, 9 fictional vendors across construction /
building-maintenance / creative, T+13-digit registration numbers, dated over
the trailing 12 months, with void→restore history). All uploads go through
the real use cases — SHA-256, version rows and audit events are authentic.

## 1. Prepare

`.env` essentials (see `.env.example`):

```dotenv
NENE2_LOCAL_JWT_SECRET=<64 hex chars>       # required — fail-close without it
TENANT_RESOLUTION=single
ORG_SLUG=demo                               # unauthenticated + fixed-org fallback
NENE_VAULT_STORAGE_PATH=storage/vault
NENE_VAULT_DEMO_ADMIN_PASSWORD="…12+ chars…"   # fixed-org hand-out credentials
NENE_VAULT_DEMO_VIEWER_PASSWORD="…12+ chars…"
DEMO_MODE=1
# DEMO_SLUG_PREFIX=demo-  DEMO_TTL_HOURS=3  DEMO_MAX_ORGS=200 (defaults)
```

Schema: `php docker/bootstrap-schema.php` (sqlite) or phinx/installer (MySQL).

## 2. How the disposable demo works (#141)

`GET /demo/standard` → per-IP throttle (30/h, file-backed — shared hosting
has no cross-process memory) and org-ceiling check (`DEMO_MAX_ORGS`, 503
when full) → provision org + throwaway admin (random undisclosed password,
slug-namespaced email) through the real create-org/create-user use cases →
seed → seat page stores the SPA's `AuthSession` in `sessionStorage` and lands
in the app signed in.

Tenancy: the minted token carries the disposable org in its `org` claim,
and `OrgResolverMiddleware` resolves **verified token claims first**, host/env
strategy second — that is what makes per-visitor orgs reachable on a
single-domain deployment. Superadmin (`org_id: null`) and unauthenticated
requests still resolve via `TENANT_RESOLUTION`. Errors on the demo start
route are content-negotiated: browsers get a branded HTML card (429 with live
countdown / 503 / 404), API clients get RFC 9457 Problem Details.

## 3. Sweep (cron, hourly)

```bash
php tools/sweep-demo.php
```

Reaps demo orgs (`demo-` slug prefix only) past `DEMO_TTL_HOURS`, enforces
`DEMO_MAX_ORGS` overflow, deletes each org's DB rows **and storage tree**,
and prunes stale throttle files. Idempotent. `created_at` is parsed in the
host's default timezone — the same timezone `date()` wrote it in (#143;
Vault differs from clear/deal here, which write UTC and need the UTC-explicit
parse of clear #280 / deal #72). Regression-tested for JST and UTC hosts in
`tests/Demo/SweepDemoScriptTest`.

Production wrapper: `~/bin/sweep-vault-demo.sh`, registered hourly in the
HETEML panel (offset the minute from sibling crons; `:40` is free).

## 4. Fixed showcase org: seed / reset

```bash
php tools/seed-demo.php --force
```

**Destructive for the fixed demo org only**: reaps every DB row **and stored
file** of the org with slug `demo` (production: `ayane`), then reseeds.
Nightly cron. Hand-out credentials come from the `NENE_VAULT_DEMO_*_PASSWORD`
env vars (stable across resets).

With `DEMO_MODE=1`, `GET /demo/guided` seats the visitor as **`demo-viewer`
(read-only)**. Fail-close: 404 while `DEMO_MODE` is unset or the viewer is
not seeded.

## 5. Showcase walkthrough (the 見せ場 — disposable org, admin seat)

1. **Search** — filter by counterparty (e.g. 大和建設株式会社), by period
   (電帳法の期間検索), by amount range.
2. **Upload** — drop in any PDF; duplicate detection, SHA-256 and retention
   calculation run live, and the audit trail grows in front of the prospect.
3. **Detail + SHA-256** — open a document, download the version, note the
   verified hash.
4. **Void → restore** — mutate freely; it is a private org and dies with TTL.
5. **History / audit** — the seeded void→restore documents plus everything
   the prospect just did, append-only.
6. **Export** — ZIP with manifest CSV of the filtered set.
7. **Reset** — re-open `/demo/standard`; brand-new org.

## 6. Known limitations

- OCR / email-inbound stay disabled in the demo (external dependencies).
- Disposable sessions last `DEMO_TTL_HOURS` (token TTL matches the org TTL);
  fixed-org sessions last 1 h (the login TTL, #148).
- A swept org's leftover token fails closed with 404 `org-not-found` — the
  visitor just re-opens the link.

## 7. Shared-hosting (HETEML) deployment sketch

Target `vault.ayane.co.jp` (same shape as invoice/clear/deal; DB `_nene_vault`).

1. `bash tools/build-release.sh` on the dev machine (vendor `--no-dev`, SPA
   built into `public_html/`, installer included; the zip keeps `.htaccess`).
2. rsync so only `public_html/` is inside the docroot (vendor one level up).
3. Open `https://…/install.php` (it now lives in `public_html/`, #120) and
   walk the wizard: requirements → database (connection-tested) → org/admin.
   It self-unlinks on success.
4. `.env`: MySQL `_nene_vault` credentials, `APP_DEBUG=false`, real
   `NENE2_LOCAL_JWT_SECRET`, `TENANT_RESOLUTION=single`, `ORG_SLUG=<fixed org>`,
   `DEMO_MODE=1`, demo passwords as above.
5. Seed the fixed org over SSH: `php8.4 tools/seed-demo.php --force`; register
   the nightly reseed cron and the hourly `tools/sweep-demo.php` cron.

The `Authorization` header is stripped by HETEML's front proxy — the SPA
mirrors the token into `X-Authorization` and the front controller adopts it
when the standard header is absent (#118), the same fix proven on invoice and
clear. The `.htaccess` front-controller rule already routes `demo` paths to
PHP (deal hit the SPA-fallback trap here, #71 — vault had the rule from #127).
