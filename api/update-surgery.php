<?php
/**
 * =====================================================================
 * Update Surgery / Procedure (api/update-surgery.php)
 * ---------------------------------------------------------------------
 * Same re-resolve + re-check-access pattern as api/update-condition.php,
 * for a row in the Medical History tab's Past Surgeries/Procedures
 * section.
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

$id = trim((string) ($body['id'] ?? ''));
$procedure = trim((string) ($body['procedure'] ?? ''));
$datePerformed = trim((string) ($body['datePerformed'] ?? ''));
$surgeonFacility = trim((string) ($body['surgeonFacility'] ?? '')) ?: null;
$details = trim((string) ($body['details'] ?? '')) ?: null;

if ($id === '' || $procedure === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter the procedure.']);
    exit;
}
if ($datePerformed !== '' && strtotime($datePerformed) === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid date.']);
    exit;
}

$existing = $firestore->getDocument('surgical_history', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This procedure could not be found.']);
    exit;
}

$patientId = (string) ($existing['patientId'] ?? '');
$patient = $patientId !== '' ? $firestore->getDocument('patients', $patientId) : null;
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This procedure could not be found.']);
    exit;
}
$patient['id'] = $patientId;
if (!hc_user_can_access_patient_document($patient)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This procedure could not be found.']);
    exit;
}

$updated = $firestore->setDocument('surgical_history', $id, [
    'patientId' => $patientId,
    'procedure' => $procedure,
    'datePerformed' => $datePerformed !== '' ? date('Y-m-d', strtotime($datePerformed)) : null,
    'surgeonFacility' => $surgeonFacility,
    'details' => $details,
    'dateRecorded' => (string) ($existing['dateRecorded'] ?? date('Y-m-d')),
]);

if (!$updated) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this procedure. Please try again.']);
    exit;
}

write_audit_log('updated', 'Patient Management', 'Updated surgical history "' . $procedure . '" for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'surgical_history', 'updated', 'Updated surgical history "' . $procedure . '".');
hc_record_patient_activity($patientId, 'Surgical history "' . $procedure . '" updated.');

echo json_encode(['success' => true]);
