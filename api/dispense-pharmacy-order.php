<?php
/**
 * =====================================================================
 * Dispense Pharmacy Order (api/dispense-pharmacy-order.php)
 * ---------------------------------------------------------------------
 * Called by the "Dispense" button on pharmacist/prescription_queue.php
 * (assets/js/prescription-queue.js). Flips one real pharmacy_orders/{id}
 * doc from "pending" to "dispensed" — the pharmacist-side equivalent of
 * how a medical technologist completes a lab_results doc. Only ever
 * dispenses a doc that's genuinely still "pending" (re-checked
 * server-side, not trusted from the button already being on screen),
 * so double-clicking or two pharmacists racing the same order can't
 * double-count it.
 *
 * Optionally also decrements real medicines/{id} stock — $inventoryItemId/
 * $quantity, chosen by the pharmacist in the Dispense modal (pre-filled
 * from a fuzzy name match, but editable — a prescribed item's free-text
 * name isn't guaranteed to match a real tracked inventory record, so
 * "don't adjust stock" is a genuine, allowed choice, not an error).
 * Every check here is re-done server-side against the real doc. The
 * actual decrement draws down the item's real batches soonest-expiry
 * first (hc_decrement_medicine_stock_fefo(), FEFO —
 * includes/firestore/medicine_batches.php) rather than one shared
 * currentStock number, same "never trust the client's own math"
 * principle api/adjust-medicine-stock.php's own +/- stock modal
 * follows too.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php'; // hc_inventory_status_for_stock(), hc_inventory_expiry_status()
require_once __DIR__ . '/../includes/firestore/medicine_batches.php'; // hc_decrement_medicine_stock_fefo()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

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
$orderId = trim((string) ($body['orderId'] ?? ''));
$inventoryItemId = trim((string) ($body['inventoryItemId'] ?? ''));
$quantity = $body['quantity'] ?? null;

if ($orderId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing order id.']);
    exit;
}

$existing = $firestore->getDocument('pharmacy_orders', $orderId);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Prescription not found.']);
    exit;
}
if (($existing['status'] ?? '') !== 'pending') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This prescription has already been dispensed.']);
    exit;
}

// "Not in inventory" (blank $inventoryItemId) is a valid, deliberate
// choice — the order dispenses with no stock adjustment. Only when a
// real item was chosen does a quantity need validating.
$dispensedQuantity = null;
$dispensedItemName = null;
$medicineDoc = null;
if ($inventoryItemId !== '') {
    if (!is_numeric($quantity) || (int) $quantity < 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please enter a valid quantity dispensed.']);
        exit;
    }
    $quantity = (int) $quantity;

    $medicineDoc = $firestore->getDocument('medicines', $inventoryItemId);
    if ($medicineDoc === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'This inventory item no longer exists.']);
        exit;
    }
    // An expired medicine/vaccine can never be dispensed — re-checked
    // here server-side, same hc_inventory_expiry_status() every other
    // real expiry check in this app already uses. A blank/not-linked
    // $inventoryItemId (dispensing with no stock adjustment) skips this
    // entirely — there's no real inventory record to check an expiry
    // against in that case, same as it already has no stock to check.
    if (hc_inventory_expiry_status($medicineDoc['expiryDate'] ?? '') === 'expired') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => ($medicineDoc['name'] ?? 'This item') . ' has expired and can no longer be dispensed.']);
        exit;
    }

    $oldStock = (int) ($medicineDoc['currentStock'] ?? 0);
    if ($quantity > $oldStock) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Only ' . $oldStock . ' ' . ($medicineDoc['unit'] ?? 'unit') . '(s) in stock — cannot dispense ' . $quantity . '.']);
        exit;
    }

    $dispensedQuantity = $quantity;
    $dispensedItemName = $medicineDoc['name'] ?? '(unnamed)';
}

$saved = $firestore->setDocument('pharmacy_orders', $orderId, array_merge($existing, [
    'status' => 'dispensed',
    'dispensedDate' => date('Y-m-d'),
    'dispensedTime' => date('g:i A'),
    'dispensedBy' => $_SESSION['name'] ?? 'Not specified',
    'dispensedQuantity' => $dispensedQuantity,
    'dispensedItemName' => $dispensedItemName,
    'dispensedInventoryId' => $inventoryItemId !== '' ? $inventoryItemId : null,
]));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save. Please try again.']);
    exit;
}

// The actual inventory decrement — only after the pharmacy_orders write
// above succeeded, so a failed inventory save never leaves an order
// marked "dispensed" against stock that was never actually deducted
// silently disagreeing with what's on screen. Draws down the
// soonest-expiring real batch(es) first (FEFO) rather than a single
// shared number — see includes/firestore/medicine_batches.php's
// header comment.
if ($medicineDoc !== null) {
    hc_decrement_medicine_stock_fefo($inventoryItemId, $dispensedQuantity);
}

$auditMessage = 'Dispensed ' . ($existing['itemName'] ?? 'item') . ' for ' . ($existing['patientName'] ?? '');
$auditMessage .= $dispensedItemName !== null
    ? ' — ' . $dispensedQuantity . ' unit(s) of "' . $dispensedItemName . '" deducted from inventory.'
    : ' (not linked to a tracked inventory item — stock unchanged).';
write_audit_log('updated', 'Prescription Queue', $auditMessage);
hc_record_patient_history($existing['patientId'] ?? '', 'medication', 'updated', 'Dispensed "' . ($existing['itemName'] ?? 'item') . '".');
hc_record_patient_activity($existing['patientId'] ?? '', 'Prescription dispensed: "' . ($existing['itemName'] ?? 'item') . '".');

echo json_encode(['success' => true]);
