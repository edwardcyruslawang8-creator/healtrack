<?php
/**
 * =====================================================================
 * Create Consultation (api/create-consultation.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/consultations_medstaff.js when a medical staff
 * member submits the "Set Consultation" modal
 * (medical-staff/consultations.php). Writes a new document to the real
 * "consultations" Firestore collection — the first real consultations
 * model in this app (previously front-end only, see
 * medical-staff/consultations.php's earlier header comments).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/consultations.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/vitals_validation.php'; // hc_validate_vitals()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()
require_once __DIR__ . '/../includes/shared/mailer.php'; // hc_send_email(), hc_account_email_shell() — assigned-doctor email below

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
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
$patientName = trim((string) ($body['patientName'] ?? ''));
$consultationType = trim((string) ($body['consultationType'] ?? ''));
$dateTime = trim((string) ($body['dateTime'] ?? ''));
$assignedPersonnel = trim((string) ($body['assignedPersonnel'] ?? ''));
$chiefComplaint = trim((string) ($body['chiefComplaint'] ?? ''));
$priority = trim((string) ($body['priority'] ?? ''));
$nextFollowUp = trim((string) ($body['nextFollowUp'] ?? ''));
$notes = trim((string) ($body['notes'] ?? ''));

// Vital Signs — required (matching the "Add Patient" modal's own Vital
// Signs step), taken here at check-in time by medical staff so
// doctor/active_consultation.php's "Vital Signs (from Front Desk)" card
// shows THIS visit's reading instead of only ever falling back to the
// patient's stale registration-time intake snapshot. BMI is excluded
// from the required set below — it's auto-calculated client-side from
// Weight/Height, never itself a direct input.
$rawVitals = is_array($body['vitals'] ?? null) ? $body['vitals'] : [];
$vitals = [
    'temperature' => trim((string) ($rawVitals['temperature'] ?? '')),
    'bloodPressureSystolic' => trim((string) ($rawVitals['bloodPressureSystolic'] ?? '')),
    'bloodPressureDiastolic' => trim((string) ($rawVitals['bloodPressureDiastolic'] ?? '')),
    'heartRate' => trim((string) ($rawVitals['heartRate'] ?? '')),
    'weight' => trim((string) ($rawVitals['weight'] ?? '')),
    'height' => trim((string) ($rawVitals['height'] ?? '')),
    'bmi' => trim((string) ($rawVitals['bmi'] ?? '')),
];

$validTypes = array_keys(hc_consultation_type_meta());
$validPriorities = array_keys(hc_consultation_priority_meta());
if ($patientId === '' || $patientName === '' || !in_array($consultationType, $validTypes, true)
    || $dateTime === '' || $assignedPersonnel === '' || $chiefComplaint === ''
    || $vitals['temperature'] === '' || $vitals['bloodPressureSystolic'] === '' || $vitals['bloodPressureDiastolic'] === ''
    || $vitals['heartRate'] === '' || $vitals['weight'] === '' || $vitals['height'] === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every required field, including Vital Signs.']);
    exit;
}
// Presence already checked above — this only re-checks real ranges.
$vitalsError = hc_validate_vitals($vitals, false);
if ($vitalsError !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $vitalsError]);
    exit;
}
if ($priority !== '' && !in_array($priority, $validPriorities, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please choose a valid priority, or leave it blank.']);
    exit;
}
if (strtotime($dateTime) < time()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Date & time cannot be in the past.']);
    exit;
}
if ($nextFollowUp !== '' && strtotime($nextFollowUp) < time()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Next follow-up cannot be in the past.']);
    exit;
}

$allConsultations = hc_get_all_consultations();

// A patient can't be double-booked at the exact same date & time — a
// cancelled slot doesn't count (there's really nothing scheduled there
// anymore), but pending/ongoing/completed all do.
foreach ($allConsultations as $existingConsult) {
    if ($existingConsult['patientId'] === $patientId && $existingConsult['dateTime'] === $dateTime && $existingConsult['status'] !== 'cancelled') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'This patient already has a consultation scheduled at that exact date and time.']);
        exit;
    }
}

$consultationCode = hc_next_consultation_code($allConsultations);

$newId = $firestore->addDocument('consultations', [
    'consultationCode' => $consultationCode,
    'patientId' => $patientId,
    'patientName' => $patientName,
    'consultationType' => $consultationType,
    'dateTime' => $dateTime,
    'assignedPersonnel' => $assignedPersonnel,
    'chiefComplaint' => $chiefComplaint,
    'priority' => $priority !== '' ? $priority : null,
    'nextFollowUp' => $nextFollowUp !== '' ? $nextFollowUp : null,
    'notes' => $notes !== '' ? $notes : null,
    'vitals' => $vitals,
    'status' => 'pending',
    'createdAt' => date('c'),
    'createdBy' => $_SESSION['uid'] ?? '',
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the consultation. Please try again.']);
    exit;
}

write_audit_log('created', 'Consultations', 'Scheduled a consultation for ' . $patientName . ' (' . $consultationCode . ').');
hc_record_patient_history($patientId, 'medical_record', 'added', 'Consultation ' . $consultationCode . ' scheduled (' . $consultationType . ').');
hc_record_patient_activity($patientId, 'Consultation ' . $consultationCode . ' scheduled.');

// Email the specific doctor just assigned — so they're notified even
// while signed out/away from the computer. $assignedPersonnel is a
// real name picked from hc_get_physician_options()'s dropdown
// (medical-staff/consultations.php), resolved back to a real
// users/{uid} doc here by role+name match to get a real email address.
foreach ($firestore->listDocuments('users') as $userDoc) {
    $uf = $userDoc['fields'];
    if (($uf['role'] ?? '') !== 'doctor' || ($uf['name'] ?? '') !== $assignedPersonnel) {
        continue;
    }
    $doctorEmail = trim((string) ($uf['email'] ?? ''));
    if ($doctorEmail !== '') {
        $whenLabel = strtotime($dateTime) !== false ? date('F j, Y g:i A', strtotime($dateTime)) : $dateTime;
        hc_send_email($doctorEmail, $assignedPersonnel, 'New consultation assigned — ' . $patientName, hc_account_email_shell(
            'You have a new consultation scheduled',
            '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Hi ' . htmlspecialchars($assignedPersonnel) . ', ' . htmlspecialchars($_SESSION['name'] ?? 'a healthcare staff member') . ' just scheduled a new consultation for you.</p>'
            . '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;"><strong>Patient:</strong> ' . htmlspecialchars($patientName) . '<br>'
            . '<strong>Date &amp; time:</strong> ' . htmlspecialchars($whenLabel) . '<br>'
            . '<strong>Chief complaint:</strong> ' . htmlspecialchars($chiefComplaint) . '</p>'
            . '<p style="margin:0; font-size:14px; line-height:1.6;">Sign in to HEALTrack to view the full details.</p>'
        ));
    }
    break;
}

echo json_encode(['success' => true, 'consultation' => ['id' => $newId, 'consultationCode' => $consultationCode]]);
