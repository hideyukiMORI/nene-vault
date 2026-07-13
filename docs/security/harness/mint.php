<?php

declare(strict_types=1);

/**
 * mint.php — maintainer-run JWT minting tool for the NeNe Vault security harness.
 *
 * Issues a fleet-standard HS256 bearer token (schema #150: sub / role / org /
 * iat / exp) signed with the SAME secret the running app uses, so an assessor
 * can drive any tenant/role combination against a DISPOSABLE local stack
 * without going through the login endpoint.
 *
 * This is an authorized self / maintainer-run assessment tool. It only works
 * when the operator already controls NENE2_LOCAL_JWT_SECRET (i.e. their own
 * throwaway environment). It grants no capability a legitimate login would not:
 * it is a convenience for reproducing attacks, never an auth bypass against a
 * secret you do not hold.
 *
 * Usage (inside the container, where vendor/ + the secret env live):
 *   php docs/security/harness/mint.php --sub=100 --role=admin --org=2
 *   php docs/security/harness/mint.php --sub=1 --role=superadmin --org=null
 *   php docs/security/harness/mint.php --sub=100 --role=viewer --org=2 --exp=-60   # already expired
 *   php docs/security/harness/mint.php ... --forge-none                            # alg:none forgery (expect reject)
 *   php docs/security/harness/mint.php ... --tamper-role=admin                     # flip role after signing (expect reject)
 *
 * Options:
 *   --sub=<int>          subject (user id) claim. Default 100.
 *   --role=<string>      role claim (superadmin|admin|member|viewer). Default admin.
 *   --org=<int|null>     org claim. Use "null" for superadmin. Default 1.
 *   --exp=<seconds>      seconds from now until exp. Negative = already expired. Default 3600.
 *   --secret=<string>    override signing secret (default: NENE2_LOCAL_JWT_SECRET env).
 *   --forge-none         emit an unsigned alg:none token (attack probe).
 *   --tamper-role=<r>    sign a benign token, then swap the role claim (attack probe).
 *   --raw                print only the token (no trailing newline) — for scripting.
 */

require __DIR__ . '/../../../vendor/autoload.php';

use Nene2\Auth\LocalBearerTokenVerifier;

/** @return array<string,string> */
function parseArgs(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $arg = substr($arg, 2);
        if (str_contains($arg, '=')) {
            [$k, $v] = explode('=', $arg, 2);
            $out[$k] = $v;
        } else {
            $out[$arg] = '1';
        }
    }
    return $out;
}

function b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$args = parseArgs($argv);

$secret = $args['secret'] ?? getenv('NENE2_LOCAL_JWT_SECRET') ?: '';
if ($secret === '') {
    fwrite(STDERR, "mint.php: no signing secret (set NENE2_LOCAL_JWT_SECRET or pass --secret)\n");
    exit(2);
}

$sub  = isset($args['sub']) ? (int) $args['sub'] : 100;
$role = $args['role'] ?? 'admin';
$orgRaw = $args['org'] ?? '1';
$org  = ($orgRaw === 'null') ? null : (int) $orgRaw;
$ttl  = isset($args['exp']) ? (int) $args['exp'] : 3600;
$now  = time();

$claims = [
    'sub'  => $sub,
    'role' => $role,
    'org'  => $org,
    'iat'  => $now,
    'exp'  => $now + $ttl,
];

$raw = isset($args['raw']);

// ── Attack probe: alg:none forgery ──────────────────────────────────────────
if (isset($args['forge-none'])) {
    $header = b64url((string) json_encode(['typ' => 'JWT', 'alg' => 'none']));
    $payload = b64url((string) json_encode($claims));
    $token = $header . '.' . $payload . '.';
    echo $raw ? $token : $token . "\n";
    exit(0);
}

$verifier = new LocalBearerTokenVerifier($secret);
$token = $verifier->issue($claims);

// ── Attack probe: tamper the role claim after signing ───────────────────────
if (isset($args['tamper-role'])) {
    [$h, $p, $s] = explode('.', $token);
    $claims['role'] = $args['tamper-role'];
    $p2 = b64url((string) json_encode($claims));
    $token = $h . '.' . $p2 . '.' . $s; // signature no longer matches payload
}

echo $raw ? $token : $token . "\n";
