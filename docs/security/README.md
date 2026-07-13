# Security assessments — NeNe Vault

Record of **authorized self / maintainer-run** security diagnostics for NeNe
Vault. These are internal, isolated-environment assessments — **not** third-party
penetration tests. No production host is ever targeted.

## History

| Date | Report | Scope | Result |
|---|---|---|---|
| 2026-07-14 | [Red-team round 2](2026-07-14-redteam-assessment.md) | nene-records parity: unauthenticated admin GET (F-01), cross-tenant JWT-vs-host replay (F-02); org-binding defense-in-depth | **0 EXPOSED** — both sibling finding types absent (main 55 + tenant 4 assertions); unconditional org-scope binding hardening + regression |
| 2026-07-13 | [Black-box live ATK](2026-07-13-assessment.md) | Auth/JWT, tenant isolation, RBAC, upload/download, export, storage-path disclosure, headers/CORS, compliance invariants | **0 Critical / 0 High / 0 Medium / 0 Low EXPOSED** (48 assertions); 2 low info-banner INFOs fixed + re-verified |

Each report documents: the tested app/framework version and scope, methodology,
an environment table, a per-category attack-results table (with `BLOCKED` /
`SAFE` / `EXPOSED` verdicts), verified-safe controls, remediation of any
observation (with the fix location and re-verification), and honest residual
notes for anything **not** implemented or deferred.

Prior to 2026-07-13 the only security artifact was the
`../review/middleware-security.md` self-review checklist; this directory adds the
first live-fire evidence.

## Reproduction harness

A disposable Docker stack + attack runner lives in [`harness/`](harness/):

```bash
export NENE2_LOCAL_JWT_SECRET='sec-assessment-fixed-secret-2026-07'
docker compose -p vaultsec up -d app
SEC_PROJECT=vaultsec BASE=http://localhost:8600 ./docs/security/harness/seed.sh
SEC_PROJECT=vaultsec BASE=http://localhost:8600 ./docs/security/harness/probe.sh
docker compose -p vaultsec down -v      # teardown (destroys all data)
```

> ⚠️ Authorized self-owned, isolated-environment testing only. Never point the
> harness at `vault.ayane.co.jp` or any live host. No DoS / destructive payloads.
> Secrets, minted tokens and run logs are git-ignored (`harness/.gitignore`).

See [`harness/README.md`](harness/README.md) for details.
