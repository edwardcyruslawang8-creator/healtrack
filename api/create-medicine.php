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
$currentStock = $body['currentStock'] ?? null;
$minLevel = $body['minLevel'] ?? null;
$reorderLevel = $body['reorderLevel'] ?? null;
// Optional — blank/omitted means "no ceiling set" (0), same convention
// as every other optional numeric field here (see hc_inventory_status_for_stock()'s
// own header comment for why 0 never flags 'overstocked').
$maxLevel = $body['maxLevel'] ?? '';
$maxLevel = ($maxLevel === '' || $maxLevel === null) ? 0 : $maxLevel;
$expiryDate = trim((string) ($body['expiryDate'] ?? ''));

if (!in_array($type, ['medicine', 'vaccine'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid item type.']);
    exit;
}
if ($name === '' || $category === '' || $unit === '' || $expiryDate === ''
    || !is_numeric($currentStock) || !is_numeric($minLevel) || !is_numeric($reorderLevel) || !is_numeric($maxLevel)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every required field.']);
    exit;
}
if ((float) $currentStock < 0 || (float) $minLevel < 0 || (float) $reorderLevel < 0 || (float) $maxLevel < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Stock levels cannot be negative.']);
    exit;
}
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
    'desc' => $desc !== '' ? $desc : null,
    'dosage' => $dosage !== '' ? $dosage : null,
    'currentStock' => $currentStock,
    'minLevel' => $minLevel,
    'reorderLevel' => $reorderLevel,
    'maxLevel' => $maxLevel,
    'expiryDate' => date('Y-m-d', strtotime($expiryDate)),
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
// shows up later (a stock request release, Adjust Stock, ...).
if ($currentStock > 0) {
    hc_create_medicine_batch($newId, $currentStock, date('Y-m-d', strtotime($expiryDate)), 'initial', null, $_SESSION['name'] ?? 'System');
}

write_audit_log('created', 'Medicine & Vaccine Inventory', 'Added new ' . $type . ' "' . $name . '" (' . $currentStock . ' ' . $unit . ' in stock).');

echo json_encode(['success' => true, 'item' => ['id' => $newId, 'name' => $name, 'status' => $status]]);
