<?php
/**
 * =====================================================================
 * Complete Serology Rapid Test (api/complete-serology-rapid-test.php)
 * ---------------------------------------------------------------------
 * Called by medical_technician/serology_rapid_test_form.php's Save
 * Results button. For each submitted {testName: result} pair, writes
 * the real result onto that patient's actual pending lab_results doc
 * for that test (looked up server-side via
 * hc_get_serology_rapid_docs_for_patient() — never a client-supplied
 * docId) and flips it to "completed". Only HBsAg/Syphilis/HIV
 * (hc_serology_rapid_test_catalog()) and only "Reactive"/"Non-Reactive"
 * are accepted; anything else is silently ignored rather than trusted.
 * A doc that's already "completed" is skipped too — this endpoint only
 * ever fills in a result once, matching the form only offering an
 * editable dropdown for still-pending rows.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/patients.php';
require_once __DIR__ . '/../includes/firestore/lab_results.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

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
$patientId = trim((string) ($body['patientId'] ?? ''));
$rawResults = is_array($body['results'] ?? null) ? $body['results'] : [];

if ($patientId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient id.']);
    exit;
}

$patient = hc_get_patient_profile($patientId);
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

$catalog = hc_serology_rapid_test_catalog();
$validValues = ['Reactive', 'Non-Reactive'];
$docsByTest = hc_get_serology_rapid_docs_for_patient($patientId);

$updatedTests = [];
foreach ($rawResults as $testName => $value) {
    $testName = (string) $testName;
    $value = trim((string) $value);

    if (!isset($catalog[$testName]) || !in_array($value, $validValues, true)) {
        continue;
    }

    $doc = $docsByTest[$testName] ?? null;
    if ($doc === null || ($doc['fields']['status'] ?? '') !== 'pending') {
        continue;
    }

    $displayLabel = $catalog[$testName];
    $existing = $doc['fields'];
    $saved = $firestore->setDocument('lab_results', $doc['id'], array_merge($existing, [
        'status' => 'completed',
        'completedDate' => date('Y-m-d'),
        'completedTime' => date('g:i A'),
        'interpretation' => $displayLabel . ': ' . $value,
        'resultBadgeLabel' => $value,
        'resultBadgeColor' => $value === 'Reactive' ? '#D93025' : '#1E8E3E',
        'resultGroups' => [
            'group1_name' => 'Serology',
            'group1_rows' => [[
                'test' => $displayLabel,
                'result' => $value,
                'unit' => '',
                'range' => 'Non-Reactive',
                'interpretation' => $value,
            ]],
            'group2_name' => null,
            'group2_rows' => [],
        ],
    ]));

    if ($saved) {
        $updatedTests[] = $displayLabel;

        // A real medical_records/{new-id} doc (type "laboratory") per
        // completed test, so each one shows up on the patient's
        // Medical Records tab (doctor/medical-staff/subadmin
        // patient_profile.php's "Laboratory Results" category) —
        // previously nothing wrote one for a completed lab test, only
        // for a completed consultation.
        $firestore->addDocument('medical_records', [
            'patientId' => $patientId,
            'type' => 'laboratory',
            'title' => 'Laboratory — ' . $displayLabel,
            'date' => date('Y-m-d'),
            'time' => date('g:i A'),
            'provider' => $_SESSION['name'] ?? null,
            'facility' => $existing['facility'] ?? 'Not specified',
        ]);
    }
}

if (count($updatedTests) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No pending test results were updated.']);
    exit;
}

write_audit_log(
    'updated',
    'Laboratory & X-ray',
    'Entered Serology rapid test result(s) (' . implode(', ', $updatedTests) . ') for "' . $patient['name'] . '".'
);
hc_record_patient_history($patientId, 'laboratory_result', 'added', 'Entered Serology rapid test result(s) (' . implode(', ', $updatedTests) . ').');
hc_record_patient_activity($patientId, 'Serology rapid test result(s) entered.');

echo json_encode(['success' => true, 'updated' => $updatedTests]);
