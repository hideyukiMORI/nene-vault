# Security Assessment — Red-team Round 2 (2026-07-14)

**Type:** Authorized self / maintainer-run assessment — black-box live attack
(ATK) + white-box review. **Not a third-party penetration test.**

**App version at test:** `nene-vault` @ `cb96a7a` (branch base `origin/main`,
round-1 #195 merged) · NENE2 v1.10.0 · PHP 8.4.22 · Apache 2.4.67.

**Why this round.** Round 1 (`2026-07-13-assessment.md`, PR #195) established
`EXPOSED 0` across a broad battery. This round is a **targeted red-team**: the
sibling product **nene-records** completed the same assessment on 2026-07-13 and
found two *real, exploited* vulnerability types
(`nene-records/docs/security/2026-07-13-assessment.md`). This report verifies,
by live fire, that **NeNe Vault does not have either type**, hardens the one
latent-fragility that maps to the sibling's root cause, and corrects one round-1
probe gap.

**Scope / target.** Disposable local Docker stack (`docs/security/harness/`),
project `vaultsec` / `vaultsec-tenant`, port 8600. **No production host**
(`vault.ayane.co.jp` or any live system) was touched; no DoS/destructive payloads.

## Result

**0 Critical / 0 High / 0 Medium / 0 Low `EXPOSED`.**

Both nene-records finding types were probed live and are **absent** in Vault:

| nene-records finding | Severity there | Vault verdict | Evidence |
|---|---|---|---|
| **F-01** Unauthenticated read of the admin API (webhook secret / drafts) | Critical | 🚫 **NOT PRESENT** | All 11 admin GET endpoints → 401 unauthenticated (blocklist auth, fail-closed) |
| **F-02** Cross-tenant read via JWT replay to another org's host | Medium | 🚫 **NOT PRESENT** | org-A JWT + org-B `Host` → org-A data only; claim binds tenant over host |
| **F-03** Version-disclosure headers | Low | ✅ already fixed round 1 (PR #195) | `Server: Apache`, no `X-Powered-By` |

One **defense-in-depth** hardening was applied in-branch (organization-scope
binding made unconditional in `CapabilityMiddleware`, mirroring the sibling's
F-02 fix) and pinned with a unit regression; it closes a *latent* fragility that
was **not live-exploitable** in Vault (claim-based resolution already protected
every route). Post-change: main probe `PASS=55 VULN=0 INFO=0`, tenant probe
`4/4`, `composer check` green (400 tests).

## Methodology

- **Black-box live ATK.** Real app booted in a throwaway stack, seeded with two
  orgs (`default` #1, `acme` #2) + one document each, driven over HTTP.
  `harness/probe.sh` runs the main battery (single-tenant mode);
  `harness/probe-tenant.sh` boots a second stack in **subdomain-resolution mode**
  to exercise the host-vs-claim binding.
- **White-box review.** `BearerTokenMiddleware` (blocklist), `OrgResolverMiddleware`
  (claim-based #141), `CapabilityMiddleware` / `CapabilityResolver`, and the full
  route registrar inventory were read to establish the root-cause difference from
  nene-records.
- **Regression.** The hardening is pinned by `tests/Auth/CapabilityMiddlewareTest.php`;
  `composer check` (400 tests / 1047 assertions, PHPStan L8, CS-Fixer, OpenAPI,
  MCP, conformance) is green.

## Findings & verification

### F-01 parity — Unauthenticated admin API read → NOT PRESENT

**nene-records root cause.** `AdminApiAuthMiddleware` protected only *non-GET*
methods; any admin resource whose prefix was never added to the allow-list
leaked its GET response (webhook signing secret, drafts, full export) to anonymous
users.

**Vault design.** `BearerTokenMiddleware` uses a **blocklist** (#157): every path
requires a bearer token **except** the four exact public paths (`/health`,
`/admin/auth/login`, `/demo/standard`, `/demo/guided`). Adding a route makes it
protected by default; the public surface is pinned by `PublicSurfaceBoundaryTest`.

**Live result (unauthenticated, no token):**

```
GET /admin/vault/documents                         → 401
GET /admin/vault/documents/{id}                     → 401
GET /admin/vault/documents/{id}/history             → 401
GET /admin/vault/documents/{id}/versions/{v}/download → 401
GET /admin/vault/documents/{id}/ocr-suggest         → 401
GET /admin/vault/settings                           → 401
GET /admin/audit-events                             → 401
GET /admin/users        /admin/users/{id}           → 401
GET /admin/organizations /admin/organizations/{id}  → 401
HEAD /admin/vault/documents  ·  POST /admin/vault/export → 401
/health → 200   ·   /admin/auth/login → reachable (422 on empty body)
```

All 11 admin GET endpoints (and write/verb surfaces) require authentication; the
public surface stays open. **No unauthenticated admin read exists.** (`probe.sh`
section L.)

### F-02 parity — Cross-tenant JWT replay to another org's host → NOT PRESENT

**nene-records root cause.** `CapabilityMiddleware` ran its "JWT `org` must equal
resolved org" check **only after** `CapabilityResolver` returned a capability;
routes with no capability mapping skipped the check, so an org-A user replaying
their JWT as a bearer against org-B's host read org-B's data.

**Vault design.** Tenancy is resolved from the **signed `org` claim** first
(`OrgResolverMiddleware`, #141) — the host is only a fallback for tokens without
a tenant (superadmin). Repositories scope every query by the resolved org. So a
non-superadmin token is bound to its own org regardless of Host.

**Live result (subdomain-resolution mode, `probe-tenant.sh`):**

```
# host strategy genuinely resolves per Host (superadmin, no org claim):
superadmin + Host acme.vault.test     → org2 (Org2 Vendor)
superadmin + Host default.vault.test  → org1 (Org1 Vendor)

# signed claim binds the token — host CANNOT override it:
org1 JWT   + Host acme.vault.test     → org1 (Org1 Vendor)   ← no org2 leak
org2 JWT   + Host default.vault.test  → org2 (Org2 Vendor)   ← no org1 leak
```

The superadmin rows prove the host strategy is live (not merely ignored); the
member rows prove the claim wins. **No cross-tenant replay exists.**

### Hardening (defense-in-depth) — unconditional org-scope binding

Although not live-exploitable in Vault, `CapabilityMiddleware` shared the sibling's
*structural* shape: it returned early for a route with no capability mapping,
skipping the org-scope check. A future admin route added without a capability
mapping would then rely solely on `OrgResolverMiddleware` + repo scoping for
tenant safety.

**Change.** The organization-scope check now runs for **every** authenticated,
org-scoped request (non-superadmin, where `nene2.org.id` is resolved),
*independent of* whether a capability is mapped — mirroring the nene-records F-02
fix. Superadmin (cross-org by design) and org-agnostic bypass routes (which never
set `nene2.org.id`) are unaffected; all capability decisions for mapped routes are
unchanged.

**Regression.** `tests/Auth/CapabilityMiddlewareTest.php` (6 tests) pins:
unmapped route + mismatched org → **403 `org-access-denied`** (handler never
reached); unmapped route + matching org → pass; mapped-route capability still
enforced (viewer upload → 403); superadmin bypass; unauthenticated pass-through.

### Round-1 probe gap corrected

Round-1 `probe.sh` exercised CSV formula-injection via `GET /admin/vault/export`,
but that route is **POST**-only — the GET returned no CSV, so the check passed
vacuously. Fixed to `POST … {"format":"csv"}`. Re-run shows the real manifest
with the user-controlled cell **neutralized**: `counterparty_name` of
`=2+5+cmd|calc` is exported as `'=2+5+cmd|calc` (leading apostrophe, UTF-8 BOM
present) by `Nene2\Export\CsvWriter` (ADR 0015). Now backed by real evidence.

## Verified-safe (carried from round 1, re-confirmed)

`PASS=55 VULN=0 INFO=0` across the extended `probe.sh`: JWT (alg:none / tamper /
expired / wrong-secret → 401), tenant isolation incl. cross-org user
role-escalation (→ 404), RBAC boundaries, SQL injection (parameterized), path
traversal + storage-path non-disclosure, MIME allowlist + attachment/nosniff,
security headers/CORS (no version banners), clean Problem Details errors,
SHA-256 tamper detection, no hard-delete, login throttle (429).

## Residual notes (non-blocking, unchanged from round 1)

- **No application-layer at-rest encryption** — stored bytes are plaintext;
  confidentiality at rest relies on host FS / S3 bucket controls. Not
  black-box-reachable. Tracked as follow-up **#196**.
- **`users` repository org filter** — cross-org safety is enforced in the
  use-cases (verified live); repo-layer `organization_id` predicates would be
  belt-and-braces. Tracked as **#197**.

## Conclusion

The two vulnerability types nene-records found and fixed (F-01 unauthenticated
admin read, F-02 cross-tenant JWT replay) are **not present** in NeNe Vault
@ `cb96a7a`, verified by live fire. Vault's blocklist authentication and
claim-based tenant resolution are the structural reasons. A defense-in-depth
hardening (unconditional org-scope binding) was added to remove the latent
fragility that maps to the sibling's root cause, and a round-1 probe gap was
corrected. `EXPOSED 0` stands.

---

*Methodology footer: authorized self / maintainer-run red-team round — main
battery 55 assertions + tenant host-reuse 4 assertions against disposable local
stacks. Reproduce with `docs/security/harness/` (`probe.sh`, `probe-tenant.sh`).
Not a third-party pentest; no production system was targeted.*
