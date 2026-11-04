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
 * "profile_access" consent_records doc, pending sub-admin review.
 *
 * The PATIENT is not contacted here — the sub-admin decides
 * (api/review-patient-consent.php), and the patient only hears
 * anything once that decision is made (email, either way).
 *
 * Never trusts the client-supplied patientId alone — it must belong to
 * this staff member's own facility, same 404-on-mismatch pattern every
 * other facility-scoped write in this app already uses (see
 * api/update-consultation.php's own copy of this).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/patients.php'; // hc_get_patient_profile()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

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
    'emailSentAt' => null,
    'smsSentAt' => null,
    'respondedAt' => null,
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not send this request. Please try again.']);
    exit;
}

write_audit_log('created', 'Consent Management', 'Requested access to ' . ($patient['name'] ?? 'a patient') . "'s details.");

echo json_encode(['success' => true]);
