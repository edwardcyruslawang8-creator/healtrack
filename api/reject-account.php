<?php
/**
 * =====================================================================
 * Reject Pending Account (api/reject-account.php)
 * ---------------------------------------------------------------------
 * Called by subadmin/user_management.php's "Pending Approvals" table
 * (Reject button) — deciding on a self-registration request is a
 * sub-admin capability, not admin's. Deletes a self-registered
 * account's (register.php) request entirely — both the Firestore
 * users/{uid} doc and the Firebase Auth account itself — since this
 * app has no "rejected" status to leave the record in; rejecting just
 * means the request never happened.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/mailer.php'; // hc_send_email(), hc_account_email_shell()

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
$uid = trim((string) ($body['uid'] ?? ''));

if ($uid === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing account id.']);
    exit;
}

$existing = $firestore->getDocument('users', $uid);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Account not found.']);
    exit;
}

// Never trust the client-supplied uid alone — it must belong to this
// sub-admin's own facility, resolved server-side from their own
// users/{uid} doc, same as every other facility-scoped write in this app.
$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myFacility = $myDoc['facility'] ?? null;
if ($myFacility === null || ($existing['facility'] ?? '') !== $myFacility) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Account not found.']);
    exit;
}

if (($existing['status'] ?? '') !== 'pending') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This account is not pending approval.']);
    exit;
}

$name = $existing['name'] ?? $uid;
$jobTitle = $existing['jobTitle'] ?? $existing['role'] ?? '';
$email = $existing['email'] ?? '';

$firestore->deleteDocument('users', $uid);

try {
    $firebaseAuth->deleteUser($uid);
} catch (\Throwable $e) {
    // The Firestore doc is already gone (the important part — no more
    // pending row, no access), so a failure here just leaves an orphaned
    // Auth account with no matching profile; log it but don't fail the
    // request over it.
    error_log('[reject-account] Could not delete Firebase Auth user ' . $uid . ': ' . $e->getMessage());
}

write_audit_log('deleted', 'User Management', 'Rejected account request from "' . $name . '" (' . $jobTitle . ').');

if ($email !== '') {
    hc_send_email($email, $name, 'Your HEALTrack account request was declined', hc_account_email_shell(
        'Request declined',
        '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Your request to join HEALTrack as a <strong>' . htmlspecialchars($jobTitle) . '</strong> was not approved.</p>'
        . '<p style="margin:0 0 0; font-size:14px; line-height:1.6;">If you believe this was a mistake, or have questions about why, please contact your facility\'s administrator directly.</p>'
    ));
}

echo json_encode(['success' => true]);
