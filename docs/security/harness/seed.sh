#!/usr/bin/env bash
# =============================================================================
# seed.sh — deterministic two-org fixture for the live ATK (probe.sh).
#
# Creates a second organization (Acme, id 2) alongside the default seeded org
# (id 1) and uploads one received-document PDF into each, so cross-tenant
# isolation, RBAC and export attacks have real, org-tagged data to target.
#
# Idempotent-ish: safe to re-run after a fresh `docker compose up`. Against an
# already-seeded DB the org-create step 409s harmlessly and a duplicate upload
# is skipped by SHA-256.
#
# Usage: SEC_PROJECT=vaultsec BASE=http://localhost:8600 ./docs/security/harness/seed.sh
# =============================================================================
set -eu

BASE="${BASE:-http://localhost:8600}"
SEC_PROJECT="${SEC_PROJECT:-vaultsec}"
MINT() { docker compose -p "$SEC_PROJECT" exec -T app php docs/security/harness/mint.php "$@" --raw; }
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

# The named storage volume is created root-owned by Docker; the app runs as
# www-data. Grant write once (idempotent) so uploads can create the org tree.
docker compose -p "$SEC_PROJECT" exec -T -u root app sh -c \
  'chown -R www-data:www-data /var/www/html/storage /var/www/html/var 2>/dev/null || true'

SUPER=$(MINT --sub=1 --role=superadmin --org=null)
echo "[seed] creating org 2 (acme)…"
curl -s -o /dev/null -X POST "$BASE/admin/organizations" -H "Authorization: Bearer $SUPER" \
  -H 'Content-Type: application/json' -d '{"name":"Acme Corp","slug":"acme"}'

A1=$(MINT --sub=1 --role=admin --org=1)
A2=$(MINT --sub=1 --role=admin --org=2)

printf '%%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%% ORG1 CONFIDENTIAL invoice\n%%%%EOF\n' > "$TMP/org1.pdf"
printf '%%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%% ORG2 SECRET contract — must never leak to org1\n%%%%EOF\n' > "$TMP/org2.pdf"

echo "[seed] uploading org1 document…"
curl -s -o /dev/null -X POST "$BASE/admin/vault/documents" -H "Authorization: Bearer $A1" \
  -F "file=@$TMP/org1.pdf;type=application/pdf" -F "counterparty_name=Org1 Vendor" \
  -F "category=invoice_received" -F "amount_cents=11100"
echo "[seed] uploading org2 document…"
curl -s -o /dev/null -X POST "$BASE/admin/vault/documents" -H "Authorization: Bearer $A2" \
  -F "file=@$TMP/org2.pdf;type=application/pdf" -F "counterparty_name=Org2 Vendor" \
  -F "category=contract" -F "amount_cents=22200"

echo "[seed] done. org1 + org2 each hold one document."
