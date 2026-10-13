<?php
/**
 * =====================================================================
 * Adjust Medicine / Vaccine Stock (api/adjust-medicine-stock.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medicine_vaccine_inventory_pharmacist.js's
 * "Adjust Stock" quick modal — a focused +/- change to just
 * currentStock, separate from the full Edit form, for the routine
 * day-to-day "we received a delivery" / "we dispensed some" case.
 * Writes to the same real "medicines" Firestore collection
 * includes/firestore/medicines.php reads. "status" is recomputed
 * server-side from the new stock vs minLevel
 * (hc_inventory_status_for_stock()), same as create/update — never
 * trusted from the client.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php';
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
$delta = $body['delta'] ?? null;

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
$oldStock = (int) ($existing['currentStock'] ?? 0);
$newStock = $oldStock + $delta;

if ($newStock < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Stock cannot go below 0 (currently ' . $oldStock . ').']);
    exit;
}

$minLevel = (int) ($existing['minLevel'] ?? 0);
$status = hc_inventory_status_for_stock($newStock, $minLevel);

$updated = array_merge($existing, [
    'currentStock' => $newStock,
    'status' => $status,
    'lastUpdated' => date('c'),
]);

$saved = $firestore->setDocument('medicines', $id, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this change. Please try again.']);
    exit;
}

$name = $existing['name'] ?? '(unnamed)';
$verb = $delta > 0 ? 'Added' : 'Removed';
write_audit_log('updated', 'Medicine & Vaccine Inventory', $verb . ' ' . abs($delta) . ' ' . ($existing['unit'] ?? 'unit') . '(s) ' . ($delta > 0 ? 'to' : 'from') . ' "' . $name . '" (now ' . $newStock . ').');

echo json_encode(['success' => true, 'id' => $id, 'currentStock' => $newStock, 'status' => $status]);
