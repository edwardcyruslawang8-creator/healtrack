<?php
/**
 * =====================================================================
 * Decide Patient Access Request (api/decide-patient-access-request.php)
 * ---------------------------------------------------------------------
 * Called by the owning doctor from doctor/patient_management.php's
 * "Pending Access Requests" panel (assets/js/patient-access-request.js)
 * — step 2 of the workflow (see
 * includes/shared/patient_access_requests.php's header comment).
 *
 * action=deny: ends the request immediately, no email sent.
 *
 * action=approve: does NOT grant access by itself — the patient still
 * has to consent (see patient-consent.php / api/respond-patient-consent.php).
 * Generates a real one-time token the same way
 * includes/shared/remember_me.php's hc_issue_remember_token() does
 * (256-bit random, only sha256(token) ever stored server-side) and
 * emails the patient a consent link via hc_send_email(). If this
 * patient has no email on file at all — a real gap in today's data,
 * api/create-patient.php never collects one — this fails cleanly with
 * a clear error instead of silently doing nothing.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/constants.php'; // BASE_URL
require_once __DIR__ . '/../includes/firestore/patients.php'; // hc_get_patient_profile()
require_once __DIR__ . '/../includes/shared/patient_access_requests.php'; // HC_ACCESS_CONSENT_TOKEN_TTL_DAYS, hc_send_email(), hc_account_email_shell()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_activity()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$myUid = $_SESSION['uid'] ?? '';

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$requestId = trim((string) ($body['requestId'] ?? ''));
$action = trim((string) ($body['action'] ?? ''));

if ($requestId === '' || !in_array($action, ['approve', 'deny'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$existing = $firestore->getDocument('patient_access_requests', $requestId);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Request not found.']);
    exit;
}

// Only the real owning doctor this request names may decide it — never
// trust a client-supplied "I'm the owner" claim.
if (($existing['ownerUid'] ?? '') !== $myUid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if (($existing['status'] ?? '') !== 'pending_owner') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This request has already been decided.']);
    exit;
}

$updated = $existing;
$updated['ownerDecidedAt'] = date('c');

if ($action === 'deny') {
    $updated['status'] = 'denied_by_owner';
    $saved = $firestore->setDocument('patient_access_requests', $requestId, $updated);
    if (!$saved) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not save your decision. Please try again.']);
        exit;
    }
    write_audit_log('rejected', 'Patient Management', 'Denied ' . ($existing['requestedByName'] ?? 'a colleague') . '\'s access request for patient "' . ($existing['patientName'] ?? '') . '".');
    hc_record_patient_activity($existing['patientId'] ?? '', 'Access request from ' . ($existing['requestedByName'] ?? 'a doctor') . ' denied by ' . ($_SESSION['name'] ?? 'the owning doctor') . '.');
    echo json_encode(['success' => true, 'status' => 'denied_by_owner']);
    exit;
}

// action === 'approve' — real patient email required before anything else moves.
$patient = hc_get_patient_profile($existing['patientId'] ?? '');
$patientEmail = $patient['email'] ?? '';
if ($patient === null || $patientEmail === '' || $patientEmail === '—') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This patient has no email on file — a consent request cannot be sent.']);
    exit;
}

$rawToken = bin2hex(random_bytes(32)); // 256-bit — only this raw value ever leaves the server, via the emailed link
$tokenHash = hash('sha256', $rawToken);
$expiresAt = time() + (HC_ACCESS_CONSENT_TOKEN_TTL_DAYS * 86400);

$updated['status'] = 'pending_patient';
$updated['consentTokenHash'] = $tokenHash;
$updated['consentTokenExpiresAt'] = date('c', $expiresAt);

$saved = $firestore->setDocument('patient_access_requests', $requestId, $updated);
if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save your decision. Please try again.']);
    exit;
}

$consentUrl = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . BASE_URL
    . '/patient-consent.php?rid=' . urlencode($requestId) . '&token=' . urlencode($rawToken);

$emailSent = hc_send_email($patientEmail, $patient['name'] ?? '', 'Your consent is needed to share your medical record', hc_account_email_shell(
    'A doctor is requesting access to your record',
    '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">'
        . '<strong>' . htmlspecialchars($existing['requestedByName'] ?? 'A doctor') . '</strong> at your facility has asked '
        . '<strong>' . htmlspecialchars($existing['ownerName'] ?? 'your doctor') . '</strong> for permission to view your medical record.</p>'
    . '<p style="margin:0 0 18px; font-size:14px; line-height:1.6;">Your doctor has agreed to consider this, but nothing is shared without your own consent first. Please review the request and let us know your decision.</p>'
    . '<a href="' . htmlspecialchars($consentUrl) . '" style="display:inline-block; background:#5B0A0A; color:#fff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:13.5px; font-weight:600;">Review Consent Request</a>'
    . '<p style="margin:18px 0 0; font-size:12.5px; color:#6b7280;">This link expires in ' . HC_ACCESS_CONSENT_TOKEN_TTL_DAYS . ' days.</p>'
));

write_audit_log('approved', 'Patient Management', 'Approved ' . ($existing['requestedByName'] ?? 'a colleague') . '\'s access request for patient "' . ($existing['patientName'] ?? '') . '" — awaiting patient consent.');
hc_record_patient_activity($existing['patientId'] ?? '', 'Access request from ' . ($existing['requestedByName'] ?? 'a doctor') . ' approved by ' . ($_SESSION['name'] ?? 'the owning doctor') . ' — awaiting patient consent.');

echo json_encode(['success' => true, 'status' => 'pending_patient', 'emailSent' => $emailSent]);
