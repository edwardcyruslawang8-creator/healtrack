<?php
/**
 * =====================================================================
 * Delete Lab Test (api/delete-lab-test.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/lab_test_catalog.js when a medical technologist
 * confirms removing a row on medical_technician/lab_test_catalog.php.
 * Deletes the real "lab_test_catalog" doc outright — there's no
 * archive/restore for this list, matching the feature as asked for
 * (add and delete, not archive). Existing historical lab_results docs
 * that used this test name are untouched (they keep their own stored
 * testName string, same as any other historical record); the test
 * simply stops being orderable going forward, since
 * hc_lab_test_catalog() reads this collection live.
 *
 * Rejects a built-in test (isBuiltIn: true — Urinalysis/CD4/Gram
 * Stain/the 3 Serology Rapid tests) — those have their own dedicated,
 * hardcoded result-form pages wired to their exact test name; removing
 * the catalog entry would silently orphan that routing
 * (includes/medical_technician/lab_xray_consultation_firestore.php's
 * isSerologyRapid/isUrinalysis/isCD4Count/isGramStain flags are literal
 * string checks, not driven by this collection).
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

if (!empty($target['fields']['isBuiltIn'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'This laboratory test has a built-in result form and can\'t be removed.']);
    exit;
}

$testName = $target['fields']['testName'] ?? 'Laboratory test';

if (!$firestore->deleteDocument('lab_test_catalog', $id)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not delete the laboratory test. Please try again.']);
    exit;
}

// Clean up its uploaded reference template file, if any, so it doesn't
// linger as an orphaned file nothing references any more.
$ext = $target['fields']['templateFileExt'] ?? null;
if ($ext !== null) {
    @unlink(hc_lab_test_template_storage_dir() . '/' . $id . '.' . $ext);
}

write_audit_log('deleted', 'Laboratory Catalog', 'Removed laboratory test "' . $testName . '".');

echo json_encode(['success' => true]);
