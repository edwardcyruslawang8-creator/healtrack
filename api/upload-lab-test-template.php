<?php
/**
 * =====================================================================
 * Upload Lab Test Template (api/upload-lab-test-template.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/lab_test_catalog.js when a medical technologist
 * submits the "Upload Template" modal on
 * medical_technician/lab_test_catalog.php, for ANY catalog row —
 * including one of the 6 "isBuiltIn" tests (Urinalysis/CD4/Gram
 * Stain/the 3 Serology Rapid tests). Those tests' real RESULT ENTRY
 * forms stay the existing dedicated, hardcoded pages (not touched by
 * this at all) — this is deliberately just a reference attachment
 * (e.g. the real paper form, once it's actually arrived at the
 * facility), shown as a "View Template" link wherever that test's
 * result gets filled in. See includes/firestore/lab_results.php's
 * hc_get_lab_test_template() header comment.
 *
 * Saves the actual file to local disk (see
 * includes/shared/lab_test_template_storage.php's header comment,
 * same reasoning as every other upload in this app) and records just
 * the original filename/extension/size on the real
 * lab_test_catalog/{id} Firestore doc. A re-upload for the same test
 * overwrites its previous file directly.
 *
 * multipart/form-data, not JSON — carries a real file.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/lab_results.php';
require_once __DIR__ . '/../includes/shared/lab_test_template_storage.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$id = trim((string) ($_POST['id'] ?? ''));
if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing laboratory test id.']);
    exit;
}

$target = null;
foreach (hc_get_lab_test_catalog_docs() as $doc) {
    if ($doc['id'] === $id) {
        $target = $doc;
        break;
    }
}
if ($target === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Laboratory test not found.']);
    exit;
}

$file = $_FILES['template'] ?? null;
if ($file === null || !isset($file['error'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please choose a file to upload.']);
    exit;
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    $tooLarge = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $tooLarge ? 'File is too large.' : 'The file could not be uploaded. Please try again.']);
    exit;
}
if (!is_uploaded_file($file['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'The file could not be uploaded. Please try again.']);
    exit;
}
if ($file['size'] > hc_lab_test_template_max_size_bytes()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'File is too large (max 10MB).']);
    exit;
}

$originalName = trim((string) $file['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$ext = $ext === 'jpeg' ? 'jpg' : $ext;
if (!in_array($ext, hc_lab_test_template_allowed_extensions(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please upload a PDF, JPG, or PNG file.']);
    exit;
}

// Real server-side check that the file's actual bytes match the
// claimed type — same reasoning as api/upload-document.php's own copy
// of this check.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$expectedMimes = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'png' => ['image/png']];
if (!in_array($realMime, $expectedMimes[$ext] ?? [], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This file does not look like a valid PDF, JPG, or PNG.']);
    exit;
}

// One file per catalog doc, named by its own real id — a re-upload
// overwrites it directly. $id is this app's own Firestore doc id (not
// attacker-controlled free text), safe to use directly in a path.
$dir = hc_lab_test_template_storage_dir();

// If this test's previous template used a DIFFERENT extension, remove
// it so it doesn't linger as an orphaned file nothing references.
foreach (hc_lab_test_template_allowed_extensions() as $otherExt) {
    if ($otherExt !== $ext) {
        @unlink($dir . '/' . $id . '.' . $otherExt);
    }
}

$destination = $dir . '/' . $id . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the file. Please try again.']);
    exit;
}

$saved = $firestore->setDocument('lab_test_catalog', $id, array_merge($target['fields'], [
    'templateFileName' => $originalName,
    'templateFileExt' => $ext,
    'templateFileSize' => $file['size'],
    'templateUploadedAt' => date('c'),
    'updatedAt' => date('c'),
]));

if (!$saved) {
    @unlink($destination); // don't leave an orphaned file if the Firestore write failed
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the file. Please try again.']);
    exit;
}

$testName = $target['fields']['testName'] ?? 'Laboratory test';
write_audit_log('updated', 'Laboratory Catalog', 'Uploaded a reference template for "' . $testName . '" (' . $originalName . ').');

echo json_encode(['success' => true]);
