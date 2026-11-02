<?php
/**
 * =====================================================================
 * Dispensing-unit migration (scripts/migrate-dispensing-units.php)
 * ---------------------------------------------------------------------
 * CLI-only, one-off. Moves every medicines/{id} doc that predates the
 * "piecesPerBox" field onto the dispensing-unit model (rules in
 * scripts/inventory-unit-profiles.php): box-stocked tablets/capsules
 * become individual pieces, liquids become Bottle, inhalers/vaccines
 * stay as they are.
 *
 * Existing batch records are preserved — same docs, batch numbers,
 * expiry/received dates — only each batch's quantity/originalQuantity
 * and the item's min/reorder/max levels are multiplied by the item's
 * conversion factor (1 when nothing converts), then the item's rollup
 * is recomputed by hc_recompute_medicine_rollup() exactly as a real
 * restock would. Docs that already have a piecesPerBox field are
 * skipped, so re-running is safe. --apply backs up both collections to
 * storage/inventory_backups/ first.
 *
 * Dry run (default):  C:\xampp\php\php.exe scripts\migrate-dispensing-units.php
 * Apply changes:      C:\xampp\php\php.exe scripts\migrate-dispensing-units.php --apply
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/firestore/medicine_batches.php'; // hc_recompute_medicine_rollup()
require_once __DIR__ . '/inventory-unit-profiles.php'; // hc_demo_unit_profile()

$apply = in_array('--apply', $argv, true);

$medicines = $firestore->listDocuments('medicines');
$batches = $firestore->listDocuments('medicine_batches');
$batchesByItem = [];
foreach ($batches as $doc) {
    $batchesByItem[(string) ($doc['fields']['medicineId'] ?? '')][] = $doc;
}

$plan = [];
foreach ($medicines as $doc) {
    $f = $doc['fields'];
    if (array_key_exists('piecesPerBox', $f)) {
        continue; // already migrated (or created after this field existed)
    }
    $profile = hc_demo_unit_profile((string) ($f['name'] ?? ''), (string) ($f['unit'] ?? ''));
    $plan[] = ['doc' => $doc, 'profile' => $profile];
    printf(
        "%-50s %4d %-8s -> %6d %-8s %s\n",
        $f['name'] ?? '(unnamed)',
        (int) ($f['currentStock'] ?? 0),
        $f['unit'] ?? '',
        (int) ($f['currentStock'] ?? 0) * $profile['factor'],
        $profile['unit'],
        $profile['piecesPerBox'] > 0 ? '(box of ' . $profile['piecesPerBox'] . ')' : ''
    );
}
echo "\n" . count($plan) . ' item(s) to migrate, ' . (count($medicines) - count($plan)) . " already on the new model.\n";

if (!$apply) {
    echo "Dry run only — re-run with --apply to write these changes.\n";
    exit(0);
}
if ($plan === []) {
    exit(0);
}

$backupDir = __DIR__ . '/../storage/inventory_backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
$backupPath = $backupDir . '/before-unit-migration-' . date('Ymd-His') . '.json';
if (file_put_contents($backupPath, json_encode(['medicines' => $medicines, 'medicine_batches' => $batches], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
    fwrite(STDERR, "Could not write backup — nothing changed.\n");
    exit(1);
}
echo 'Backed up to ' . realpath($backupPath) . "\n";

$failures = 0;
foreach ($plan as ['doc' => $doc, 'profile' => $profile]) {
    $factor = $profile['factor'];
    $batchFailed = false;
    foreach ($batchesByItem[$doc['id']] ?? [] as $batch) {
        $bf = $batch['fields'];
        $ok = $factor === 1 || $firestore->setDocument('medicine_batches', $batch['id'], array_merge($bf, [
            'quantity' => (int) ($bf['quantity'] ?? 0) * $factor,
            'originalQuantity' => (int) ($bf['originalQuantity'] ?? 0) * $factor,
        ]));
        if (!$ok) {
            $failures++;
            $batchFailed = true;
            echo "  ! could not update medicine_batches/{$batch['id']}\n";
        }
    }
    if ($batchFailed) {
        // Some of this item's batches are already converted — re-running
        // would multiply them again. Restore this item from the backup.
        echo '  ! ' . ($doc['fields']['name'] ?? $doc['id']) . " left PARTIALLY converted — restore it from the backup above before re-running.\n";
        continue;
    }

    $f = $doc['fields'];
    $ok = $firestore->setDocument('medicines', $doc['id'], array_merge($f, [
        'unit' => $profile['unit'],
        'form' => $profile['unit'], // same as api/create-medicine.php
        'piecesPerBox' => $profile['piecesPerBox'],
        'minLevel' => (int) ($f['minLevel'] ?? 0) * $factor,
        'reorderLevel' => (int) ($f['reorderLevel'] ?? 0) * $factor,
        'maxLevel' => (int) ($f['maxLevel'] ?? 0) * $factor,
    ]));
    if (!$ok) {
        $failures++;
        echo "  ! could not update medicines/{$doc['id']}\n";
        continue;
    }
    hc_recompute_medicine_rollup($doc['id']);
    echo '  migrated ' . ($f['name'] ?? $doc['id']) . "\n";
}

echo $failures === 0 ? "\nDone — no errors.\n" : "\nFinished with {$failures} failed write(s) — re-running retries only unmigrated items.\n";
exit($failures === 0 ? 0 : 1);
