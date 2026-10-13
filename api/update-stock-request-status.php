<?php
/**
 * =====================================================================
 * Update Stock Request Status — Approve / Reject / Release (api/update-stock-request-status.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/stock_requests.js when a subadmin picks "Approve
 * Request"/"Reject Request"/"Release Items" from a row's three-dot
 * menu on subadmin/stock_requests.php. Writes a real status change to
 * the same "stock_requests" Firestore collection
 * includes/firestore/stock_requests.php reads.
 *
 * Subadmin-only — deciding on a request (approve/reject/release) is a
 * sub-admin capability, not the requesting pharmacist's own. A
 * pharmacist manages their own request's existence (create/edit/
 * delete, see api/create-stock-request.php, api/update-stock-request.php,
 * api/delete-stock-request.php) but not its outcome.
 *
 * Each target status only makes sense from one specific current status
 * — pending -> approved/rejected, approved -> released — so
 * $allowedFrom below is checked server-side too, not just hidden in
 * the UI (the UI hides/disables the menu items that don't apply to a
 * row's current status, but this endpoint doesn't trust that alone).
 *
 * Releasing ALSO adds real stock: each item on the request that
 * carries a real "itemId" (resolved at request-creation time by
 * api/create-stock-request.php's hc_find_inventory_item_by_name())
 * gets that item's requested qty added onto its real medicines/{id}
 * doc's currentStock, with "status" recomputed via
 * hc_inventory_status_for_stock() — same pattern
 * api/adjust-medicine-stock.php already uses for its own +/- stock
 * changes. An item with no itemId (a brand-new item that didn't exist
 * in inventory yet when the request was made — see
 * includes/pharmacist/new_stock_request_modal.php's header comment)
 * or whose matched item has since been deleted is skipped rather than
 * failing the whole release; $skippedItems below tracks which ones so
 * the audit log entry stays honest about what did/didn't actually
 * update inventory.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php'; // hc_inventory_status_for_stock()
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

$docId = trim((string) ($body['docId'] ?? ''));
$newStatus = trim((string) ($body['status'] ?? ''));

// Which current status a given target status is allowed to transition
// from — the only two real workflows this collection supports.
$allowedFrom = [
    'approved' => 'pending',
    'rejected' => 'pending',
    'released' => 'approved',
];

if ($docId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing request id.']);
    exit;
}
if (!isset($allowedFrom[$newStatus])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid status.']);
    exit;
}

$existing = $firestore->getDocument('stock_requests', $docId);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This request no longer exists.']);
    exit;
}

// Never trust the client-supplied docId alone — it must belong to this
// sub-admin's own facility (each stock_requests doc carries a real
// "facility" field), resolved server-side from their own users/{uid}
// doc, same as every other facility-scoped write in this app.
$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myFacility = $myDoc['facility'] ?? null;
if ($myFacility === null || ($existing['facility'] ?? '') !== $myFacility) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This request no longer exists.']);
    exit;
}

$currentStatus = $existing['status'] ?? 'pending';
if ($currentStatus !== $allowedFrom[$newStatus]) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'This request is currently "' . $currentStatus . '" and cannot be ' . $newStatus . ' from there.']);
    exit;
}

$updated = array_merge($existing, [
    'status' => $newStatus,
    'reviewedBy' => $_SESSION['name'] ?? 'System',
    'reviewedAt' => date('c'),
]);

$saved = $firestore->setDocument('stock_requests', $docId, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this change. Please try again.']);
    exit;
}

$requester = $existing['pharmacistName'] ?? 'Unknown';

// Release — add each matched item's requested qty onto real inventory.
// See this file's own header comment for why an item with no itemId
// (or a since-deleted one) is skipped rather than failing the release.
$updatedItems = [];
$skippedItems = [];
if ($newStatus === 'released') {
    foreach ((is_array($existing['items'] ?? null) ? $existing['items'] : []) as $item) {
        $itemName = (string) ($item['name'] ?? 'Unknown item');
        $itemId = $item['itemId'] ?? null;
        $qty = (int) ($item['qty'] ?? 0);

        $inventoryDoc = $itemId !== null && $itemId !== '' ? $firestore->getDocument('medicines', $itemId) : null;
        if ($inventoryDoc === null || $qty <= 0) {
            $skippedItems[] = $itemName;
            continue;
        }

        $newStock = (int) ($inventoryDoc['currentStock'] ?? 0) + $qty;
        $minLevel = (int) ($inventoryDoc['minLevel'] ?? 0);

        $firestore->setDocument('medicines', $itemId, array_merge($inventoryDoc, [
            'currentStock' => $newStock,
            'status' => hc_inventory_status_for_stock($newStock, $minLevel),
            'lastUpdated' => date('c'),
        ]));

        $updatedItems[] = $itemName . ' (+' . $qty . ')';
    }
}

$auditDetails = ucfirst($newStatus) . ' stock request from ' . $requester . ' (' . (int) ($existing['itemsCount'] ?? 0) . ' item(s)).';
if ($newStatus === 'released') {
    if (!empty($updatedItems)) {
        $auditDetails .= ' Added to inventory: ' . implode(', ', $updatedItems) . '.';
    }
    if (!empty($skippedItems)) {
        $auditDetails .= ' Not matched to any inventory item (added manually if needed): ' . implode(', ', $skippedItems) . '.';
    }
}

write_audit_log($newStatus, 'Stock Requests', $auditDetails);

echo json_encode(['success' => true, 'docId' => $docId, 'status' => $newStatus, 'updatedItems' => $updatedItems, 'skippedItems' => $skippedItems]);
