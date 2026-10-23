<?php
/**
 * =====================================================================
 * Adjust Medicine / Vaccine Stock (api/adjust-medicine-stock.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medicine_vaccine_inventory_pharmacist.js's
 * "Adjust Stock" quick modal — a focused +/- change, separate from the
 * full Edit form, for the routine day-to-day "we received a delivery"
 * / "we miscounted, fix it" case. currentStock itself is a maintained
 * rollup over this item's own real medicine_batches docs now
 * (includes/firestore/medicine_batches.php) — a positive delta creates
 * a real NEW batch (with its own real expiry date, required — this is
 * exactly the "same medicine, different expiry than what's already on
 * the shelf" case that single shared field used to silently get
 * wrong), a negative delta draws down existing batches soonest-expiry
 * first (hc_decrement_medicine_stock_fefo(), FEFO). "status" is still
 * recomputed server-side from the new total vs minLevel, never trusted
 * from the client.
 *
 * Subadmin-only — a pharmacist changes real stock only through a real
 * tracked action (Dispense, api/dispense-without-prescription.php /
 * api/dispense-pharmacy-order.php), never this free-form +/- override.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php';
require_once __DIR__ . '/../includes/firestore/medicine_batches.php'; // hc_create_medicine_batch(), hc_decrement_medicine_stock_fefo()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'subadmin') {
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
$delta = $body['delta'] ?? null;
$expiryDate = trim((string) ($body['expiryDate'] ?? ''));

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing item id.']);
    exit;
}
if (!is_numeric($delta) || (int) $delta === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a non-zero amount.']);
    exit;
}

$existing = $firestore->getDocument('medicines', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This item no longer exists.']);
    exit;
}

$delta = (int) $delta;
$name = $existing['name'] ?? '(unnamed)';

if ($delta > 0) {
    // New stock is a real NEW batch — its own real expiry date,
    // required, same as any other real delivery.
    if ($expiryDate === '' || strtotime($expiryDate) === false) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please enter a valid expiry date for the stock being added.']);
        exit;
    }
    if (strtotime($expiryDate) < strtotime(date('Y-m-d'))) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Expiry date cannot be in the past.']);
        exit;
    }

    hc_create_medicine_batch($id, $delta, date('Y-m-d', strtotime($expiryDate)), 'adjustment', null, $_SESSION['name'] ?? 'System');
} else {
    $removeQty = abs($delta);
    $ok = hc_decrement_medicine_stock_fefo($id, $removeQty);
    if (!$ok) {
        $available = (int) ($existing['currentStock'] ?? 0);
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Stock cannot go below 0 (currently ' . $available . ').']);
        exit;
    }
}

$updatedDoc = $firestore->getDocument('medicines', $id);
$newStock = (int) ($updatedDoc['currentStock'] ?? 0);
$status = (string) ($updatedDoc['status'] ?? 'unavailable');

$verb = $delta > 0 ? 'Added' : 'Removed';
write_audit_log('updated', 'Medicine & Vaccine Inventory', $verb . ' ' . abs($delta) . ' ' . ($existing['unit'] ?? 'unit') . '(s) ' . ($delta > 0 ? 'to' : 'from') . ' "' . $name . '" (now ' . $newStock . ').');

echo json_encode(['success' => true, 'id' => $id, 'currentStock' => $newStock, 'status' => $status]);
