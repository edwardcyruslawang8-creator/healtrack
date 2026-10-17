<?php
/**
 * =====================================================================
 * Update Family History (api/update-family-history.php)
 * ---------------------------------------------------------------------
 * Same re-resolve + re-check-access pattern as api/update-condition.php,
 * for a row in the Medical History tab's Family History section.
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
$condition = trim((string) ($body['condition'] ?? ''));
$relation = trim((string) ($body['relation'] ?? ''));

if ($id === '' || $condition === '' || $relation === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter the condition and relation.']);
    exit;
}

$existing = $firestore->getDocument('family_history', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This family history entry could not be found.']);
    exit;
}

$patientId = (string) ($existing['patientId'] ?? '');
$patient = $patientId !== '' ? $firestore->getDocument('patients', $patientId) : null;
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This family history entry could not be found.']);
    exit;
}
$patient['id'] = $patientId;
if (!hc_user_can_access_patient_document($patient)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This family history entry could not be found.']);
    exit;
}

$updated = $firestore->setDocument('family_history', $id, [
    'patientId' => $patientId,
    'condition' => $condition,
    'relation' => $relation,
]);

if (!$updated) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this family history entry. Please try again.']);
    exit;
}

write_audit_log('updated', 'Patient Management', 'Updated family history "' . $condition . '" (' . $relation . ') for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'family_history', 'updated', 'Updated family history "' . $condition . '" (' . $relation . ').');
hc_record_patient_activity($patientId, 'Family history "' . $condition . '" updated.');

echo json_encode(['success' => true]);
