<?php
/**
 * =====================================================================
 * Request Patient Consent (api/request-patient-consent.php)
 * ---------------------------------------------------------------------
 * Called from the "Patient Details Restricted" card
 * (subadmin/medical-staff/doctor's own patient_profile.php, rendered
 * instead of the full profile whenever includes/shared/patient_consent.php's
 * hc_patient_consent_status() says no real access path applies yet) —
 * a staff member types a real reason and this writes a new
 * "profile_access" consent_records doc, emails the patient a real
 * link to approve/decline it (consent-response.php?token=...), and
 * also tries SMS (includes/shared/sms.php's hc_send_sms() — safely a
 * no-op until a real provider is wired in).
 *
 * Never trusts the client-supplied patientId alone — it must belong to
 * this staff member's own facility, same 404-on-mismatch pattern every
 * other facility-scoped write in this app already uses (see
 * api/update-consultation.php's own copy of this).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/constants.php'; // BASE_URL
require_once __DIR__ . '/../includes/firestore/patients.php'; // hc_get_patient_profile()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/mailer.php'; // hc_send_email(), hc_account_email_shell()
require_once __DIR__ . '/../includes/shared/sms.php'; // hc_send_sms()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['subadmin', 'healthcare_staff', 'doctor'], true)) {
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
$patientId = trim((string) ($body['patientId'] ?? ''));
$reason = trim((string) ($body['reason'] ?? ''));

if ($patientId === '' || $reason === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please explain why you need to view this patient\'s details.']);
    exit;
}

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myFacility = $myDoc['facility'] ?? null;

$patient = hc_get_patient_profile($patientId);
// Same-facility, OR a different facility but this patient's own real
// "Share My Information" consent (api/create-patient.php's
// 'consentShareInfo') is true — see includes/firestore/patients.php's
// own comment on that field.
if ($myFacility === null || $patient === null || ($patient['facility'] !== $myFacility && !$patient['consentShareInfo'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

$patientEmail = $patient['email'] ?? '';
if ($patientEmail === '' || $patientEmail === '—') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This patient has no email on file — a consent request can\'t be sent.']);
    exit;
}

// Refuse a duplicate — an accepted or still-pending request for this
// exact (patient, requester) pair already covers it.
$existingRequests = array_values(array_filter(
    $firestore->listDocuments('consent_records'),
    fn ($doc) => ($doc['fields']['patientId'] ?? '') === $patientId && ($doc['fields']['requestedByUid'] ?? '') === $myUid
));
foreach ($existingRequests as $doc) {
    $status = $doc['fields']['status'] ?? 'pending';
    if ($status === 'accepted') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'You already have access to this patient\'s details.']);
        exit;
    }
    if ($status === 'pending') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'A consent request for this patient is already awaiting their response.']);
        exit;
    }
}

$roleLabel = match ($_SESSION['role']) {
    'subadmin' => 'Sub-Administrator',
    'doctor' => 'Doctor',
    'healthcare_staff' => 'Medical Staff',
    default => 'Staff',
};

$token = bin2hex(random_bytes(32));
$now = date('c');

$newId = $firestore->addDocument('consent_records', [
    'patientId' => $patientId,
    'patientName' => $patient['name'] ?? '',
    'type' => 'profile_access',
    'purpose' => $reason,
    'status' => 'pending',
    'givenOn' => $now,
    'expirationDate' => null,
    'approvedBy' => null,
    'notes' => null,
    'signature' => null,
    'attachedFile' => null,
    'requestedByUid' => $myUid,
    'requestedByName' => $_SESSION['name'] ?? '',
    'requestedByRole' => $_SESSION['role'],
    'respondToken' => $token,
    'tokenExpiresAt' => date('c', strtotime('+14 days')),
    'tokenUsed' => false,
    'emailSentAt' => null,
    'smsSentAt' => null,
    'respondedAt' => null,
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not send this request. Please try again.']);
    exit;
}

$scheme = (($_SERVER['HTTPS'] ?? 'off') !== 'off') ? 'https://' : 'http://';
$responseUrl = $scheme . $_SERVER['HTTP_HOST'] . BASE_URL . '/consent-response.php?token=' . $token;

$emailSent = hc_send_email(
    $patientEmail,
    $patient['name'] ?? '',
    'A HEALTrack staff member is requesting access to your patient details',
    hc_account_email_shell(
        'Someone wants to view your patient details',
        '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Hi ' . htmlspecialchars($patient['name'] ?? '') . ', <strong>' . htmlspecialchars($_SESSION['name'] ?? '') . '</strong> (' . htmlspecialchars($roleLabel) . ') at HEALTrack is requesting your permission to view your full patient details.</p>'
        . '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;"><strong>Reason given:</strong> ' . htmlspecialchars($reason) . '</p>'
        . '<p style="margin:0 0 18px; font-size:14px; line-height:1.6;">Please review this request and choose whether to approve or decline it. This link is only valid for you and expires in 14 days.</p>'
        . '<a href="' . htmlspecialchars($responseUrl) . '" style="display:inline-block; background:#5B0A0A; color:#fff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:13.5px; font-weight:600;">Review This Request</a>'
    )
);

$smsSent = hc_send_sms(
    $patient['phone'] ?? '',
    'HEALTrack: ' . ($_SESSION['name'] ?? 'A staff member') . ' is requesting access to your patient details. Review: ' . $responseUrl
);

if ($emailSent || $smsSent) {
    $firestore->setDocument('consent_records', $newId, array_merge(
        $firestore->getDocument('consent_records', $newId) ?? [],
        [
            'emailSentAt' => $emailSent ? $now : null,
            'smsSentAt' => $smsSent ? $now : null,
        ]
    ));
}

write_audit_log('created', 'Consent Management', 'Requested access to ' . ($patient['name'] ?? 'a patient') . "'s details.");

echo json_encode(['success' => true]);
