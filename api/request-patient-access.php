<?php
/**
 * =====================================================================
 * Request Patient Access (api/request-patient-access.php)
 * ---------------------------------------------------------------------
 * Called by doctor/patient_management.php's "Request Access" button
 * (assets/js/patient-access-request.js) — step 1 of the patient-access
 * workflow (see includes/shared/patient_access_requests.php's header
 * comment for the full design). Creates a real patient_access_requests
 * doc with status 'pending_owner' — nobody gets any new access yet;
 * that only happens once BOTH the owning doctor
 * (api/decide-patient-access-request.php) AND the patient
 * (api/respond-patient-consent.php) say yes.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/patients.php'; // hc_get_patient_profile()
require_once __DIR__ . '/../includes/shared/patient_access_requests.php'; // hc_resolve_patient_owning_doctor()
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
$myName = $_SESSION['name'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myFacility = $myDoc['facility'] ?? '';

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$patientId = trim((string) ($body['patientId'] ?? ''));
$reason = trim((string) ($body['reason'] ?? ''));

if ($patientId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient id.']);
    exit;
}

$patient = hc_get_patient_profile($patientId);
// Same facility-isolation rule as includes/doctor/patient_profile_doctor_firestore.php
// — never even acknowledge a patient outside this doctor's own facility.
if ($patient === null || $patient['facility'] !== $myFacility) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

$owner = hc_resolve_patient_owning_doctor($patient);

if ($owner === null || $owner['uid'] === $myUid) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'You already have access to this patient.']);
    exit;
}

// Block a duplicate request while one is already in flight.
foreach ($firestore->listDocuments('patient_access_requests') as $doc) {
    $f = $doc['fields'];
    if (($f['patientId'] ?? '') === $patientId
        && ($f['requestedByUid'] ?? '') === $myUid
        && in_array($f['status'] ?? '', ['pending_owner', 'pending_patient'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'You already have a pending request for this patient.']);
        exit;
    }
}

$newId = $firestore->addDocument('patient_access_requests', [
    'patientId' => $patientId,
    'patientName' => $patient['name'] ?? '',
    'requestedByUid' => $myUid,
    'requestedByName' => $myName,
    'ownerUid' => $owner['uid'],
    'ownerName' => $owner['name'],
    'reason' => $reason,
    'initiatedBy' => 'colleague_request',
    'status' => 'pending_owner',
    'createdAt' => date('c'),
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not submit your request. Please try again.']);
    exit;
}

write_audit_log('created', 'Patient Management', 'Requested access to patient "' . ($patient['name'] ?? $patientId) . '" (owned by ' . $owner['name'] . ').');
hc_record_patient_activity($patientId, ($_SESSION['name'] ?? 'A doctor') . ' requested access to this patient\'s record.');

echo json_encode(['success' => true]);
