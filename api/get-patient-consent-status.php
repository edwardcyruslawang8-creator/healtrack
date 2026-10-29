<?php
/**
 * =====================================================================
 * Get Patient Consent Status (api/get-patient-consent-status.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/patient-consent-gate.js the instant a "View
 * Profile" link is clicked on subadmin/medical-staff/doctor's own
 * patient_management.php — decides, BEFORE navigating anywhere,
 * whether to let the click through to patient_profile.php (real
 * access) or open the "Request Access" modal in place instead (no
 * access yet), without a full extra page load either way.
 *
 * This is purely a read — includes/shared/patient_consent.php's own
 * hc_patient_consent_status() is the SAME real check
 * patient_profile.php itself re-runs server-side before ever showing
 * anything, so this is a UX convenience only, never the actual
 * security boundary (a user could skip this call entirely and hit
 * patient_profile.php directly — it still gates correctly on its own).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/patients.php'; // hc_get_patient_profile()
require_once __DIR__ . '/../includes/shared/patient_consent.php'; // hc_patient_consent_status()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['subadmin', 'healthcare_staff', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$patientId = trim((string) ($_GET['patientId'] ?? ''));
if ($patientId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient id.']);
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

$status = hc_patient_consent_status($patientId, $myUid, $_SESSION['role'], $_SESSION['name'] ?? '');

echo json_encode([
    'success' => true,
    'access' => $status['access'],
    'patientName' => $patient['name'],
    'pendingRequest' => $status['pendingRequest'],
    'declinedRequest' => $status['declinedRequest'],
]);
