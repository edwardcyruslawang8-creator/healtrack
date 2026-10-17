<?php
/**
 * =====================================================================
 * Upload Patient Document (api/upload-document.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/document-upload.js (doctor/documents.php,
 * medical-staff/documents.php, subadmin/documents.php's Upload
 * Document panel) — the first real file-upload endpoint in this app.
 * Saves the actual file to local disk (see
 * includes/shared/document_storage.php's header comment for why,
 * instead of Firebase Cloud Storage) and writes a real doc to the same
 * "patient_documents" Firestore collection every Documents page already
 * reads, so an upload shows up in that patient's list immediately.
 *
 * multipart/form-data, not JSON — carries a real file, every other
 * field (patientId/category/description) rides along as plain POST
 * fields rather than a JSON body.
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

$patientId = trim((string) ($_POST['patientId'] ?? ''));
$category = trim((string) ($_POST['category'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));

$validCategories = ['laboratory_result', 'imaging', 'prescription', 'medical_certificate', 'vaccination_record'];
if ($patientId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient id.']);
    exit;
}
if (!in_array($category, $validCategories, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please choose a document category.']);
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

$file = $_FILES['file'] ?? null;
if ($file === null || !isset($file['error'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please choose a file to upload.']);
    exit;
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    $tooLarge = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $tooLarge ? 'File is too large. Maximum size is 10MB.' : 'The file could not be uploaded. Please try again.']);
    exit;
}
if (!is_uploaded_file($file['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'The file could not be uploaded. Please try again.']);
    exit;
}
if ($file['size'] > hc_document_max_size_bytes()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'File is too large. Maximum size is 10MB.']);
    exit;
}

$originalName = trim((string) $file['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$ext = $ext === 'jpeg' ? 'jpg' : $ext;
if (!in_array($ext, hc_document_allowed_extensions(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unsupported file type. Please upload a PDF, JPG, or PNG file.']);
    exit;
}

// Real server-side check that the file's actual bytes match the
// claimed type — a renamed .php-with-a-.pdf-extension file still gets
// rejected here, not just by its extension.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$expectedMimes = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'png' => ['image/png']];
if (!in_array($realMime, $expectedMimes[$ext] ?? [], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This file does not look like a valid ' . strtoupper($ext) . ' file.']);
    exit;
}

$storedFileName = bin2hex(random_bytes(16)) . '.' . $ext;
$destination = hc_document_storage_dir() . '/' . $storedFileName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the file. Please try again.']);
    exit;
}

$now = new DateTime();
$newId = $firestore->addDocument('patient_documents', [
    'patientId' => $patientId,
    'name' => $originalName,
    'fileType' => $ext,
    'category' => $category,
    'description' => $description,
    'uploadedBy' => $_SESSION['name'] ?? 'Unknown',
    'uploadDate' => $now->format('Y-m-d'),
    'uploadTime' => $now->format('g:i A'),
    'storedFileName' => $storedFileName,
    'sizeBytes' => (int) $file['size'],
]);

if ($newId === null) {
    @unlink($destination); // don't leave an orphaned file if the Firestore write failed
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this document. Please try again.']);
    exit;
}

write_audit_log('created', 'Patient Documents', 'Uploaded document "' . $originalName . '" for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'document', 'uploaded', 'Uploaded "' . $originalName . '".');
hc_record_patient_activity($patientId, 'Document uploaded: "' . $originalName . '".');

echo json_encode(['success' => true, 'id' => $newId]);
