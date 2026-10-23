<?php
/**
 * =====================================================================
 * Create Stock Request (api/create-stock-request.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/new-stock-request.js when a pharmacist submits
 * pharmacist/stock_requests.php's "+ New Stock Request" modal
 * (includes/pharmacist/new_stock_request_modal.php). Writes a real doc
 * to the same "stock_requests" Firestore collection
 * subadmin/stock_requests.php already reads — a request posted here
 * shows up there immediately, no changes needed on that side.
 * pharmacistName/pharmacistUid/facility are all read from the signed-in
 * pharmacist's own session/Firestore doc, never trusted from the
 * client. Every new request starts "pending" — Approve/Reject/Release
 * happen from subadmin's (or pharmacist's own) three-dot menu via
 * api/update-stock-request-status.php, a separate endpoint.
 *
 * Each item also gets a real "itemId" resolved HERE, server-side, via
 * hc_find_inventory_item_by_name() — a case-insensitive exact match
 * against the real medicines/{id} docs of this request's own $type.
 * This is what lets api/update-stock-request-status.php's Release
 * Items step actually add each item's requested quantity onto real
 * inventory instead of just flipping a status label. A typed name
 * that doesn't match anything yet (a genuinely new item, see
 * includes/pharmacist/new_stock_request_modal.php's header comment on
 * why that's still allowed) just gets itemId null — Release still
 * works, it simply has nothing real to add stock to for that one item.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php'; // hc_find_inventory_item_by_name()
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
$dateNeeded = trim((string) ($body['dateNeeded'] ?? ''));
$rawItems = is_array($body['items'] ?? null) ? $body['items'] : [];

if (!in_array($type, ['medicine', 'vaccine'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please choose a request type.']);
    exit;
}
if ($dateNeeded === '' || strtotime($dateNeeded) === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid date needed.']);
    exit;
}
if (strtotime($dateNeeded) < strtotime(date('Y-m-d'))) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Date needed cannot be in the past.']);
    exit;
}

$items = [];
foreach ($rawItems as $item) {
    $name = trim((string) ($item['name'] ?? ''));
    $qty = $item['qty'] ?? null;
    if ($name === '' || !is_numeric($qty) || (int) $qty <= 0) {
        continue; // skip incomplete/blank rows rather than reject the whole request
    }
    $matched = hc_find_inventory_item_by_name($type, $name);
    $items[] = ['name' => $name, 'qty' => (int) $qty, 'itemId' => $matched['id'] ?? null];
}

if (count($items) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please add at least one item with a name and quantity.']);
    exit;
}

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$newId = $firestore->addDocument('stock_requests', [
    'pharmacistName' => $_SESSION['name'] ?? 'Pharmacist',
    'pharmacistUid' => $myUid,
    // Same default every other page already falls back to when
    // displaying this pharmacist's own facility (e.g.
    // includes/pharmacist/profile_settings_pharmacist_firestore.php) —
    // this was the one place still writing a blank string instead,
    // which showed as a real but misleading "—" on this request's own
    // Facility field forever after, even though every other page
    // quietly treats a blank facility as this clinic's own default.
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
    'status' => 'pending',
    'type' => $type,
    'items' => $items,
    'itemsCount' => count($items),
    'dateNeeded' => date('Y-m-d', strtotime($dateNeeded)),
    'requestedAt' => date('c'),
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not submit this request. Please try again.']);
    exit;
}

write_audit_log('created', 'Stock Requests', 'Submitted a ' . $type . ' stock request (' . count($items) . ' item(s)).');

echo json_encode(['success' => true, 'id' => $newId]);
