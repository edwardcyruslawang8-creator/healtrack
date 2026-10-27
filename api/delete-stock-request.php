<?php
/**
 * =====================================================================
 * Delete Stock Request (api/delete-stock-request.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/stock_requests.js when a subadmin or pharmacist
 * confirms "Delete Request" from a row's three-dot menu on
 * subadmin/stock_requests.php or pharmacist/stock_requests.php.
 * Deletes a real stock_requests/{autoId} doc from Firestore — same
 * delete pattern as api/delete-medicine.php.
 *
 * Ownership check: a pharmacist may only delete their OWN request
 * (pharmacistUid must match the signed-in session) — a subadmin can
 * still delete any request AT THEIR OWN FACILITY, since reviewing
 * every pharmacist's there is a sub-admin capability, but never another
 * facility's. pharmacist/stock_requests.php only ever lists the
 * signed-in pharmacist's own rows, but this endpoint doesn't trust
 * that alone.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['subadmin', 'pharmacist'], true)) {
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

if ($docId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing request id.']);
    exit;
}

$existing = $firestore->getDocument('stock_requests', $docId);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This request no longer exists.']);
    exit;
}
if (($_SESSION['role'] ?? null) === 'pharmacist' && ($existing['pharmacistUid'] ?? '') !== ($_SESSION['uid'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only delete your own requests.']);
    exit;
}
if (($_SESSION['role'] ?? null) === 'subadmin') {
    $myUid = $_SESSION['uid'] ?? '';
    $myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
    $myFacility = $myDoc['facility'] ?? null;
    if ($myFacility === null || ($existing['facility'] ?? '') !== $myFacility) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'This request no longer exists.']);
        exit;
    }
}

$deleted = $firestore->deleteDocument('stock_requests', $docId);

if (!$deleted) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not delete this request. Please try again.']);
    exit;
}

$requester = $existing['pharmacistName'] ?? 'Unknown';
write_audit_log('deleted', 'Stock Requests', 'Deleted stock request from ' . $requester . ' (' . (int) ($existing['itemsCount'] ?? 0) . ' item(s)).');

echo json_encode(['success' => true]);
