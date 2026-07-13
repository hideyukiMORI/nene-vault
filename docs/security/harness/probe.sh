#!/usr/bin/env bash
# =============================================================================
# probe.sh — NeNe Vault black-box live attack runner (authorized self /
#            maintainer-run assessment).
#
# SCOPE: localhost disposable stack ONLY. Never point at production
#        (vault.ayane.co.jp or any live host). No DoS / destructive payloads.
#
# Assumes the disposable stack is up (see harness/README.md), reachable at
# $BASE, seeded with two organizations and one document each. Mints bearer
# tokens with harness/mint.php (same secret as the running app) to drive every
# tenant/role combination.
#
# Usage:
#   SEC_PROJECT=vaultsec BASE=http://localhost:8600 ./docs/security/harness/probe.sh
# =============================================================================
set -u

BASE="${BASE:-http://localhost:8600}"
SEC_PROJECT="${SEC_PROJECT:-vaultsec}"

pass=0; vuln=0; info=0
ok()   { echo "  [PASS] $*"; pass=$((pass+1)); }
bad()  { echo "  [VULN] $*"; vuln=$((vuln+1)); }
note() { echo "  [INFO] $*"; info=$((info+1)); }
sec()  { echo; echo "== $* =="; }

MINT() { docker compose -p "$SEC_PROJECT" exec -T app php docs/security/harness/mint.php "$@" --raw; }
# HTTP status only
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
# response body
body() { curl -s "$@"; }
# raw headers
hdrs() { curl -s -D - -o /dev/null "$@"; }

echo "### NeNe Vault live ATK — $BASE"

# ── Tokens ───────────────────────────────────────────────────────────────────
SUPER=$(MINT --sub=1 --role=superadmin --org=null)
A1=$(MINT --sub=1 --role=admin  --org=1)     # org1 admin
V1=$(MINT --sub=1 --role=viewer --org=1)     # org1 viewer
M1=$(MINT --sub=1 --role=member --org=1)     # org1 member
A2=$(MINT --sub=1 --role=admin  --org=2)     # org2 admin

# Discover one document id per org (search endpoint)
first_id() { python3 -c 'import sys,json;d=json.load(sys.stdin);print(d["items"][0]["id"] if d.get("items") else "")' 2>/dev/null; }
first_ver() { python3 -c 'import sys,json;d=json.load(sys.stdin);v=d.get("versions",[]);print(v[0]["id"] if v else "")' 2>/dev/null; }
DOC1=$(body -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents" | first_id)
VER1=$(body -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC1/history" | first_ver)
DOC2=$(body -H "Authorization: Bearer $A2" "$BASE/admin/vault/documents" | first_id)
VER2=$(body -H "Authorization: Bearer $A2" "$BASE/admin/vault/documents/$DOC2/history" | first_ver)
echo "org1 doc=$DOC1 ver=$VER1 | org2 doc=$DOC2 ver=$VER2"

# ── A. Authentication / JWT verification ─────────────────────────────────────
sec "A. Authentication / JWT"
c=$(code "$BASE/admin/vault/documents"); [ "$c" = 401 ] && ok "no token → 401" || bad "no token → $c"
c=$(code -H "Authorization: Bearer garbage" "$BASE/admin/vault/documents"); [ "$c" = 401 ] && ok "malformed token → 401" || bad "malformed → $c"
NONE=$(MINT --sub=1 --role=superadmin --org=null --forge-none)
c=$(code -H "Authorization: Bearer $NONE" "$BASE/admin/vault/documents"); [ "$c" = 401 ] && ok "alg:none forgery → 401" || bad "alg:none → $c (EXPOSED)"
TAMP=$(MINT --sub=1 --role=viewer --org=1 --tamper-role=superadmin)
c=$(code -H "Authorization: Bearer $TAMP" "$BASE/admin/organizations"); [ "$c" = 401 ] && ok "payload tamper (role→superadmin) → 401" || bad "tamper → $c (EXPOSED)"
EXP=$(MINT --sub=1 --role=admin --org=1 --exp=-60)
c=$(code -H "Authorization: Bearer $EXP" "$BASE/admin/vault/documents"); [ "$c" = 401 ] && ok "expired token → 401" || bad "expired → $c (EXPOSED)"
WRONG=$(MINT --sub=1 --role=admin --org=1 --secret=not-the-real-secret)
c=$(code -H "Authorization: Bearer $WRONG" "$BASE/admin/vault/documents"); [ "$c" = 401 ] && ok "wrong-secret signature → 401" || bad "wrong-secret → $c (EXPOSED)"

# ── B. Tenant isolation (cross-org IDOR) ─────────────────────────────────────
sec "B. Tenant isolation (org1 token vs org2 data)"
c=$(code -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC2"); [ "$c" = 404 ] && ok "org1 admin GET org2 doc → 404" || bad "cross-org GET → $c (EXPOSED)"
c=$(code -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC2/versions/$VER2/download"); [ "$c" = 404 ] && ok "org1 admin download org2 file → 404" || bad "cross-org download → $c (EXPOSED)"
c=$(code -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC2/history"); [ "$c" = 404 ] && ok "org1 admin GET org2 history → 404" || bad "cross-org history → $c (EXPOSED)"
# Use fully-valid payloads so the request reaches the use-case org check (not stopped at input validation)
c=$(code -X PATCH -H "Authorization: Bearer $A1" -H 'Content-Type: application/json' -d '{"counterparty_name":"HACKED","category":"other"}' "$BASE/admin/vault/documents/$DOC2/metadata"); [ "$c" = 404 ] && ok "org1 admin PATCH org2 metadata → 404" || bad "cross-org PATCH → $c (EXPOSED)"
c=$(code -X POST -H "Authorization: Bearer $A1" -H 'Content-Type: application/json' -d '{"void_reason":"atk"}' "$BASE/admin/vault/documents/$DOC2/void"); [ "$c" = 404 ] && ok "org1 admin void org2 doc → 404" || bad "cross-org void → $c (EXPOSED)"
# confirm org2 doc is untouched by the cross-org attempts
cp=$(body -H "Authorization: Bearer $A2" "$BASE/admin/vault/documents/$DOC2" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(d.get("counterparty_name","")+"|"+d.get("status",""))' 2>/dev/null)
[ "$cp" = "Org2 Vendor|active" ] && ok "org2 doc unchanged after cross-org write attempts ($cp)" || bad "org2 doc mutated cross-org: $cp (EXPOSED)"
# search must only return own org
n=$(body -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents?counterparty_name=Org2" | python3 -c 'import sys,json;print(len(json.load(sys.stdin).get("items",[])))' 2>/dev/null)
[ "$n" = 0 ] && ok "org1 search cannot see org2 rows (0 hits)" || bad "org1 search saw $n org2 rows (EXPOSED)"

# Cross-org USER management (role escalation across tenants). Create a user in
# org1, then try to read / re-role / delete it with an org2 admin token.
UID1=$(body -X POST -H "Authorization: Bearer $A1" -H 'Content-Type: application/json' \
  -d '{"email":"victim-'"$RANDOM"'@org1.example","password":"Passw0rd!23","role":"viewer"}' \
  "$BASE/admin/users" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("id",""))' 2>/dev/null)
if [ -n "$UID1" ]; then
  c=$(code -H "Authorization: Bearer $A2" "$BASE/admin/users/$UID1"); [ "$c" = 404 ] && ok "org2 admin GET org1 user → 404" || bad "cross-org user read → $c (EXPOSED)"
  c=$(code -X PATCH -H "Authorization: Bearer $A2" -H 'Content-Type: application/json' -d '{"role":"admin"}' "$BASE/admin/users/$UID1"); [ "$c" = 404 ] && ok "org2 admin escalate org1 user role → 404" || bad "cross-org role escalation → $c (EXPOSED)"
  c=$(code -X DELETE -H "Authorization: Bearer $A2" "$BASE/admin/users/$UID1"); [ "$c" = 404 ] && ok "org2 admin DELETE org1 user → 404" || bad "cross-org user delete → $c (EXPOSED)"
  r=$(body -H "Authorization: Bearer $A1" "$BASE/admin/users/$UID1" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("role",""))' 2>/dev/null)
  [ "$r" = viewer ] && ok "org1 user role intact after cross-org attempts ($r)" || bad "org1 user role changed cross-org: $r (EXPOSED)"
else
  note "could not create org1 user for cross-org user test"
fi

# ── C. RBAC / role boundaries ────────────────────────────────────────────────
sec "C. RBAC / role boundaries"
c=$(code -X POST -H "Authorization: Bearer $V1" -F "file=@/etc/hostname" -F "counterparty_name=x" -F "category=other" "$BASE/admin/vault/documents"); [ "$c" = 403 ] && ok "viewer upload → 403" || bad "viewer upload → $c (EXPOSED)"
c=$(code -X POST -H "Authorization: Bearer $V1" "$BASE/admin/vault/documents/$DOC1/void"); [ "$c" = 403 ] && ok "viewer void → 403" || bad "viewer void → $c (EXPOSED)"
c=$(code -H "Authorization: Bearer $V1" "$BASE/admin/vault/export?format=csv"); [ "$c" = 403 ] && ok "viewer export → 403" || bad "viewer export → $c (EXPOSED)"
c=$(code -H "Authorization: Bearer $V1" "$BASE/admin/users"); [ "$c" = 403 ] && ok "viewer list users → 403" || bad "viewer users → $c (EXPOSED)"
c=$(code -H "Authorization: Bearer $V1" "$BASE/admin/audit-events"); [ "$c" = 403 ] && ok "viewer audit-events → 403" || bad "viewer audit → $c (EXPOSED)"
c=$(code -H "Authorization: Bearer $M1" "$BASE/admin/vault/export?format=csv"); [ "$c" = 403 ] && ok "member export → 403" || bad "member export → $c (EXPOSED)"
c=$(code -X PATCH -H "Authorization: Bearer $M1" -H 'Content-Type: application/json' -d '{"retention_years":1}' "$BASE/admin/vault/settings"); [ "$c" = 403 ] && ok "member change settings → 403" || bad "member settings → $c (EXPOSED)"
c=$(code -H "Authorization: Bearer $A1" "$BASE/admin/organizations"); [ "$c" = 403 ] && ok "org admin list orgs (superadmin-only) → 403" || bad "admin org-mgmt → $c (EXPOSED)"
c=$(code -X GET -H "Authorization: Bearer $V1" "$BASE/admin/vault/documents/$DOC1"); [ "$c" = 200 ] && ok "viewer read own doc → 200 (allowed)" || note "viewer read own doc → $c"

# ── D. SQL / search injection ────────────────────────────────────────────────
sec "D. SQL injection (search params, parameterized?)"
for inj in "%27%20OR%201%3D1--" "%27%3B%20DROP%20TABLE%20vault_documents%3B--" "%27%20UNION%20SELECT%20password_hash%20FROM%20users--"; do
  c=$(code -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents?counterparty_name=$inj")
  [ "$c" = 200 ] || [ "$c" = 422 ] && ok "injection payload handled ($c, no 500)" || bad "injection → $c"
done
# prove table intact
n=$(body -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents" | python3 -c 'import sys,json;print(len(json.load(sys.stdin).get("items",[])))' 2>/dev/null)
[ -n "$n" ] && [ "$n" -ge 1 ] && ok "vault_documents intact after injection attempts ($n rows)" || bad "table state suspicious ($n)"

# ── E. Path traversal / storage-path disclosure ──────────────────────────────
sec "E. Path traversal / storage-path disclosure"
c=$(code -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/..%2f..%2f..%2fetc%2fpasswd"); { [ "$c" = 404 ] || [ "$c" = 400 ]; } && ok "traversal in doc id → $c" || bad "traversal doc id → $c"
c=$(code -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC1/versions/..%2f..%2fpasswd/download"); { [ "$c" = 404 ] || [ "$c" = 400 ]; } && ok "traversal in version id → $c" || bad "traversal version id → $c"
# storage path must never appear in any document response
resp=$(body -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC1")
echo "$resp" | grep -qiE 'storage/vault|/var/www|file_path|absolute' && bad "storage path/file_path leaked in doc response (EXPOSED)" || ok "no storage path/file_path in doc response"
# download headers must not leak storage path
h=$(hdrs -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC1/versions/$VER1/download")
echo "$h" | grep -qiE 'storage/vault|/var/www|X-.*Path' && bad "storage path leaked in download headers (EXPOSED)" || ok "no storage path in download headers"

# ── F. Upload MIME / content-type enforcement ────────────────────────────────
sec "F. Upload MIME allowlist / download hardening"
printf '<html><script>alert(1)</script></html>' > /tmp/vaultsec_xss.html
c=$(code -X POST -H "Authorization: Bearer $A1" -F "file=@/tmp/vaultsec_xss.html;type=text/html" -F "counterparty_name=x" -F "category=other" "$BASE/admin/vault/documents")
[ "$c" = 415 ] && ok "text/html upload rejected → 415" || bad "text/html upload → $c (EXPOSED: MIME allowlist)"
# spoofed mime (html body, declared application/pdf): download must force attachment + nosniff
c=$(code -X POST -H "Authorization: Bearer $A1" -F "file=@/tmp/vaultsec_xss.html;type=application/pdf" -F "counterparty_name=spoof" -F "category=other" "$BASE/admin/vault/documents")
if [ "$c" = 201 ]; then
  SDOC=$(body -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents?counterparty_name=spoof" | first_id)
  SVER=$(body -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$SDOC/history" | first_ver)
  h=$(hdrs -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$SDOC/versions/$SVER/download")
  echo "$h" | grep -qi 'Content-Disposition: attachment' && ok "spoofed file served as attachment" || bad "spoofed file not attachment (EXPOSED)"
  echo "$h" | grep -qi 'X-Content-Type-Options: nosniff' && ok "download sets nosniff" || bad "download missing nosniff (EXPOSED)"
else
  note "spoofed-mime upload returned $c"
fi

# ── G. Security headers / CORS ───────────────────────────────────────────────
sec "G. Security headers / CORS"
h=$(hdrs "$BASE/health")
echo "$h" | grep -qi 'X-Content-Type-Options: nosniff' && ok "X-Content-Type-Options present" || bad "missing X-Content-Type-Options"
echo "$h" | grep -qi 'X-Frame-Options' && ok "X-Frame-Options present" || note "no X-Frame-Options on /health"
echo "$h" | grep -qiE '^Server: (Apache|nginx)?/?[0-9]' && note "Server header reveals version: $(echo "$h" | grep -i '^Server:')" || ok "no version-revealing Server header"
echo "$h" | grep -qi '^X-Powered-By:' && note "X-Powered-By reveals PHP version: $(echo "$h" | grep -i '^X-Powered-By:')" || ok "no X-Powered-By version banner"
h=$(hdrs -H 'Origin: https://evil.example' "$BASE/health")
echo "$h" | grep -qi 'Access-Control-Allow-Origin: \*' && bad "CORS reflects * (check prod config)" || ok "no wildcat ACAO for evil origin"

# ── H. Error handling / info disclosure ──────────────────────────────────────
sec "H. Error handling / info disclosure"
resp=$(body -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/does-not-exist")
echo "$resp" | grep -qiE 'SQLSTATE|PDO|stack trace|/var/www|\.php on line' && bad "error body leaks internals (EXPOSED)" || ok "404 error body clean (no SQL/stack/path)"
echo "$resp" | python3 -c 'import sys,json;json.load(sys.stdin)' 2>/dev/null && ok "error is valid Problem Details JSON" || note "error not JSON"

# ── I. CSV / export formula injection + scope ────────────────────────────────
sec "I. Export CSV formula-injection neutralization"
# upload a doc whose counterparty starts with a formula trigger
printf '%%PDF-1.4\n%% csvinj\n%%%%EOF' > /tmp/vaultsec_csv.pdf
code -X POST -H "Authorization: Bearer $A1" -F "file=@/tmp/vaultsec_csv.pdf;type=application/pdf" -F 'counterparty_name==2+5+cmd|calc' -F "category=other" -F "confirm_duplicate=1" "$BASE/admin/vault/documents" >/dev/null
# NB: /admin/vault/export is POST + JSON body (a GET yields no CSV — round-1 probe bug, fixed here).
csv=$(body -X POST -H "Authorization: Bearer $A1" -H 'Content-Type: application/json' -d '{"format":"csv"}' "$BASE/admin/vault/export")
verdict=$(printf '%s' "$csv" | python3 -c '
import sys,csv,io
data=sys.stdin.buffer.read().decode("utf-8-sig")
rows=list(csv.reader(io.StringIO(data)))
bad=[]
for r in rows:
    for cell in r:
        if cell and cell[0] in "=+@" or (cell[:1]=="-" and any(ch in cell for ch in "()|")):
            bad.append(cell)
# after CsvWriter neutralization, no raw cell should START with a formula lead char
print("RAW_FORMULA" if bad else "NEUTRALIZED")
print("|".join(bad)[:120])
' 2>/dev/null)
v1=$(echo "$verdict" | head -1)
if [ "$v1" = NEUTRALIZED ]; then ok "no exported cell begins with a formula lead char (=+@-) — CsvWriter neutralized"; else bad "raw formula cell survived export: $(echo "$verdict" | tail -1) (EXPOSED)"; fi

# ── J. Hard-rule invariants (no hard-delete, SHA-256 verify) ─────────────────
sec "J. Compliance invariants"
code -X POST -H "Authorization: Bearer $A1" -H 'Content-Type: application/json' -d '{"void_reason":"assessment"}' "$BASE/admin/vault/documents/$DOC1/void" >/dev/null
st=$(body -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC1" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("status",""))' 2>/dev/null)
[ "$st" = voided ] && ok "void marks status=voided (row retained, no hard-delete)" || bad "post-void status=$st (expected voided)"
code -X POST -H "Authorization: Bearer $A1" -H 'Content-Type: application/json' -d '{"restore_reason":"assessment"}' "$BASE/admin/vault/documents/$DOC1/restore" >/dev/null
# tamper the stored byte then download → must fail SHA-256 integrity
docker compose -p "$SEC_PROJECT" exec -T -u root app sh -c "f=\$(find /var/www/html/storage/vault/vault/1/$DOC1 -type f | head -1); echo TAMPERED >> \"\$f\"" 2>/dev/null
c=$(code -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC1/versions/$VER1/download")
{ [ "$c" = 409 ] || [ "$c" = 500 ] || [ "$c" = 422 ]; } && ok "tampered file blocked on download (SHA-256 verify) → $c" || bad "tampered file served → $c (EXPOSED)"

# ── K. Login throttle / brute force ──────────────────────────────────────────
sec "K. Login throttle (5/15min per email+IP)"
last=200
for i in 1 2 3 4 5 6 7; do
  last=$(code -X POST "$BASE/admin/auth/login" -H 'Content-Type: application/json' -d '{"email":"admin@example.com","password":"wrong"}')
done
[ "$last" = 429 ] && ok "brute force throttled → 429 after repeated failures" || bad "no throttle (last=$last)"

# ── L. Unauthenticated admin-API sweep (nene-records F-01 Critical parity) ────
sec "L. Unauthenticated admin-API GET sweep (every admin read must be 401)"
UNAUTH_OK=1
for p in \
  "/admin/vault/documents" \
  "/admin/vault/documents/$DOC1" \
  "/admin/vault/documents/$DOC1/history" \
  "/admin/vault/documents/$DOC1/versions/$VER1/download" \
  "/admin/vault/documents/$DOC1/ocr-suggest" \
  "/admin/vault/settings" \
  "/admin/audit-events" \
  "/admin/users" \
  "/admin/users/1" \
  "/admin/organizations" \
  "/admin/organizations/1" ; do
  c=$(code "$BASE$p")
  if [ "$c" != 401 ]; then bad "UNAUTH GET $p → $c (EXPOSED: unauthenticated admin read)"; UNAUTH_OK=0; fi
done
[ "$UNAUTH_OK" = 1 ] && ok "all 11 admin GET endpoints require auth (401 unauthenticated)"
# write/verb surfaces unauthenticated
c=$(code -I "$BASE/admin/vault/documents"); [ "$c" = 401 ] && ok "unauth HEAD documents → 401" || bad "unauth HEAD → $c (EXPOSED)"
c=$(code -X POST "$BASE/admin/vault/export" -H 'Content-Type: application/json' -d '{"format":"csv"}'); [ "$c" = 401 ] && ok "unauth POST export → 401" || bad "unauth export → $c (EXPOSED)"
# public surface must STAY open (no over-correction)
c=$(code "$BASE/health"); [ "$c" = 200 ] && ok "/health stays public → 200" || bad "/health → $c"
c=$(code -X POST "$BASE/admin/auth/login" -H 'Content-Type: application/json' -d '{}'); { [ "$c" = 422 ] || [ "$c" = 400 ] || [ "$c" = 401 ]; } && ok "/admin/auth/login stays reachable (no bearer needed) → $c" || note "login → $c"

# ── M. Verb/method confusion ─────────────────────────────────────────────────
sec "M. Verb / method confusion"
c=$(code -X GET -H "Authorization: Bearer $A1" "$BASE/admin/vault/export"); { [ "$c" = 404 ] || [ "$c" = 405 ] || [ "$c" = 403 ]; } && ok "GET on POST-only export → $c (not a data leak)" || bad "GET export → $c"
c=$(code -X DELETE -H "Authorization: Bearer $A1" "$BASE/admin/vault/documents/$DOC1"); { [ "$c" = 404 ] || [ "$c" = 405 ]; } && ok "DELETE document (no such route) → $c" || note "DELETE document → $c"

echo
echo "================================================================"
echo "SUMMARY: PASS=$pass  VULN=$vuln  INFO=$info"
echo "================================================================"
[ "$vuln" = 0 ]
