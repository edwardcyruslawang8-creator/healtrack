<?php
/**
 * =====================================================================
 * Add Chronic Condition (api/add-condition.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medical-history.js from the Medical History tab's
 * Chronic Conditions section (subadmin/doctor/medical-staff's
 * medical_history.php). Writes to the real "medical_conditions"
 * collection — same one api/create-patient.php's registration wizard
 * already writes to (Step 3's Medical History block), in the exact
 * field shape includes/firestore/medical_records.php's
 * hc_get_medical_conditions_for_patient() reads back.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php'; // hc_user_can_access_patient_document() — same real patient-access rule (doctor ownership/approved request, or matching facility for subadmin/medical-staff), reused here beyond just documents
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

// Sub-admins have read-only access to medical history.
if (!in_array($_SESSION['role'] ?? null, ['doctor', 'healthcare_staff'], true)) {
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
$label = trim((string) ($body['label'] ?? ''));
$diagnosedOn = trim((string) ($body['diagnosedOn'] ?? ''));
$details = trim((string) ($body['details'] ?? '')) ?: null;
$status = trim((string) ($body['status'] ?? ''));
$status = in_array($status, ['Active', 'Controlled', 'Resolved'], true) ? $status : 'Active';

if ($patientId === '' || $label === '' || $diagnosedOn === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter the condition and date diagnosed.']);
    exit;
}
if (strtotime($diagnosedOn) === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid date diagnosed.']);
    exit;
}

$patient = $firestore->getDocument('patients', $patientId);
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This patient could not be found.']);
    exit;
}
$patient['id'] = $patientId;
if (!hc_user_can_access_patient_document($patient)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This patient could not be found.']);
    exit;
}

$newId = $firestore->addDocument('medical_conditions', [
    'patientId' => $patientId,
    'label' => $label,
    'details' => $details,
    'status' => $status,
    'diagnosedOn' => date('Y-m-d', strtotime($diagnosedOn)),
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this condition. Please try again.']);
    exit;
}

write_audit_log('created', 'Patient Management', 'Added chronic condition "' . $label . '" for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'medical_condition', 'added', 'Added chronic condition "' . $label . '" (status: ' . $status . ').');
hc_record_patient_activity($patientId, 'Chronic condition "' . $label . '" recorded.');

echo json_encode(['success' => true, 'id' => $newId]);
