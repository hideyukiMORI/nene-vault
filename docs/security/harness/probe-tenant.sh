#!/usr/bin/env bash
# =============================================================================
# probe-tenant.sh — cross-tenant JWT-vs-host replay test (nene-records F-02
#                   parity), self-contained: boot → seed → assert → teardown.
#
# Boots a DISPOSABLE stack in subdomain-resolution mode and proves that a
# signed `org` claim binds a bearer token to its own tenant regardless of the
# Host header — i.e. replaying org-A's JWT against org-B's host does NOT read
# org-B (the sibling-product F-02 leak type). A superadmin token (no org claim)
# correctly follows the host (cross-tenant by design).
#
# SCOPE: localhost disposable stack ONLY. Never against production. Needs host
# port 8600 free. Requires NENE2_LOCAL_JWT_SECRET to be exported (same value
# mint.php will sign with).
#
# Usage:
#   export NENE2_LOCAL_JWT_SECRET='sec-assessment-fixed-secret-2026-07'
#   ./docs/security/harness/probe-tenant.sh
# =============================================================================
set -uo pipefail

PROJECT=vaultsec-tenant
BASE="http://localhost:8600"
DOMAIN=vault.test
pass=0; fail=0
check() { if [ "$2" = "$3" ]; then echo "  ✅ $1 ($3)"; pass=$((pass+1)); else echo "  ❌ $1 (expected [$2], got [$3])"; fail=$((fail+1)); fi }

cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)" || exit 1
: "${NENE2_LOCAL_JWT_SECRET:?export NENE2_LOCAL_JWT_SECRET first}"

cleanup() { docker compose -p "$PROJECT" down -v >/dev/null 2>&1; }
trap cleanup EXIT

echo "== boot disposable stack (subdomain resolution, base=$DOMAIN) =="
TENANT_RESOLUTION=subdomain BASE_DOMAIN="$DOMAIN" \
  docker compose -p "$PROJECT" up -d app >/dev/null 2>&1
for i in $(seq 1 40); do [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/health")" = 200 ] && break; sleep 1; done

echo "== seed org1(default) + org2(acme), one doc each =="
SEC_PROJECT="$PROJECT" BASE="$BASE" bash docs/security/harness/seed.sh >/dev/null 2>&1

MINT() { docker compose -p "$PROJECT" exec -T app php docs/security/harness/mint.php "$@" --raw; }
SUPER=$(MINT --sub=1 --role=superadmin --org=null)
A1=$(MINT --sub=1 --role=admin --org=1)   # org1 = default
A2=$(MINT --sub=1 --role=admin --org=2)   # org2 = acme
seen() { curl -s -H "Authorization: Bearer $1" ${2:+-H "Host: $2"} "$BASE/admin/vault/documents" \
  | python3 -c 'import sys,json;d=json.load(sys.stdin);print(",".join(i["counterparty_name"] for i in d.get("items",[])) if isinstance(d,dict) and "items" in d else "ERR")' 2>/dev/null; }

echo "== host strategy resolves (superadmin, no org claim) =="
check "superadmin + Host acme.$DOMAIN sees org2"      "Org2 Vendor" "$(seen "$SUPER" "acme.$DOMAIN")"
check "superadmin + Host default.$DOMAIN sees org1"   "Org1 Vendor" "$(seen "$SUPER" "default.$DOMAIN")"

echo "== F-02 parity: signed org claim binds the token, host cannot override =="
check "org1 JWT + Host acme.$DOMAIN stays org1 (no org2 leak)"   "Org1 Vendor" "$(seen "$A1" "acme.$DOMAIN")"
check "org2 JWT + Host default.$DOMAIN stays org2 (no org1 leak)" "Org2 Vendor" "$(seen "$A2" "default.$DOMAIN")"

echo
echo "== RESULT: $pass passed, $fail failed =="
[ "$fail" -eq 0 ]
