<?php
/**
 * =====================================================================
 * Add Lab Test (api/add-lab-test.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/lab_test_catalog.js when a medical technologist
 * submits the "Add Laboratory" modal on
 * medical_technician/lab_test_catalog.php. Writes a new document to the
 * real "lab_test_catalog" Firestore collection that
 * includes/firestore/lab_results.php's hc_lab_test_catalog() now reads
 * from — so a newly added test becomes orderable on
 * doctor/active_consultation.php's "Select Laboratory Tests" panel
 * immediately, no code change needed.
 *
 * New tests always start with formFields: [] (the generic fallback
 * form, medical_technician/lab_result_form.php) — a custom result-field
 * template is defined afterward via api/update-lab-test-form.php, not
 * here.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/lab_results.php';
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
$testName = trim((string) ($body['testName'] ?? ''));
$category = trim((string) ($body['category'] ?? ''));

if ($testName === '' || $category === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Test name and category are required.']);
    exit;
}

foreach (hc_get_lab_test_catalog_docs() as $doc) {
    if (strcasecmp($doc['fields']['testName'] ?? '', $testName) === 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'A laboratory test with this name already exists.']);
        exit;
    }
}

$now = date('c');
$newId = $firestore->addDocument('lab_test_catalog', [
    'testName' => $testName,
    'category' => $category,
    'isBuiltIn' => false,
    'formFields' => [],
    'createdAt' => $now,
    'updatedAt' => $now,
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not add the laboratory test. Please try again.']);
    exit;
}

write_audit_log('created', 'Laboratory Catalog', 'Added new laboratory test "' . $testName . '" (' . $category . ').');

echo json_encode(['success' => true, 'id' => $newId]);
