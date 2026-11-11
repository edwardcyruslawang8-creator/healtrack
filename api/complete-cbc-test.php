<?php
/**
 * =====================================================================
 * Complete CBC Test (api/complete-cbc-test.php)
 * ---------------------------------------------------------------------
 * Called by medical_technician/cbc_form.php's Save Results button.
 * Writes all 15 submitted values (8 Hematology + 5 Differential Count
 * + Blood Type/Rh) onto this patient's actual pending "CBC" lab_results
 * doc (looked up server-side via hc_get_cbc_docs_for_patient() — never
 * a client-supplied docId) and flips it to "completed". Each of the 13
 * numeric fields is auto-classified Low/Normal/High against its own
 * fixed real reference range (hc_cbc_hematology_fields()/
 * hc_cbc_differential_fields(), includes/firestore/lab_results.php —
 * same idea api/complete-cd4-count-test.php already uses); Blood Type/
 * Rh carry no such range. A doc that's already "completed" is left
 * alone — this endpoint only ever fills in a result once, matching the
 * form only offering editable inputs for a still-pending doc.
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

$hematologyFields = hc_cbc_hematology_fields();
$differentialFields = hc_cbc_differential_fields();
$validBloodTypes = ['A', 'B', 'AB', 'O'];
$validRh = ['Positive', 'Negative'];

foreach ($hematologyFields as $key => $f) {
    if (trim((string) ($rawValues[$key] ?? '')) === '' || !is_numeric($rawValues[$key])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'A valid numeric result is required for ' . $f['label'] . '.']);
        exit;
    }
}
foreach ($differentialFields as $key => $f) {
    if (trim((string) ($rawValues[$key] ?? '')) === '' || !is_numeric($rawValues[$key])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'A valid numeric result is required for ' . $f['label'] . '.']);
        exit;
    }
}
if (!in_array($rawValues['bloodType'] ?? '', $validBloodTypes, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A valid Blood Type is required.']);
    exit;
}
if (!in_array($rawValues['rh'] ?? '', $validRh, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A valid Rh result is required.']);
    exit;
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
foreach (hc_get_cbc_docs_for_patient($patientId) as $doc) {
    if (($doc['fields']['status'] ?? '') === 'pending') {
        $pendingDoc = $doc;
        break;
    }
}
if ($pendingDoc === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No pending CBC order found for this patient.']);
    exit;
}

/** @param array<string, array{label: string, unit: string, range: string, low: float, high: float}> $fields */
$buildRows = function (array $fields) use ($rawValues, &$anyAbnormal): array {
    $rows = [];
    foreach ($fields as $key => $f) {
        $value = (float) $rawValues[$key];
        $classification = $value < $f['low'] ? 'Low' : ($value > $f['high'] ? 'High' : 'Normal');
        if ($classification !== 'Normal') {
            $anyAbnormal = true;
        }
        $rows[] = ['test' => $f['label'], 'result' => $rawValues[$key], 'unit' => $f['unit'], 'range' => $f['range'], 'interpretation' => $classification];
    }
    return $rows;
};

$anyAbnormal = false;
$hematologyRows = $buildRows($hematologyFields);
$differentialRows = $buildRows($differentialFields);

$existing = $pendingDoc['fields'];
$mergedFields = array_merge($existing, [
    'status' => 'completed',
    'completedDate' => date('Y-m-d'),
    'completedTime' => date('g:i A'),
    'specimenType' => hc_lab_test_required_specimen('CBC'),
    'interpretation' => 'CBC result: ' . ($anyAbnormal ? 'Abnormal' : 'Normal') . '. Blood Type ' . $rawValues['bloodType'] . ' ' . $rawValues['rh'] . '.',
    'resultBadgeLabel' => $anyAbnormal ? 'Abnormal' : 'Normal',
    'resultBadgeColor' => $anyAbnormal ? '#D93025' : '#1E8E3E',
    'resultGroups' => [
        'group1_name' => 'Hematology',
        'group1_rows' => $hematologyRows,
        'group2_name' => 'Differential Count',
        'group2_rows' => $differentialRows,
        'group3_name' => 'Blood Typing',
        'group3_rows' => [
            ['test' => 'Blood Type', 'result' => $rawValues['bloodType'], 'unit' => '', 'range' => '', 'interpretation' => ''],
            ['test' => 'Rh', 'result' => $rawValues['rh'], 'unit' => '', 'range' => '', 'interpretation' => ''],
        ],
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
    'Entered CBC result (' . ($anyAbnormal ? 'Abnormal' : 'Normal') . ') for ' . $patient['name'] . '.'
);
hc_record_patient_history($patientId, 'laboratory_result', 'added', 'Entered CBC result (' . ($anyAbnormal ? 'Abnormal' : 'Normal') . ').');
hc_record_patient_activity($patientId, 'CBC result entered.');

echo json_encode(['success' => true]);
