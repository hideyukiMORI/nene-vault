# Security assessment harness — NeNe Vault

Reproducible **disposable** environment for the authorized self / maintainer-run
security assessment (see `../2026-07-13-assessment.md`). It stands up the real
application in a throwaway Docker stack, seeds two organizations with one
received document each, and fires a black-box live attack (ATK) battery.

> ⚠️ **Authorized self-owned, isolated-environment testing only.** Never point
> this at production (`vault.ayane.co.jp` or any live host). No DoS / destructive
> payloads. This is a maintainer-run diagnostic, **not** a third-party pentest.

## Files

| File | Role |
|---|---|
| `mint.php`  | Mints fleet-standard HS256 bearer tokens (`sub`/`role`/`org`/`iat`/`exp`) with the running app's own secret, so any tenant/role can be driven directly. Also emits attack tokens (`--forge-none`, `--tamper-role`, `--exp=-60`). |
| `seed.sh`   | Creates org 2 (`acme`) beside the default org 1 and uploads one org-tagged PDF into each — the data cross-tenant/RBAC/export attacks target. |
| `probe.sh`  | The live ATK runner. Sections A–K; prints `[PASS]`/`[VULN]`/`[INFO]` and a `SUMMARY: PASS/VULN/INFO` line. Exit non-zero if any `VULN`. |
| `.gitignore`| Keeps real secrets / minted tokens / run logs out of git. |

The harness reuses the repository's own `docker-compose.yml` (SQLite, port 8600)
rather than a separate DB image — that is the authentic reproduction of the
shipped stack. A fixed JWT secret is pinned only for the disposable run so
`mint.php` and the app agree.

## Run

```bash
# From the repo root. Pin a throwaway secret so mint.php == app.
export NENE2_LOCAL_JWT_SECRET='sec-assessment-fixed-secret-2026-07'

# 1. Bring up a disposable stack (namespaced project so volumes are isolated).
docker compose -p vaultsec up -d app
curl -fs http://localhost:8600/health >/dev/null && echo "up"

# 2. Seed two orgs + one document each.
SEC_PROJECT=vaultsec BASE=http://localhost:8600 ./docs/security/harness/seed.sh

# 3. Fire the live ATK battery.
SEC_PROJECT=vaultsec BASE=http://localhost:8600 ./docs/security/harness/probe.sh
```

Expected tail on a clean tree: `SUMMARY: PASS=48  VULN=0  INFO=0`.

## Teardown (destroys all data + volumes)

```bash
docker compose -p vaultsec down -v
```

## Notes

- **Throwaway credentials only.** The seeded admin (`admin@example.com` /
  `changeme123`) and the pinned JWT secret exist only inside the disposable
  stack. Never reuse them anywhere real.
- The named storage volume is created root-owned by Docker; `seed.sh` chowns it
  to `www-data` once so uploads can write. This is an environment quirk, not an
  application behaviour.
- `probe.sh` is intentionally mutating (it voids a document, tampers a stored
  byte to exercise the SHA-256 check, and trips the login throttle). Re-run
  after `down -v` + `seed.sh` for a clean result.
- Minting tokens is only possible because the operator already holds
  `NENE2_LOCAL_JWT_SECRET` for their own stack; it grants nothing a real login
  would not, and is not an auth bypass against a secret you do not control.
