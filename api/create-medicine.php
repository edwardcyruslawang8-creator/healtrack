<?php
/**
 * =====================================================================
 * Create Medicine / Vaccine (api/create-medicine.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medicine_vaccine_inventory_pharmacist.js when a
 * pharmacist submits the "Add Medicine" or "Add Vaccine" modal
 * (pharmacist/medicine_vaccine_inventory.php). Writes a new document
 * to the real "medicines" Firestore collection — the same collection
 * includes/firestore/medicines.php reads for both the Medicines and
 * Vaccines tabs (medicines and vaccines share one collection, split by
 * a "type" field).
 *
 * "status" is computed server-side from currentStock vs minLevel
 * (hc_inventory_status_for_stock()) rather than trusted from the
 * client, matching how the read side already treats it as a real
 * derived signal, not free-form input.
 *
 * The stored "form" field (Tablet/Capsule/Syrup/... — read by
 * patient/medicine.php's own Form column/filter and Inventory Reports)
 * is set equal to $unit rather than collected as its own form input —
 * see includes/pharmacist/add_medicine_modal.php's header comment for
 * why that field was removed from the Add/Edit form.
 *
 * currentStock/minLevel/reorderLevel/maxLevel/expiryDate/dosage are
 * ALL optional now (the Add form itself hides every one of them — see
 * add_medicine_modal.php's own header comment) — a newly-created item
 * is a catalog entry with 0 real stock and no thresholds, 'status'
 * correctly comes back "Out of Stock" for it, and a pharmacist adds
 * its real first batch (with a real expiry date) through Restock on
 * pharmacist/manage_medicine.php afterward, which also auto-computes
 * Minimum/Reorder Level from whatever quantity results. Still accepts
 * real values for all of these if sent (defensive — nothing besides
 * that hidden-field form posts here, but this endpoint doesn't assume
 * that stays true forever), just doesn't require them.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php';
require_once __DIR__ . '/../includes/firestore/medicine_batches.php'; // hc_create_medicine_batch()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'pharmacist') {
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

$type = trim((string) ($body['type'] ?? ''));
$name = trim((string) ($body['name'] ?? ''));
$category = trim((string) ($body['category'] ?? ''));
$unit = trim((string) ($body['unit'] ?? ''));
$desc = trim((string) ($body['desc'] ?? ''));
$dosage = trim((string) ($body['dosage'] ?? ''));
// All optional now — blank/omitted/non-numeric defaults to 0 (no
// stock, no threshold set), same "0 means unset" convention
// hc_inventory_status_for_stock() already uses for maxLevel.
$currentStock = $body['currentStock'] ?? '';
$currentStock = is_numeric($currentStock) ? $currentStock : 0;
$minLevel = $body['minLevel'] ?? '';
$minLevel = is_numeric($minLevel) ? $minLevel : 0;
$reorderLevel = $body['reorderLevel'] ?? '';
$reorderLevel = is_numeric($reorderLevel) ? $reorderLevel : 0;
$maxLevel = $body['maxLevel'] ?? '';
$maxLevel = is_numeric($maxLevel) ? $maxLevel : 0;
$expiryDate = trim((string) ($body['expiryDate'] ?? ''));
// Optional box packaging over the dispensing unit — see
// includes/firestore/medicines.php's hc_box_breakdown(). Blank = 0.
$rawPiecesPerBox = trim((string) ($body['piecesPerBox'] ?? ''));

if ($rawPiecesPerBox !== '' && !ctype_digit($rawPiecesPerBox)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Pieces per Box must be a whole number (or left blank).']);
    exit;
}
$piecesPerBox = (int) $rawPiecesPerBox;
$unitError = hc_dispensing_unit_error($unit);
if ($unitError !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $unitError]);
    exit;
}
// Tablets/capsules come boxed and their count per box differs by
// product — required, never assumed. Bottles/inhalers never convert.
if ($piecesPerBox <= 0 && hc_unit_requires_pieces_per_box($unit)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter how many ' . strtolower(rtrim($unit, 's')) . 's come in one box.']);
    exit;
}
if (!hc_unit_allows_boxes($unit)) {
    $piecesPerBox = 0;
}

if (!in_array($type, ['medicine', 'vaccine'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid item type.']);
    exit;
}
if ($name === '' || $category === '' || $unit === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every required field.']);
    exit;
}
if ((float) $currentStock < 0 || (float) $minLevel < 0 || (float) $reorderLevel < 0 || (float) $maxLevel < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Stock levels cannot be negative.']);
    exit;
}
if ($expiryDate !== '') {
    if (strtotime($expiryDate) === false) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please enter a valid expiry date.']);
        exit;
    }
    if (strtotime($expiryDate) < strtotime(date('Y-m-d'))) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Expiry date cannot be in the past.']);
        exit;
    }
}

// Required first stock from the Add form's "Initial Stock" section —
// every new item must be added with real starting stock now (by
// explicit request — no more "add with 0 stock, restock later" path).
// Received exactly like a "new batch" Restock (api/restock-medicine.php):
// a required batch number + future expiry, and a quantity in boxes
// (converted with THIS item's per-box count) or in the dispensing unit
// itself. Fully validated here, BEFORE the item is created, so a bad
// batch never leaves a half-created item behind.
if (!is_array($body['initialStock'] ?? null)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please provide the initial stock — batch number, expiry date, and quantity received.']);
    exit;
}
$is = $body['initialStock'];
$isBatchNumber = trim((string) ($is['batchNumber'] ?? ''));
$isExpiry = trim((string) ($is['expiryDate'] ?? ''));
$isQuantity = $is['quantity'] ?? null;
$isInBoxes = ($is['quantityUnit'] ?? 'unit') === 'box';

if ($isBatchNumber === '' || strlen($isBatchNumber) > 50) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter the batch number for the initial stock.']);
    exit;
}
if ($isExpiry === '' || strtotime($isExpiry) === false || strtotime($isExpiry) < strtotime(date('Y-m-d'))) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid expiry date (not in the past) for the initial stock.']);
    exit;
}
if (!is_int($isQuantity) && !(is_string($isQuantity) && ctype_digit($isQuantity)) || (int) $isQuantity <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter an initial quantity greater than 0.']);
    exit;
}
$isQuantity = (int) $isQuantity;
if ($isInBoxes) {
    if (!hc_unit_allows_boxes($unit)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $unit . ' stock is received per ' . strtolower($unit) . ', not in boxes.']);
        exit;
    }
    if ($piecesPerBox <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please enter how many ' . strtolower(rtrim($unit, 's')) . 's come in one box to receive the initial stock in boxes.']);
        exit;
    }
}
$initialStock = [
    'batchNumber' => $isBatchNumber,
    'expiryDate' => date('Y-m-d', strtotime($isExpiry)),
    'quantity' => $isInBoxes ? $isQuantity * $piecesPerBox : $isQuantity,
    'boxes' => $isInBoxes ? $isQuantity : null,
];

$currentStock = (int) $currentStock;
$minLevel = (int) $minLevel;
$reorderLevel = (int) $reorderLevel;
$maxLevel = (int) $maxLevel;
$status = hc_inventory_status_for_stock($currentStock, $minLevel, $maxLevel);

$newId = $firestore->addDocument('medicines', [
    'type' => $type,
    'name' => $name,
    'category' => $category,
    'unit' => $unit,
    'form' => $unit, // see this file's header comment
    'piecesPerBox' => $piecesPerBox,
    'desc' => $desc !== '' ? $desc : null,
    'dosage' => $dosage !== '' ? $dosage : null,
    'currentStock' => $currentStock,
    'minLevel' => $minLevel,
    'reorderLevel' => $reorderLevel,
    'maxLevel' => $maxLevel,
    'expiryDate' => $expiryDate !== '' ? date('Y-m-d', strtotime($expiryDate)) : null,
    'status' => $status,
    'icon' => $type === 'vaccine' ? 'bi-droplet-half' : 'bi-capsule',
    'color' => $type === 'vaccine' ? 'cyan' : 'blue',
    'lastUpdated' => date('c'),
    'createdBy' => $_SESSION['uid'] ?? '',
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this item. Please try again.']);
    exit;
}

// This item's real first batch — see includes/firestore/medicine_batches.php's
// header comment for why stock is tracked per-batch, not as one shared
// number, the moment a second delivery with a different expiry date
// shows up later (a stock request release, Restock, ...). The Add form
// no longer collects currentStock/expiryDate at all, so this is now
// effectively a no-op on every real submission — real stock always
// enters via Restock instead — but stays defensive rather than
// assuming that forever.
if ($currentStock > 0 && $expiryDate !== '') {
    hc_create_medicine_batch($newId, $currentStock, date('Y-m-d', strtotime($expiryDate)), 'initial', null, $_SESSION['name'] ?? 'System');
}

// The Add form's Initial Stock: its own first batch (source "restock",
// so Stock Movement counts it as received, like any real restock), then
// Minimum/Reorder Level computed from the resulting stock exactly as
// api/restock-medicine.php does.
if ($initialStock !== null) {
    $batchId = hc_create_medicine_batch($newId, $initialStock['quantity'], $initialStock['expiryDate'], 'restock', null, $_SESSION['name'] ?? 'System', $initialStock['batchNumber']);
    if ($batchId === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'The item was added, but its initial stock could not be saved. Please add it with Restock.']);
        exit;
    }
    $createdDoc = $firestore->getDocument('medicines', $newId) ?? [];
    $currentStock = (int) ($createdDoc['currentStock'] ?? 0);
    $thresholds = hc_auto_compute_thresholds(max(1, $currentStock));
    $status = hc_inventory_status_for_stock($currentStock, $thresholds['minLevel'], $maxLevel);
    $firestore->setDocument('medicines', $newId, array_merge($createdDoc, [
        'minLevel' => $thresholds['minLevel'],
        'reorderLevel' => $thresholds['reorderLevel'],
        'status' => $status,
    ]));
}

write_audit_log('created', 'Medicine & Vaccine Inventory', 'Added new ' . $type . ' "' . $name . '" (' . hc_qty_unit($currentStock, $unit) . ' in stock'
    . ($initialStock !== null ? ', batch ' . $initialStock['batchNumber'] . ($initialStock['boxes'] !== null ? ' — ' . $initialStock['boxes'] . ' box(es) of ' . $piecesPerBox : '') : '') . ').');

echo json_encode(['success' => true, 'item' => ['id' => $newId, 'name' => $name, 'status' => $status]]);
