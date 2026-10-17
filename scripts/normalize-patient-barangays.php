<?php
/**
 * =====================================================================
 * One-off Patient Barangay Cleanup (scripts/normalize-patient-barangays.php)
 * ---------------------------------------------------------------------
 * CLI-only. Makes every patients/{id} doc registered under one of the
 * 11 barangays North Super Health Center covers (HC_COVERED_BARANGAYS),
 * in Lipa City:
 *   - renames the old misspelling "Buclanin" to the real "Bulacnin"
 *   - matches barangay names case-insensitively to their proper spelling
 *   - sets city to "Lipa City" where it says anything else
 * Only the barangay/city fields are written (field-masked PATCH), so
 * nothing else on a patient's doc is touched. Patients whose barangay
 * isn't one of the 11 are listed, not changed.
 *
 * Dry run (default):  C:\xampp\php\php.exe scripts\normalize-patient-barangays.php
 * Apply changes:      C:\xampp\php\php.exe scripts\normalize-patient-barangays.php --apply
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/shared/predictive_analytics_core.php'; // HC_COVERED_BARANGAYS

$apply = in_array('--apply', $argv, true);
$aliases = ['buclanin' => 'Bulacnin'];
foreach (HC_COVERED_BARANGAYS as $name) {
    $aliases[strtolower($name)] = $name;
}

// FirestoreRestClient has no field-masked update (setDocument() replaces
// the whole doc), so borrow its authenticated HTTP client and token.
$ref = new ReflectionObject($firestore);
$http = $ref->getProperty('http');
$http->setAccessible(true);
$token = $ref->getMethod('getAccessToken');
$token->setAccessible(true);

$changed = 0;
$unmatched = [];

foreach ($firestore->listDocuments('patients') as $doc) {
    $f = $doc['fields'];
    $barangay = trim((string) ($f['barangay'] ?? ''));
    $city = trim((string) ($f['city'] ?? ''));

    $newBarangay = $aliases[strtolower($barangay)] ?? null;
    if ($newBarangay === null) {
        $unmatched[] = "{$doc['id']} ({$f['name']}): barangay=\"{$barangay}\"";
        continue;
    }

    $updates = [];
    if ($newBarangay !== $barangay) {
        $updates['barangay'] = $newBarangay;
    }
    if ($city !== 'Lipa City') {
        $updates['city'] = 'Lipa City';
    }
    if (!$updates) {
        continue;
    }

    $changed++;
    $desc = implode(', ', array_map(fn ($k) => "{$k}: \"" . ($f[$k] ?? '') . "\" -> \"{$updates[$k]}\"", array_keys($updates)));
    echo ($apply ? 'UPDATE ' : 'WOULD UPDATE ') . "{$doc['id']} ({$f['name']}): {$desc}\n";

    if ($apply) {
        $mask = implode('&', array_map(fn ($k) => 'updateMask.fieldPaths=' . $k, array_keys($updates)));
        $http->getValue($firestore)->patch("patients/{$doc['id']}?{$mask}", [
            'headers' => ['Authorization' => 'Bearer ' . $token->invoke($firestore)],
            'json' => ['fields' => array_map(fn ($v) => ['stringValue' => $v], $updates)],
        ]);
    }
}

if ($apply && $changed > 0) {
    // Same cross-request cache setDocument() clears after a write.
    $invalidate = $ref->getMethod('cacheInvalidate');
    $invalidate->setAccessible(true);
    $invalidate->invoke($firestore, 'patients');
}

echo "\n" . ($apply ? 'Updated' : 'Would update') . " {$changed} patient(s).\n";
if ($unmatched) {
    echo count($unmatched) . " patient(s) with a barangay outside the 11 covered (left unchanged):\n  " . implode("\n  ", $unmatched) . "\n";
}
if (!$apply) {
    echo "Dry run only — re-run with --apply to write these changes.\n";
}
