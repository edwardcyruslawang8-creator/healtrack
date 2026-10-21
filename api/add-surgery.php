<?php
/**
 * =====================================================================
 * Add Surgery / Procedure (api/add-surgery.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medical-history.js from the Medical History tab's
 * Past Surgeries/Procedures section. Writes to the real
 * "surgical_history" collection — same one api/create-patient.php's
 * registration wizard already writes to (Step 3), but this is the
 * first path that also lets 'datePerformed' (the real date the
 * surgery happened, not just when it was recorded) and
 * 'surgeonFacility' be set — see
 * includes/firestore/medical_records.php's hc_get_surgical_history_for_patient()
 * for how those two new fields are read back.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php'; // hc_user_can_access_patient_document()
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
$procedure = trim((string) ($body['procedure'] ?? ''));
$datePerformed = trim((string) ($body['datePerformed'] ?? ''));
$surgeonFacility = trim((string) ($body['surgeonFacility'] ?? '')) ?: null;
$details = trim((string) ($body['details'] ?? '')) ?: null;

if ($patientId === '' || $procedure === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter the procedure.']);
    exit;
}
if ($datePerformed !== '' && strtotime($datePerformed) === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid date.']);
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

$newId = $firestore->addDocument('surgical_history', [
    'patientId' => $patientId,
    'procedure' => $procedure,
    'datePerformed' => $datePerformed !== '' ? date('Y-m-d', strtotime($datePerformed)) : null,
    'surgeonFacility' => $surgeonFacility,
    'details' => $details,
    'dateRecorded' => date('Y-m-d'),
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this procedure. Please try again.']);
    exit;
}

write_audit_log('created', 'Patient Management', 'Added surgical history "' . $procedure . '" for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'surgical_history', 'added', 'Added surgical history "' . $procedure . '".');
hc_record_patient_activity($patientId, 'Surgical history "' . $procedure . '" recorded.');

echo json_encode(['success' => true, 'id' => $newId]);
