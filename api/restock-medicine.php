<?php
/**
 * =====================================================================
 * Restock Medicine / Vaccine (api/restock-medicine.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/manage_medicine.js's Restock modal, opened from
 * pharmacist/manage_medicine.php — a pharmacist receiving real stock,
 * always as a brand new batch with its own batch number/expiry
 * ("mode":"new" — hc_create_medicine_batch()). Adding into an
 * existing batch ("mode":"existing") was removed by request and is
 * now rejected.
 *
 * Deliberately separate from api/adjust-medicine-stock.php (subadmin-
 * only, a free-form +/- override that can also REMOVE stock) — this
 * endpoint only ever ADDS real stock a pharmacist actually received, so
 * it's allowed for the pharmacist role too, same reasoning
 * api/create-medicine.php's own header comment already gives for why
 * registering new stock is a different operation from editing existing
 * master data.
 *
 * Two more real fields get written here on every successful restock,
 * per explicit request:
 *   - minLevel/reorderLevel are recalculated from the item's own real
 *     total stock AFTER this restock (hc_auto_compute_thresholds()) —
 *     these two thresholds now scale with how much is actually on
 *     hand, rather than staying pinned to whatever a sub-admin set
 *     once at creation.
 *   - "dosage" (optional) is updated from the Restock form's own
 *     Dosage Information field — restocking is the one real pharmacist-
 *     accessible write on an EXISTING item, so it doubles as the place
 *     to keep that field current rather than leaving it permanently
 *     stuck at whatever was typed (or left blank) when the item was
 *     first added.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php';
require_once __DIR__ . '/../includes/firestore/medicine_batches.php'; // hc_create_medicine_batch()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['pharmacist', 'subadmin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = trim((string) ($body['id'] ?? ''));
$mode = trim((string) ($body['mode'] ?? ''));
$quantity = $body['quantity'] ?? null;
$dosage = trim((string) ($body['dosage'] ?? ''));

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing item id.']);
    exit;
}
// Every restock is received as its own new batch. Adding stock into a
// batch already on file ("mode":"existing") was removed by request —
// it allowed restocking an empty or already-expired batch.
if ($mode !== 'new') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Restocking into an existing batch is no longer supported. Add this stock as a new batch with its own batch number and expiry date.']);
    exit;
}
if (!is_numeric($quantity) || (int) $quantity <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a quantity greater than 0.']);
    exit;
}

$existing = $firestore->getDocument('medicines', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This item no longer exists.']);
    exit;
}

$quantity = (int) $quantity;
$name = $existing['name'] ?? '(unnamed)';
$unit = $existing['unit'] ?? 'unit';

// Restocking in whole boxes ("quantityUnit": "box") converts into the
// dispensing unit every batch quantity is counted in
// (includes/firestore/medicines.php's hc_box_breakdown() comment) using
// THIS medicine's own per-box count — never a fixed number. The modal
// shows the stored count; if the pharmacist enters one (first time, or
// correcting it) it's sent as "piecesPerBox" and saved to the medicine
// just before the stock is written ($savePiecesPerBox below), so it
// never has to be entered again. Bottles/inhalers never convert.
$piecesPerBox = (int) ($existing['piecesPerBox'] ?? 0);
$piecesPerBoxChanged = false;
$quantityUnit = (string) ($body['quantityUnit'] ?? 'unit');
if ($quantityUnit === 'box') {
    if (!hc_unit_allows_boxes($unit)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $name . ' is counted per ' . strtolower($unit) . ' — enter the number of ' . strtolower($unit) . 's received, not boxes.']);
        exit;
    }
    $sentPiecesPerBox = trim((string) ($body['piecesPerBox'] ?? ''));
    if ($sentPiecesPerBox !== '') {
        if (!ctype_digit($sentPiecesPerBox) || (int) $sentPiecesPerBox <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Please enter how many ' . strtolower($unit) . 's come in one box (a whole number above 0).']);
            exit;
        }
        $piecesPerBoxChanged = (int) $sentPiecesPerBox !== $piecesPerBox;
        $piecesPerBox = (int) $sentPiecesPerBox;
    }
    if ($piecesPerBox <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please enter how many ' . strtolower($unit) . 's come in one box of ' . $name . '.']);
        exit;
    }
    $boxesReceived = $quantity;
    $quantity *= $piecesPerBox;
}

/** Persists a new/corrected per-box count — only once every check above and below has passed. */
$savePiecesPerBox = function () use ($firestore, $id, $existing, $piecesPerBox, $piecesPerBoxChanged, $name, $unit): bool {
    if (!$piecesPerBoxChanged) {
        return true;
    }
    $saved = $firestore->setDocument('medicines', $id, array_merge($existing, ['piecesPerBox' => $piecesPerBox, 'lastUpdated' => date('c')]));
    if ($saved) {
        write_audit_log('updated', 'Medicine & Vaccine Inventory', 'Set packaging for "' . $name . '": 1 box = ' . $piecesPerBox . ' ' . $unit . '(s).');
    }

    return $saved;
};
$quantityLabel = $quantity . ' ' . $unit . '(s)'
    . (isset($boxesReceived) ? ' — ' . $boxesReceived . ' box(es) of ' . $piecesPerBox : '');

$batchNumber = trim((string) ($body['batchNumber'] ?? ''));
$expiryDate = trim((string) ($body['expiryDate'] ?? ''));

// Vaccines are tracked by Lot Number, medicines by Batch Number.
$isVaccine = ($existing['type'] ?? 'medicine') === 'vaccine';
if ($batchNumber === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $isVaccine ? 'Please enter the lot number for this new stock.' : 'Please enter the batch number for this new batch.']);
    exit;
}
// The same number twice on one item would make two Batch Inventory
// rows indistinguishable, so a batch number already on file for this
// item (including an empty or expired one) is rejected.
foreach (hc_get_batches_for_medicine($id, true) as $existingBatch) {
    if (strcasecmp($existingBatch['batchNumber'], $batchNumber) === 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $isVaccine
            ? 'Lot ' . $batchNumber . ' already exists for this vaccine. Enter the lot number printed on this new stock.'
            : 'Batch ' . $batchNumber . ' already exists for this item. Enter the batch number printed on this new stock.']);
        exit;
    }
}
if ($expiryDate === '' || strtotime($expiryDate) === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid expiry date for this batch.']);
    exit;
}
if (strtotime($expiryDate) < strtotime(date('Y-m-d'))) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Expiry date cannot be in the past.']);
    exit;
}

if (!$savePiecesPerBox()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this medicine\'s packaging. Please try again.']);
    exit;
}
$newBatchId = hc_create_medicine_batch(
    $id,
    $quantity,
    date('Y-m-d', strtotime($expiryDate)),
    'restock',
    null,
    $_SESSION['name'] ?? 'System',
    $batchNumber
);
if ($newBatchId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this new batch. Please try again.']);
    exit;
}

write_audit_log('created', 'Medicine & Vaccine Inventory', 'Restocked "' . $name . '" with a new batch (' . $quantityLabel . ').');

// hc_create_medicine_batch() already
// recomputed currentStock via hc_recompute_medicine_rollup() — this is
// a SEPARATE follow-up write, not a second rollup, so it doesn't touch
// that shared function (used by every other stock-changing path too,
// most of which have no reason to auto-recalculate thresholds).
$updatedDoc = $firestore->getDocument('medicines', $id);
$newStock = (int) ($updatedDoc['currentStock'] ?? 0);
$maxLevel = (int) ($updatedDoc['maxLevel'] ?? 0);
$thresholds = hc_auto_compute_thresholds($newStock);

$firestore->setDocument('medicines', $id, array_merge($updatedDoc, [
    'minLevel' => $thresholds['minLevel'],
    'reorderLevel' => $thresholds['reorderLevel'],
    'status' => hc_inventory_status_for_stock($newStock, $thresholds['minLevel'], $maxLevel),
    'dosage' => $dosage !== '' ? $dosage : null,
]));

write_audit_log('updated', 'Medicine & Vaccine Inventory', 'Recalculated thresholds for "' . $name . '" after restock (Minimum Level: ' . $thresholds['minLevel'] . ', Reorder Level: ' . $thresholds['reorderLevel'] . ').');

echo json_encode([
    'success' => true,
    'id' => $id,
    'currentStock' => $newStock,
    'status' => hc_inventory_status_for_stock($newStock, $thresholds['minLevel'], $maxLevel),
    'minLevel' => $thresholds['minLevel'],
    'reorderLevel' => $thresholds['reorderLevel'],
]);
