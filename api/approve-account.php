<?php
/**
 * =====================================================================
 * Approve Pending Account (api/approve-account.php)
 * ---------------------------------------------------------------------
 * Called by subadmin/user_management.php's "Pending Approvals" table
 * (Approve button) — deciding on a self-registration request is a
 * sub-admin capability, not admin's. Flips a self-registered account's
 * (register.php) status from "pending" to "active" — that's the
 * moment real access actually begins, since api/session-login.php
 * refuses to start a session for anything but an active account.
 *
 * Also requires and saves a "facility" — register.php's public form
 * never asks for one, so approval is the first real point this ever
 * gets set. Required (not optional) since it drives real downstream
 * filtering (e.g. includes/firestore/consultations.php's
 * hc_get_physician_options(), which medical-staff/consultations.php's
 * "Assigned Physician" list uses) — an approved account with no
 * facility would just be silently invisible wherever that matters.
 * FORCED to the approving sub-admin's own real facility, never the
 * client-submitted value — a sub-admin only manages their own site, so
 * approving a new hire "into" a different facility isn't a real
 * capability of theirs even though the request body still carries a
 * facility field (kept so the request shape doesn't need to change,
 * matched/validated against $myFacility below instead of trusted).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/constants.php'; // BASE_URL
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

// Never trust a client-submitted facility — force it to the approving
// sub-admin's own real facility (see header comment).
$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$facility = $myDoc['facility'] ?? null;
if ($facility === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Could not resolve your own facility. Please contact your administrator.']);
    exit;
}

$existing = $firestore->getDocument('users', $uid);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Account not found.']);
    exit;
}
if (($existing['status'] ?? '') !== 'pending') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This account is not pending approval.']);
    exit;
}

$updated = $existing;
$updated['status'] = 'active';
$updated['facility'] = $facility;
$updated['approvedAt'] = date('c');
$updated['approvedBy'] = $_SESSION['uid'] ?? '';

$saved = $firestore->setDocument('users', $uid, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not approve this account. Please try again.']);
    exit;
}

write_audit_log('approved', 'User Management', 'Approved the account request from ' . ($existing['name'] ?? $uid) . ' (' . ($existing['jobTitle'] ?? $existing['role'] ?? '') . ').');

if (!empty($existing['email'])) {
    $loginUrl = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . BASE_URL . '/login.php';
    hc_send_email($existing['email'], $existing['name'] ?? '', 'Your HEALTrack account has been approved', hc_account_email_shell(
        'You\'re approved!',
        '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Good news — your request to join HEALTrack as a <strong>' . htmlspecialchars($existing['jobTitle'] ?? $existing['role'] ?? '') . '</strong> at <strong>' . htmlspecialchars($facility) . '</strong> has been approved.</p>'
        . '<p style="margin:0 0 18px; font-size:14px; line-height:1.6;">You can sign in now using the email and password you registered with.</p>'
        . '<a href="' . htmlspecialchars($loginUrl) . '" style="display:inline-block; background:#5B0A0A; color:#fff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:13.5px; font-weight:600;">Sign In Now</a>'
    ));
}

echo json_encode(['success' => true]);
