<?php
/**
 * =====================================================================
 * Complete Dengue Test (api/complete-dengue-test.php)
 * ---------------------------------------------------------------------
 * Called by medical_technician/dengue_test_form.php's Save Results
 * button. Writes all 3 submitted results (NS1 Ag, IgM, IgG) onto this
 * patient's actual pending "Dengue Test" lab_results doc (looked up
 * server-side via hc_get_dengue_test_docs_for_patient() — never a
 * client-supplied docId) and flips it to "completed". ONE order, ONE
 * result sheet, same as the real Lipa City Health Office paper form —
 * unlike the 3 independent Serology Rapid Tests (HBsAg/Syphilis/HIV),
 * all 3 rows must be submitted together; a doc that's already
 * "completed" is left alone — this endpoint only ever fills in a
 * result once, matching the form only offering editable inputs for a
 * still-pending doc.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/lab_test_names.php'; // hc_lab_test_display_name(), hc_lab_test_required_specimen()
require_once __DIR__ . '/../includes/firestore/patients.php';
require_once __DIR__ . '/../includes/firestore/lab_results.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()
require_once __DIR__ . '/../includes/shared/lab_result_attachment.php'; // hc_attach_lab_result_report()

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

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$patientId = trim((string) ($body['patientId'] ?? ''));
$rawValues = is_array($body['values'] ?? null) ? $body['values'] : [];

if ($patientId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient id.']);
    exit;
}

$rowLabels = ['ns1' => 'Dengue NS1 Ag', 'igm' => 'Dengue IgM', 'igg' => 'Dengue IgG'];
$validValues = ['Reactive', 'Non-Reactive'];
foreach ($rowLabels as $key => $label) {
    if (!in_array(trim((string) ($rawValues[$key] ?? '')), $validValues, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'A valid result is required for all 3 tests.']);
        exit;
    }
}

$patient = hc_get_patient_profile($patientId);
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

// Never trust the client-supplied patientId alone — it must belong to
// this medical technologist's own facility, resolved server-side from
// their own users/{uid} doc, same as every other facility-scoped write
// in this app (see api/update-stock-request-status.php's own copy of
// this check). A 404, not 403, so this never confirms another
// facility's patient even exists.
$myFacility = $myDoc['facility'] ?? null;
if ($myFacility === null || $patient['facility'] !== $myFacility) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

$pendingDoc = null;
foreach (hc_get_dengue_test_docs_for_patient($patientId) as $doc) {
    if (($doc['fields']['status'] ?? '') === 'pending') {
        $pendingDoc = $doc;
        break;
    }
}
if ($pendingDoc === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No pending Dengue Test order found for this patient.']);
    exit;
}

$rows = [];
$summaryParts = [];
$anyReactive = false;
foreach ($rowLabels as $key => $label) {
    $value = trim((string) $rawValues[$key]);
    $rows[] = ['test' => $label, 'result' => $value, 'unit' => '', 'range' => 'Non-Reactive', 'interpretation' => ''];
    $summaryParts[] = $label . ': ' . $value;
    if ($value === 'Reactive') {
        $anyReactive = true;
    }
}

$existing = $pendingDoc['fields'];
$mergedFields = array_merge($existing, [
    'status' => 'completed',
    'completedDate' => date('Y-m-d'),
    'completedTime' => date('g:i A'),
    'specimenType' => hc_lab_test_required_specimen('Dengue Test'),
    'interpretation' => implode('; ', $summaryParts) . '.',
    'resultBadgeLabel' => $anyReactive ? 'Reactive' : 'Non-Reactive',
    'resultBadgeColor' => $anyReactive ? '#D93025' : '#1E8E3E',
    'resultGroups' => [
        'group1_name' => 'Dengue Test',
        'group1_rows' => $rows,
        'group2_name' => null,
        'group2_rows' => [],
    ],
]);
$saved = $firestore->setDocument('lab_results', $pendingDoc['id'], $mergedFields);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the result. Please try again.']);
    exit;
}

hc_attach_lab_result_report($patientId, $pendingDoc['id'], $mergedFields, $_SESSION['name'] ?? 'Medical Technologist');

// A real medical_records/{new-id} doc (type "laboratory") so this
// result actually shows up on the patient's Medical Records tab —
// same convention as every other lab-test completion endpoint.
$firestore->addDocument('medical_records', [
    'patientId' => $patientId,
    'type' => 'laboratory',
    'title' => 'Laboratory — ' . hc_lab_test_display_name($existing['testName']),
    'date' => date('Y-m-d'),
    'time' => date('g:i A'),
    'provider' => $_SESSION['name'] ?? null,
    'facility' => $existing['facility'] ?? 'Not specified',
]);

write_audit_log(
    'updated',
    'Laboratory & X-ray',
    'Entered Dengue Test result (' . implode('; ', $summaryParts) . ') for ' . $patient['name'] . '.'
);
hc_record_patient_history($patientId, 'laboratory_result', 'added', 'Entered Dengue Test result (' . implode('; ', $summaryParts) . ').');
hc_record_patient_activity($patientId, 'Dengue Test result entered.');

echo json_encode(['success' => true]);
