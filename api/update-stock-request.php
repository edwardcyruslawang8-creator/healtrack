<?php
/**
 * =====================================================================
 * Update Stock Request (api/update-stock-request.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/new-stock-request.js when a pharmacist OR
 * sub-admin submits the "Edit Request" menu item's modal
 * (includes/pharmacist/new_stock_request_modal.php, reused as-is on
 * subadmin/stock_requests.php too) — the same shared form
 * api/create-stock-request.php's modal uses, just pre-filled and
 * posting here instead. Writes to the same real "stock_requests"
 * Firestore collection includes/firestore/stock_requests.php reads.
 *
 * Two guardrails checked server-side, not just hidden in the UI:
 *   - Ownership: a pharmacist may only edit their OWN request
 *     (pharmacistUid must match the signed-in session) — the page only
 *     ever lists their own rows, but this endpoint doesn't trust that
 *     alone. A sub-admin is exempt from this check — unlike a
 *     pharmacist, who only ever sees their own requests, a sub-admin
 *     manages every pharmacist's requests at their facility, and can
 *     edit any of them (a deliberate decision — see this app's
 *     Medicine & Vaccine Inventory page for the same subadmin-can-
 *     edit-what-pharmacist-created pattern).
 *   - Pending-only: once a request has been approved/rejected/
 *     released, editing it would silently change what a sub-admin
 *     already decided on — only "pending" requests can be edited,
 *     regardless of who's editing.
 * pharmacistName/pharmacistUid/facility/status/requestedAt are all
 * preserved from the existing doc (read-merge-write), same reasoning
 * as api/update-medicine.php's header comment.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['pharmacist', 'subadmin'], true)) {
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
$type = trim((string) ($body['type'] ?? ''));
$dateNeeded = trim((string) ($body['dateNeeded'] ?? ''));
$rawItems = is_array($body['items'] ?? null) ? $body['items'] : [];

if ($docId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing request id.']);
    exit;
}
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

$items = [];
foreach ($rawItems as $item) {
    $name = trim((string) ($item['name'] ?? ''));
    $qty = $item['qty'] ?? null;
    if ($name === '' || !is_numeric($qty) || (int) $qty <= 0) {
        continue;
    }
    $items[] = ['name' => $name, 'qty' => (int) $qty];
}
if (count($items) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please add at least one item with a name and quantity.']);
    exit;
}

$existing = $firestore->getDocument('stock_requests', $docId);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This request no longer exists.']);
    exit;
}
if ($role === 'pharmacist' && ($existing['pharmacistUid'] ?? '') !== ($_SESSION['uid'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only edit your own requests.']);
    exit;
}
if ($role === 'subadmin') {
    // "Manages every pharmacist's requests AT THEIR FACILITY" (see
    // header comment) means at their own facility, never another one —
    // resolved server-side from their own users/{uid} doc, same as
    // api/update-stock-request-status.php's own copy of this check.
    $myUid = $_SESSION['uid'] ?? '';
    $myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
    $myFacility = $myDoc['facility'] ?? null;
    if ($myFacility === null || ($existing['facility'] ?? '') !== $myFacility) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'This request no longer exists.']);
        exit;
    }
}
if (($existing['status'] ?? 'pending') !== 'pending') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'This request has already been ' . $existing['status'] . ' and can no longer be edited.']);
    exit;
}

$updated = array_merge($existing, [
    'type' => $type,
    'items' => $items,
    'itemsCount' => count($items),
    'dateNeeded' => date('Y-m-d', strtotime($dateNeeded)),
]);

$saved = $firestore->setDocument('stock_requests', $docId, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save your changes. Please try again.']);
    exit;
}

write_audit_log('updated', 'Stock Requests', 'Edited a ' . $type . ' stock request (' . count($items) . ' item(s)).');

echo json_encode(['success' => true, 'docId' => $docId]);
