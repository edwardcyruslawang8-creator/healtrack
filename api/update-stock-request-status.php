<?php
/**
 * =====================================================================
 * Update Stock Request Status — Approve / Reject (api/update-stock-request-status.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/stock_requests.js when a subadmin picks "Approve
 * Request"/"Reject Request" from a row's three-dot menu on
 * subadmin/stock_requests.php. Writes a real status change to the same
 * "stock_requests" Firestore collection includes/firestore/stock_requests.php
 * reads.
 *
 * Subadmin-only — deciding on a request (approve/reject) is a
 * sub-admin capability, not the requesting pharmacist's own. A
 * pharmacist manages their own request's existence (create/edit/
 * delete, see api/create-stock-request.php, api/update-stock-request.php,
 * api/delete-stock-request.php) but not its outcome.
 *
 * Each target status only makes sense from one specific current status
 * — pending -> approved/rejected — so $allowedFrom below is checked
 * server-side too, not just hidden in the UI (the UI hides/disables
 * the menu items that don't apply to a row's current status, but this
 * endpoint doesn't trust that alone).
 *
 * "Release Items" (approved -> released, which also created real
 * inventory batches per item) was removed entirely by request — an
 * approved request has no further status change from this page; real
 * stock is added separately via subadmin/medicine_vaccine_inventory.php.
 * Some requests may still carry a "released" status from before this
 * removal — that's a read/display concern for other pages (e.g.
 * includes/subadmin/stock_request_reports_firestore.php), not this
 * endpoint, which no longer accepts it as a target.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
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
// from — the only real workflow this endpoint supports now.
$allowedFrom = [
    'approved' => 'pending',
    'rejected' => 'pending',
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
    // Used by includes/firestore/stock_requests.php's
    // hc_get_decided_stock_request_notifications_for_subadmin() to show
    // "you decided this" only to the sub-admin who actually did —
    // 'reviewedBy' alone is just a display name, not safe to match a
    // signed-in account against (two sub-admins could share a name).
    'reviewedByUid' => $myUid,
    'reviewedAt' => date('c'),
]);

$saved = $firestore->setDocument('stock_requests', $docId, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this change. Please try again.']);
    exit;
}

$requester = $existing['pharmacistName'] ?? 'Unknown';

$auditDetails = ucfirst($newStatus) . ' stock request from ' . $requester . ' (' . (int) ($existing['itemsCount'] ?? 0) . ' item(s)).';

write_audit_log($newStatus, 'Stock Requests', $auditDetails);

echo json_encode(['success' => true, 'docId' => $docId, 'status' => $newStatus]);
