<?php

declare(strict_types=1);

/**
 * Validates that every locale file under locales/ has an identical key structure.
 *
 * The first locale file (alphabetically, ja.json) is the reference. Any key present
 * in one file but missing in another is a defect that blocks merge.
 *
 * Usage: php tools/validate-locales.php
 * Exit code 0 = all locales consistent; 1 = mismatch or invalid JSON.
 */

$localesDir = dirname(__DIR__) . '/locales';
$files = glob($localesDir . '/*.json');

if ($files === false || $files === []) {
    fwrite(STDERR, "No locale files found in {$localesDir}\n");
    exit(1);
}

/**
 * Flatten a nested associative array into dot-notation keys.
 * Leaf = any non-associative value (scalar or list array).
 *
 * @param array<string, mixed> $data
 * @return list<string>
 */
function flattenKeys(array $data, string $prefix = ''): array
{
    $keys = [];

    foreach ($data as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;

        $isAssoc = is_array($value) && (count($value) === 0 || array_keys($value) !== range(0, count($value) - 1));

        if ($isAssoc) {
            $keys = array_merge($keys, flattenKeys($value, $full));
        } else {
            $keys[] = $full;
        }
    }

    return $keys;
}

/** @var array<string, list<string>> $keysByFile */
$keysByFile = [];

foreach ($files as $file) {
    $raw = file_get_contents($file);

    if ($raw === false) {
        fwrite(STDERR, "Cannot read {$file}\n");
        exit(1);
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        fwrite(STDERR, "Invalid JSON in {$file}\n");
        exit(1);
    }

    // Ignore the _meta block — it legitimately differs per locale
    unset($decoded['_meta']);

    $keys = flattenKeys($decoded);
    sort($keys);
    $keysByFile[basename($file)] = $keys;
}

$names = array_keys($keysByFile);
$reference = $names[0];
$referenceKeys = $keysByFile[$reference];

$hasError = false;

foreach ($names as $name) {
    if ($name === $reference) {
        continue;
    }

    $onlyInReference = array_diff($referenceKeys, $keysByFile[$name]);
    $onlyInThis = array_diff($keysByFile[$name], $referenceKeys);

    if ($onlyInReference !== []) {
        $hasError = true;
        fwrite(STDERR, "Keys in {$reference} but missing in {$name}:\n");
        foreach ($onlyInReference as $k) {
            fwrite(STDERR, "  - {$k}\n");
        }
    }

    if ($onlyInThis !== []) {
        $hasError = true;
        fwrite(STDERR, "Keys in {$name} but missing in {$reference}:\n");
        foreach ($onlyInThis as $k) {
            fwrite(STDERR, "  - {$k}\n");
        }
    }
}

if ($hasError) {
    fwrite(STDERR, "\nLocale key structures are NOT consistent.\n");
    exit(1);
}

printf("OK: %d locale file(s), %d keys each, all consistent.\n", count($names), count($referenceKeys));
exit(0);
