<?php
/**
 * =====================================================================
 * Delete Patient Document (api/delete-document.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/document-upload.js (each row's Delete button on
 * doctor/documents.php, medical-staff/documents.php,
 * subadmin/documents.php). Removes BOTH halves of a real uploaded
 * document — the metadata doc in the "patient_documents" Firestore
 * collection AND the actual file on local disk under
 * storage/patient_documents/ (see includes/shared/document_storage.php's
 * header comment for why files live there instead of Firebase Cloud
 * Storage) — deleting only the Firestore row would leave an orphaned
 * file behind forever with nothing left pointing at it.
 *
 * Same access gate as api/upload-document.php/api/download-document.php:
 * re-resolves the owning patient and re-checks THIS signed-in user's
 * real access to them, never trusting that the document id alone
 * proves that.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php';
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

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing document id.']);
    exit;
}

$doc = $firestore->getDocument('patient_documents', $id);
if ($doc === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This document could not be found.']);
    exit;
}

$patientId = (string) ($doc['patientId'] ?? '');
$patient = $patientId !== '' ? $firestore->getDocument('patients', $patientId) : null;
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This document could not be found.']);
    exit;
}
$patient['id'] = $patientId;

if (!hc_user_can_access_patient_document($patient)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This document could not be found.']);
    exit;
}

$deleted = $firestore->deleteDocument('patient_documents', $id);
if (!$deleted) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not delete this document. Please try again.']);
    exit;
}

// Best-effort — the Firestore row is already gone either way, so a
// missing/already-removed file on disk doesn't fail the request.
$storedFileName = (string) ($doc['storedFileName'] ?? '');
if ($storedFileName !== '') {
    @unlink(hc_document_storage_dir() . '/' . $storedFileName);
}

write_audit_log('deleted', 'Patient Documents', 'Deleted "' . ($doc['name'] ?? 'a document') . '" for "' . ($patient['name'] ?? 'a patient') . '".');
hc_record_patient_history($patientId, 'document', 'deleted', 'Deleted "' . ($doc['name'] ?? 'a document') . '".');
hc_record_patient_activity($patientId, 'Document deleted: "' . ($doc['name'] ?? 'a document') . '".');

echo json_encode(['success' => true]);
