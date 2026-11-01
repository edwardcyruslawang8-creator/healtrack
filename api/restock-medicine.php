<?php
/**
 * =====================================================================
 * Restock Medicine / Vaccine (api/restock-medicine.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medicine_vaccine_inventory_pharmacist.js's Restock
 * modal, opened from inside pharmacist/medicine_vaccine_inventory.php's
 * Manage Medicine modal (includes/pharmacist/medicine_action_modals.php)
 * — a pharmacist receiving real stock, either more of an existing real
 * batch ("mode":"existing", a real medicine_batches/{batchId} that
 * already belongs to this item — hc_add_to_existing_batch()) or a brand
 * new one with its own batch number/expiry
 * ("mode":"new" — hc_create_medicine_batch()).
 *
 * Deliberately separate from api/adjust-medicine-stock.php (subadmin-
 * only, a free-form +/- override that can also REMOVE stock) — this
 * endpoint only ever ADDS real stock a pharmacist actually received, so
 * it's allowed for the pharmacist role too, same reasoning
 * api/create-medicine.php's own header comment already gives for why
 * registering new stock is a different operation from editing existing
 * master data.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php';
require_once __DIR__ . '/../includes/firestore/medicine_batches.php'; // hc_create_medicine_batch(), hc_add_to_existing_batch()
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

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing item id.']);
    exit;
}
if (!in_array($mode, ['existing', 'new'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please choose whether this is an existing batch or a new one.']);
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

if ($mode === 'existing') {
    $batchId = trim((string) ($body['batchId'] ?? ''));
    if ($batchId === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please select which batch this stock belongs to.']);
        exit;
    }
    $batchDoc = $firestore->getDocument('medicine_batches', $batchId);
    if ($batchDoc === null || ($batchDoc['medicineId'] ?? '') !== $id) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'That batch no longer exists for this item.']);
        exit;
    }

    $ok = hc_add_to_existing_batch($batchId, $quantity);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not restock this batch. Please try again.']);
        exit;
    }

    write_audit_log('updated', 'Medicine & Vaccine Inventory', 'Restocked ' . $quantity . ' ' . $unit . '(s) into an existing batch of "' . $name . '".');
} else {
    $batchNumber = trim((string) ($body['batchNumber'] ?? ''));
    $expiryDate = trim((string) ($body['expiryDate'] ?? ''));

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

    write_audit_log('created', 'Medicine & Vaccine Inventory', 'Restocked "' . $name . '" with a new batch (' . $quantity . ' ' . $unit . '(s)).');
}

$updatedDoc = $firestore->getDocument('medicines', $id);
$newStock = (int) ($updatedDoc['currentStock'] ?? 0);
$status = (string) ($updatedDoc['status'] ?? 'unavailable');

echo json_encode(['success' => true, 'id' => $id, 'currentStock' => $newStock, 'status' => $status]);
