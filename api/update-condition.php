<?php
/**
 * =====================================================================
 * Update Chronic Condition (api/update-condition.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medical-history.js when editing a row in the
 * Medical History tab's Chronic Conditions section. Re-resolves the
 * owning patient from the EXISTING doc (never trusts a client-supplied
 * patientId for the access check) and re-validates this signed-in
 * user's real access before writing — same pattern api/delete-document.php
 * already uses.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php'; // hc_user_can_access_patient_document()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['doctor', 'healthcare_staff', 'subadmin'], true)) {
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
$label = trim((string) ($body['label'] ?? ''));
$diagnosedOn = trim((string) ($body['diagnosedOn'] ?? ''));
$details = trim((string) ($body['details'] ?? '')) ?: null;
$status = trim((string) ($body['status'] ?? ''));
$status = in_array($status, ['Active', 'Controlled', 'Resolved'], true) ? $status : 'Active';

if ($id === '' || $label === '' || $diagnosedOn === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter the condition and date diagnosed.']);
    exit;
}
if (strtotime($diagnosedOn) === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid date diagnosed.']);
    exit;
}

$existing = $firestore->getDocument('medical_conditions', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This condition could not be found.']);
    exit;
}

$patientId = (string) ($existing['patientId'] ?? '');
$patient = $patientId !== '' ? $firestore->getDocument('patients', $patientId) : null;
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This condition could not be found.']);
    exit;
}
$patient['id'] = $patientId;
if (!hc_user_can_access_patient_document($patient)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This condition could not be found.']);
    exit;
}

$updated = $firestore->setDocument('medical_conditions', $id, [
    'patientId' => $patientId,
    'label' => $label,
    'details' => $details,
    'status' => $status,
    'diagnosedOn' => date('Y-m-d', strtotime($diagnosedOn)),
]);

if (!$updated) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this condition. Please try again.']);
    exit;
}

write_audit_log('updated', 'Patient Management', 'Updated chronic condition "' . $label . '" for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'medical_condition', 'updated', 'Updated chronic condition "' . $label . '" (status: ' . $status . ').');
hc_record_patient_activity($patientId, 'Chronic condition "' . $label . '" updated.');

echo json_encode(['success' => true]);
