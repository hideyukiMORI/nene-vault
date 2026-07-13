# Security Assessment — Black-box Live ATK (2026-07-13)

**App version at time of test:** `nene-vault` @ `a77fc62` (branch base
`origin/main`, latest merged #193) · Framework **NENE2 v1.10.0** · PHP 8.4.22 ·
Apache 2.4.67.

**Scope of this report:** an **authorized self / maintainer-run** security
assessment of the running NeNe Vault API — authentication & JWT verification,
multi-tenant isolation (`organization_id` cross-org access), role/capability
boundaries, upload/download paths, CSV/ZIP export, storage-path disclosure,
security headers/CORS, error/info disclosure, and the compliance hard-rule
invariants (no hard-delete, SHA-256-verified download). This is **not** a
third-party penetration test. Attacks were run only against a disposable local
Docker stack; **no production host** (`vault.ayane.co.jp` or any live system)
was targeted, and no destructive/DoS payloads were used.

This is the first live-fire report for this repository; prior security work was
the `docs/review/middleware-security.md` self-review checklist only.

## Result

**0 Critical / 0 High / 0 Medium / 0 Low `EXPOSED`.**

48 live attack assertions across 11 categories, **`VULN=0`**. Every
tenant-isolation, RBAC, JWT, upload/download, export and compliance invariant
held under fire (full transcript reproduced in [Attack results](#attack-results)).
Two low-severity **information-disclosure INFO** observations on the container
build (Apache version banner, `X-Powered-By` PHP version) were remediated
in-branch and re-verified (see [Remediation](#remediation-of-observations)); the
post-fix run is `PASS=48 VULN=0 INFO=0`.

## Methodology

- **Black-box live attack (ATK).** The real app is started in a throwaway
  Docker stack (`docs/security/harness/`), seeded with two organizations, and
  driven over HTTP with `curl`. Bearer tokens for every tenant/role are minted
  with the app's own secret via `harness/mint.php` — the same tokens a real
  login would issue, plus deliberately malformed ones (alg:none, tampered
  payload, expired, wrong-secret).
- **Regression verification.** Each INFO observation was fixed on this branch,
  the image rebuilt, and the ATK re-run to confirm the observation is gone with
  no regression elsewhere.
- **Test-suite corroboration.** The repo already pins these invariants in CI
  via the `*BoundaryTest` suite (`PublicSurfaceBoundaryTest`,
  `Organization/User/Export/Audit/VaultSettings/Document*BoundaryTest`);
  `composer check` is green on this branch.

**Verdict legend:** `🚫 BLOCKED` (attack rejected) · `✅ SAFE` (invariant held) ·
`EXPOSED` (attack succeeded — none found).

## Environment

| Item | Value |
|---|---|
| Runtime | PHP 8.4.22, Apache 2.4.67 (`php:8.4-apache`), disposable container |
| Framework | hideyukimori/nene2 v1.10.0 (Packagist) |
| Database | SQLite (throwaway volume; MySQL variant available via `--profile mysql`) |
| Tenant resolution | `single` + claim-based org resolution (#141) — org taken from the signed `org` claim |
| App wiring | `BearerTokenMiddleware` → `OrgResolverMiddleware` → `CapabilityMiddleware`; blocklist public surface `/health`, `/admin/auth/login`, `/demo/standard`, `/demo/guided` |
| APP_DEBUG | **`true`** — deliberately worst-case for info-disclosure checks |
| Seed | org 1 `default` + org 2 `acme`, one org-tagged received-document PDF each |

## Attack results

| # | Category | Techniques | Result |
|---|---|---|---|
| A | Authentication / JWT | no token; malformed; **alg:none** forgery; **payload tamper** (role→superadmin); expired `exp`; wrong-secret signature | 🚫 BLOCKED — all 6 → 401. Verifier pins `alg=HS256`, `hash_equals` signature, mandatory int `exp`. |
| B | Tenant isolation (org1 vs org2) | cross-org GET / download / history / PATCH metadata / void; search leakage; **cross-org user read / role-escalation / delete** | 🚫 BLOCKED — 11/11. All cross-org object access → 404; org2 doc and org1 user left unchanged; search returns only own-org rows. |
| C | RBAC / capabilities | viewer upload/void/export/users/audit; member export/settings; org-admin hitting superadmin-only org mgmt | 🚫 BLOCKED — 8/8 → 403; viewer read of own doc correctly 200. |
| D | SQL / search injection | `' OR 1=1--`, `; DROP TABLE`, `UNION SELECT password_hash` in `counterparty_name` | ✅ SAFE — parameterized; 200/422, no 500, table intact after attempts. |
| E | Path traversal / storage disclosure | `../` in document & version id; storage path / `file_path` in body & download headers | 🚫 BLOCKED — traversal → 404; **no** storage path or `file_path` in any response or header. |
| F | Upload MIME / download | `text/html` upload; HTML body spoofed as `application/pdf` then downloaded | 🚫 BLOCKED — `text/html` → 415; spoofed file served `Content-Disposition: attachment` + `X-Content-Type-Options: nosniff`. |
| G | Security headers / CORS | header inventory; evil `Origin` reflection | ✅ SAFE — `nosniff` + `X-Frame-Options` present; no wildcard ACAO for a foreign origin. (Version banners: see INFO.) |
| H | Error handling / info disclosure | forced 404 / not-found; body inspection under `APP_DEBUG=true` | ✅ SAFE — clean RFC 9457 Problem Details; no SQL/DSN/stack/path. |
| I | Export CSV formula injection | `counterparty_name` = `=2+5+cmd|calc`, exported to CSV | ✅ SAFE — no exported cell begins with a formula lead char; `Nene2\Export\CsvWriter` neutralizes (ADR 0015). |
| J | Compliance invariants | void a document; tamper a stored byte then download | ✅ SAFE — void sets `status=voided` (row retained, no hard-delete); tampered file blocked by SHA-256 check → 500 with no file/hash leak. |
| K | Login throttle | 7 rapid bad logins for one email | 🚫 BLOCKED — throttled → 429 (`PdoLoginThrottle`, 5/15 min per email+IP). |

Full runner transcript (`SUMMARY: PASS=48 VULN=0 INFO=0`) is reproducible with
`docs/security/harness/probe.sh` — see `harness/README.md`.

## Verified-safe core controls

- **JWT verification internals** — `Nene2\Auth\LocalBearerTokenVerifier` rejects
  any `alg` ≠ `HS256` (no alg-confusion / `none`), verifies the HMAC with
  `hash_equals` (constant-time), and requires a numeric `exp` in the future.
- **Claim-based tenant isolation (#141)** — org is derived from the *signed*
  `org` claim; a bearer for org A can only ever act as org A. Repositories on
  tenant tables (`vault_documents`, `document_versions`, `vault_settings`) scope
  every query by `organization_id`; user-management use-cases enforce
  `user.organizationId === resolvedOrg` before any read/mutate.
- **Capability model** — `CapabilityResolver` + `Role::hasCapability`;
  superadmin-only org management, admin-gated users/settings/export/audit,
  member cannot export or change settings, viewer is read-only.
- **Storage-path confidentiality** — the download handler streams bytes at the
  application layer; the storage path never appears in responses, headers, or
  the download URL (only server-generated ULIDs are exposed).
- **Upload safety** — server-side ULID document ids (no client path influence),
  `basename` + whitelist filename sanitization, MIME allowlist
  (`application/pdf`, `image/jpeg`, `image/png`), size cap, forced-attachment +
  `nosniff` download.
- **Compliance hard rules** — SHA-256 verified on every download (mismatch
  fails closed), void is a status change (no hard-delete), every mutation is
  audited in the same transaction.

## Remediation of observations

| Observation | Severity | Fix (this branch) | Re-verification |
|---|---|---|---|
| **INFO-1** — `Server: Apache/2.4.67 (Debian)` reveals server version/OS | Low (info disclosure) | `Dockerfile`: `ServerTokens Prod` + `ServerSignature Off` + `TraceEnable Off` in Debian's `security.conf` | `Server: Apache`; TRACE → 405; probe → `no version-revealing Server header` |
| **INFO-2** — `X-Powered-By: PHP/8.4.22` reveals PHP version | Low (info disclosure) | `Dockerfile`: `expose_php = Off` (php.ini `conf.d`) | header absent; probe → `no X-Powered-By version banner` |

Both fixes apply to the container image we build (local + the disposable-org
demo). On HETEML shared hosting the `Server` banner is host-controlled and out
of the application's reach — noted, not claimed as fixed there.

## Residual notes (non-blocking)

- **No application-layer at-rest encryption.** Stored document bytes are written
  in the clear by `LocalFilesystemDocumentStorage`; the S3 adapter sends no SSE
  header. Confidentiality at rest currently relies on OS filesystem permissions
  (or the S3 bucket policy), **not** on an app-managed cipher like nene-clear's
  `Encryptor`. This is a design posture, not a black-box-reachable exposure — an
  attacker cannot read stored files without host/bucket access. Recommended as a
  future enhancement (field/file `Encryptor` + key management) and filed for
  follow-up. Documented honestly as **not implemented**.
- **`users` repository defense-in-depth.** `PdoUserRepository::findById` /
  `updateRole` / `updateStatus` / `updateEmail` / `delete` query by `id` only;
  cross-org safety is enforced one layer up in the use-cases (verified live in
  category B — cross-org user read/escalate/delete all → 404). Adding an
  `organization_id` predicate at the repository layer would align with the
  "every tenant-table query includes `organization_id`" hard rule as
  belt-and-braces. No live exposure.
- **Operational:** keep `APP_DEBUG=false` in production. Even at `APP_DEBUG=true`
  no internals leaked in the tested error paths, but debug mode is not a
  production posture.

## Conclusion

Under live black-box attack, NeNe Vault @ `a77fc62` (NENE2 v1.10.0) exposed **no
Critical/High/Medium/Low** findings across authentication, tenant isolation,
authorization, injection, file handling, export, and compliance invariants. Two
low-severity version-banner INFO observations were remediated in this branch and
re-verified. Residual items (at-rest encryption posture, `users`-repo
defense-in-depth) are documented honestly as non-blocking follow-ups.

---

*Methodology footer: authorized self / maintainer-run black-box live ATK — 48
assertions across 11 categories against a disposable local stack. Reproduce with
`docs/security/harness/` (`seed.sh` + `probe.sh`). Not a third-party pentest; no
production system was targeted.*
