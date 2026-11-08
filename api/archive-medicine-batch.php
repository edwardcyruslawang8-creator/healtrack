<?php
/**
 * =====================================================================
 * Archive an Expired Batch (api/archive-medicine-batch.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/manage_medicine.js when a pharmacist or sub-admin
 * confirms "Archive Expired Batch" on an expired row of
 * pharmacist/manage_medicine.php or subadmin/manage_medicine.php's
 * Batch Inventory table.
 *
 * Unlike api/archive-medicine.php (which hides the WHOLE item, valid
 * batches included), this takes only the one expired batch out of
 * inventory: marks the medicine_batches/{id} doc archived, keeps its
 * remaining quantity as archivedQuantity for the record, zeroes its
 * quantity, then recomputes the item's currentStock/expiryDate rollup
 * so Total Current Stock no longer counts the expired stock.
 * hc_get_batches_for_medicine() skips archived batches everywhere.
 *
 * Refuses a batch that isn't actually expired (checked server-side,
 * same hc_batch_status() the table's badge uses).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicine_batches.php'; // hc_batch_status(), hc_batch_display_number(), hc_recompute_medicine_rollup()
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
$batchId = trim((string) ($body['batchId'] ?? ''));

if ($batchId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing batch id.']);
    exit;
}

$batch = $firestore->getDocument('medicine_batches', $batchId);
if ($batch === null || ($batch['archived'] ?? false)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This batch no longer exists or was already archived.']);
    exit;
}

$medicineId = (string) ($batch['medicineId'] ?? '');
$medicine = $medicineId !== '' ? $firestore->getDocument('medicines', $medicineId) : null;
if ($medicine === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'The item this batch belongs to no longer exists.']);
    exit;
}

// Vaccines are tracked by lot, medicines by batch — for the wording below.
$lotWord = ($medicine['type'] ?? 'medicine') === 'vaccine' ? 'lot' : 'batch';

$quantity = (int) ($batch['quantity'] ?? 0);
$status = hc_batch_status($quantity, (string) ($batch['expiryDate'] ?? ''), (int) ($medicine['reorderLevel'] ?? 0));
if ($status['key'] !== 'expired') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Only expired ' . ($lotWord === 'lot' ? 'lots' : 'batches') . ' can be archived here.']);
    exit;
}

$saved = $firestore->setDocument('medicine_batches', $batchId, array_merge($batch, [
    'archived' => true,
    'archivedQuantity' => $quantity,
    'quantity' => 0,
    'archivedAt' => date('c'),
    'archivedBy' => $_SESSION['name'] ?? ($_SESSION['uid'] ?? 'unknown'),
]));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not archive this ' . $lotWord . '. Please try again.']);
    exit;
}

hc_recompute_medicine_rollup($medicineId);

$name = $medicine['name'] ?? '(unnamed)';
$batchLabel = hc_batch_display_number($batch, $batchId);
write_audit_log('updated', 'Medicine & Vaccine Inventory', 'Archived expired ' . $lotWord . ' ' . $batchLabel . ' of "' . $name . '" (' . $quantity . ' removed from stock).');

echo json_encode(['success' => true]);
