<?php
/**
 * =====================================================================
 * Delete Lab Test Template (api/delete-lab-test-template.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/lab_test_catalog.js's "Remove Template" row
 * action on medical_technician/lab_test_catalog.php — clears a test's
 * uploaded reference document (e.g. the wrong file was attached) WITHOUT
 * touching the catalog entry itself, unlike api/delete-lab-test.php.
 * Works for a built-in test too, same as api/upload-lab-test-template.php.
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

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = trim((string) ($body['id'] ?? ''));

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

$ext = $target['fields']['templateFileExt'] ?? null;
if ($ext !== null) {
    @unlink(hc_lab_test_template_storage_dir() . '/' . $id . '.' . $ext);
}

$saved = $firestore->setDocument('lab_test_catalog', $id, array_merge($target['fields'], [
    'templateFileName' => null,
    'templateFileExt' => null,
    'templateFileSize' => null,
    'templateUploadedAt' => null,
    'updatedAt' => date('c'),
]));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not remove the template. Please try again.']);
    exit;
}

$testName = $target['fields']['testName'] ?? 'Laboratory test';
write_audit_log('updated', 'Laboratory Catalog', 'Removed the reference template for "' . $testName . '".');

echo json_encode(['success' => true]);
