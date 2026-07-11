# Structural-Alignment Audit — 2026-07-11 (Vault findings)

On 2026-07-11 a read-only structural audit compared the four NeNe products
(invoice / clear / deal / vault) across six axes: NENE2 usage, auth/session,
multi-tenancy, installer/distribution, frontend, and demo machinery. This
document records the **Vault-relevant** outcome. Every finding below was
re-verified against this repository's `main` (cd298c4) before the issues were
filed; nothing here is copied unverified from the cross-product report.

Tracking:

- **#148** — session posture (security, high): 24 h TTL + `localStorage` + no login throttle
- **#149** — frontend stack one generation behind (React 18 / router 6 / Vite 6 / Node 20)
- **#150** — consolidated checklist for the remaining findings
- **#144** — installer probe reads `.env` via `parse_ini_file` (pre-existing, still open)
- **#143** — sweep timezone pairing (closed; the write-side UTC fix remains in #150)

## What the audit confirmed is already aligned

The fleet skeleton is shared and Vault is on it: NENE2 Router / DI /
ServiceProvider layout, RFC 9457 Problem Details, `GuardedJwtSecretResolver`
fail-close, conformance baseline in `composer check`, the `Nene2\Demo`
disposable-org consumer (throttle 30/h, cap 200 + 503, TTL 3 h),
the `Nene2\Install` core trio in `public_html/install.php`, `organization_id`
row scoping on every tenant query (a CLAUDE.md hard rule), and the
HETEML `X-Authorization` bridge (#118) on both backend and frontend.

## Findings (weakest first)

| # | Finding | Evidence | Severity | Issue |
|---|---|---|---|---|
| 1 | Session triple-weakness: access-token TTL 24 h (fleet: 1 h) + token persisted in `localStorage` (fleet: in-memory / sessionStorage) + no rate limit on `POST /admin/auth/login` | `src/Auth/LoginUseCase.php:11`; `frontend/src/entities/auth/model.ts:13,30`; `src/Auth/AuthRouteRegistrar.php:18` (no throttle anywhere in the auth path) | High (security) | #148 |
| 2 | Frontend stack one generation behind: React ^18.3.1, react-router-dom ^6.28.1, Vite ^6.4.3, TS ^5.7.2, Node >=20 vs. fleet React 19 / router 7 / Vite 7–8 / Node >=22. Main blocker for the planned shared UI kit | `frontend/package.json` | High | #149 |
| 3 | NENE2 dependency is a path repo at `@dev`; lock pins `dev-main` ref `1baf209` (2026-05-29). Builds are not reproducible and track local NENE2 HEAD (invoice/deal pin Packagist `^1.10`) | `composer.json:8,11-15`; `composer.lock` | High | #150 |
| 4 | Login never checks `users.status` — a deactivated user can still log in. The column exists and is maintained by user management, but `LoginUseCase` ignores it | `src/Auth/LoginUseCase.php`; `database/migrations/20260530000002_create_users_table.php:16` | Medium-high (security) | #150 |
| 5 | JWT claims schema diverges from the fleet: `sub`=email, `user_id`, `org_id` vs. the shared `sub`=user id, `org`. Same method calls `time()` directly instead of the injected `ClockInterface` (11 files in the conformance D4 raw-time baseline — fleet worst) | `src/Auth/LoginUseCase.php:33,39-46` | Medium | #150 |
| 6 | Custom `AdminApiAuthMiddleware` ("protect `/admin/*`, everything else open") instead of NENE2 `BearerTokenMiddleware` used by the other three products | `src/Auth/AdminApiAuthMiddleware.php:26-29,78` | Medium | #150 |
| 7 | `organizations.created_at`/`updated_at` written in host-local timezone; demo orgs go through this path, which is why the sweep deliberately bare-parses (#143). A fleet-wide UTC unification would silently break the pairing — the write side going UTC is the real fix | `src/Organization/PdoOrganizationRepository.php:69,114` | Medium (latent) | #150 |
| 8 | i18n keys are raw dot-notation strings over JSON catalogs; misses surface at runtime as the key itself. Siblings use typed TS catalogs where typos are compile errors (the #137 bug class) | `frontend/src/shared/i18n/translate.ts:7-16` | Medium | #150 |
| 9 | No base-path / subdirectory deployment support (no Vite `base`, no `APP_BASE_PATH`; invoice ADR 0015 is the fleet reference) and 401/403 handling hard-navigates via `window.location.href` | `frontend/vite.config.ts`; `frontend/src/shared/api/client.ts:17,20` | Medium | #150 |
| 10 | Custom `HealthHandler` (not NENE2 `HealthCheckInterface`/`HealthStatus`) and hand-written `Ulid` (deal uses `symfony/uid`) | `src/Http/HealthHandler.php`; `src/Support/Ulid.php` | Low | #150 |
| 11 | `tools/build-release.sh` emits no SHA-256 sidecar for the release zip (invoice's build script is the fleet reference) | `tools/build-release.sh` | Low-medium | #150 |
| 12 | Installer probe reads `.env` via `parse_ini_file` (same shape as deal's #78) | `public_html/install.php` | Medium | #144 (pre-existing) |

## Strengths the audit confirmed (Vault leads the fleet here)

- **Claim-first tenant resolution layered on a strategy pipeline** (#141):
  `src/Organization/Resolution/OrgResolverMiddleware.php` — a verified
  bearer's `org_id` claim wins over the host/env strategies, reaped-org tokens
  fail closed as 404, superadmin `null` falls through to the strategies.
  Vault is the only product combining claim resolution with the strategy
  pattern; the audit names it the reference for invoice's disposable demo.
- **Sweep JST/UTC regression test**: `tests/Demo/SweepDemoScriptTest.php`
  pins both timezones. invoice and deal have no equivalent despite the fleet
  hitting this trap twice.
- **Installer self-deletes** after completion:
  `public_html/install.php:1121` (`@unlink(__FILE__)` + marker + probe).
  The audit recommends invoice/clear adopt this deal/vault shape.
- **`/demo/standard` + `/demo/guided` coexistence**: disposable admin-seat
  demo and fixed viewer-seat walkthrough live side by side
  (`src/Demo/GuidedDemoRouteRegistrar.php:33`) — unique in the fleet.

## Corrections made during re-verification

- The cross-product report loosely said "demo org `created_at`" for finding 7;
  in fact **all** organizations are written with local time via
  `PdoOrganizationRepository` — demo orgs simply share that path.
- The 24 h TTL is mirrored in `src/Demo/SeatFixedDemoHandler.php:45`, so the
  #148 fix must update both sites together.
