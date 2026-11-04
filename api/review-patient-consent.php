<?php
/**
 * =====================================================================
 * Review Patient Consent — Approve / Reject (api/review-patient-consent.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/consent_management.js when a sub-admin clicks
 * Approve/Reject on a pending "profile_access" row in
 * subadmin/consent_management.php. This IS the real decision now —
 * the patient no longer approves/declines their own access requests
 * (api/request-patient-consent.php only ever creates the "pending"
 * consent_records doc; this endpoint is the only thing that ever
 * flips its status). includes/shared/patient_consent.php's access gate
 * just reads that status, so this is the one place that actually
 * grants or denies a staff member's access to a patient's profile.
 *
 * Once decided, the patient is informed either way (approved or
 * rejected) by email — informational only, no link, no action expected
 * from them (patients have no login/portal in this app). SMS was
 * decided against for now (email only); includes/shared/sms.php's
 * hc_send_sms() still exists as a safe no-op stub if that changes.
 *
 * Same facility-ownership pattern as api/update-stock-request-status.php:
 * never trusts the client-supplied consentId alone — the consent's own
 * patient must belong to this sub-admin's own facility.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/patients.php'; // hc_get_patient_profile()
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
$consentId = trim((string) ($body['consentId'] ?? ''));
$decision = trim((string) ($body['decision'] ?? ''));

if ($consentId === '' || !in_array($decision, ['accepted', 'rejected'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$existing = $firestore->getDocument('consent_records', $consentId);
if ($existing === null || ($existing['type'] ?? '') !== 'profile_access') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This request no longer exists.']);
    exit;
}

if (($existing['status'] ?? 'pending') !== 'pending') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'This request has already been decided.']);
    exit;
}

// Never trust the client-supplied consentId alone — the patient it's
// about must belong to this sub-admin's own facility, resolved
// server-side, same as every other facility-scoped write in this app.
$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myFacility = $myDoc['facility'] ?? null;

$patientId = $existing['patientId'] ?? '';
$patient = hc_get_patient_profile($patientId);
if ($myFacility === null || $patient === null || ($patient['facility'] ?? '') !== $myFacility) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This request no longer exists.']);
    exit;
}

$now = date('c');
$updated = array_merge($existing, [
    'status' => $decision,
    'approvedBy' => $_SESSION['name'] ?? 'Sub-Administrator',
    'respondedAt' => $now,
]);

$saved = $firestore->setDocument('consent_records', $consentId, $updated);
if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this decision. Please try again.']);
    exit;
}

$requesterRoleLabel = match ($existing['requestedByRole'] ?? '') {
    'subadmin' => 'Sub-Administrator',
    'doctor' => 'Doctor',
    'healthcare_staff' => 'Medical Staff',
    default => 'Staff',
};
$requesterName = $existing['requestedByName'] ?? 'A staff member';
$facility = $myFacility;
$patientName = $patient['name'] ?? ($existing['patientName'] ?? 'Patient');

if ($decision === 'accepted') {
    $emailSubject = 'Your HEALTrack patient details access request was approved';
    $emailBody = hc_account_email_shell(
        'Access request approved',
        '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Hi ' . htmlspecialchars($patientName) . ', <strong>' . htmlspecialchars($requesterName) . '</strong> (' . htmlspecialchars($requesterRoleLabel) . ') at ' . htmlspecialchars($facility) . ' has been granted access to view your patient details.</p>'
        . '<p style="margin:0; font-size:14px; line-height:1.6;"><strong>Reason given:</strong> ' . htmlspecialchars($existing['purpose'] ?? '') . '</p>'
    );
} else {
    $emailSubject = 'Your HEALTrack patient details access request was declined';
    $emailBody = hc_account_email_shell(
        'Access request declined',
        '<p style="margin:0; font-size:14px; line-height:1.6;">Hi ' . htmlspecialchars($patientName) . ', a request by <strong>' . htmlspecialchars($requesterName) . '</strong> (' . htmlspecialchars($requesterRoleLabel) . ') to view your patient details at ' . htmlspecialchars($facility) . ' was reviewed and declined.</p>'
    );
}

$emailSent = hc_send_email($patient['email'] ?? '', $patientName, $emailSubject, $emailBody);

$firestore->setDocument('consent_records', $consentId, array_merge(
    $firestore->getDocument('consent_records', $consentId) ?? $updated,
    [
        'emailSentAt' => $emailSent ? $now : null,
    ]
));

write_audit_log(
    $decision === 'accepted' ? 'approved' : 'rejected',
    'Consent Management',
    'Reviewed a patient details access request for ' . $patientName . ' — ' . $decision . '.'
);

echo json_encode(['success' => true, 'consentId' => $consentId, 'status' => $decision]);
