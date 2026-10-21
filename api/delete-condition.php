<?php
/**
 * =====================================================================
 * Delete Chronic Condition (api/delete-condition.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/medical-history.js's per-row trash button on the
 * Medical History tab's Chronic Conditions section. Same re-resolve +
 * re-check-access pattern as api/update-condition.php.
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

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing condition id.']);
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

$deleted = $firestore->deleteDocument('medical_conditions', $id);
if (!$deleted) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not delete this condition. Please try again.']);
    exit;
}

write_audit_log('deleted', 'Patient Management', 'Deleted chronic condition "' . ($existing['label'] ?? 'a condition') . '" for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'medical_condition', 'deleted', 'Deleted chronic condition "' . ($existing['label'] ?? 'a condition') . '".');
hc_record_patient_activity($patientId, 'Chronic condition "' . ($existing['label'] ?? '') . '" removed.');

echo json_encode(['success' => true]);
