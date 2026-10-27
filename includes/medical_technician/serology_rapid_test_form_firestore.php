<?php
/**
 * =====================================================================
 * Serology (Rapid Immunochromatographic Tests) Form — Data
 * ---------------------------------------------------------------------
 * Backs medical_technician/serology_rapid_test_form.php, reached from
 * a "Fill Serology Form" row action on lab_xray_consultation.php
 * whenever a patient has a pending HBsAg/Syphilis/HIV referral (see
 * hc_serology_rapid_test_catalog(), includes/firestore/lab_results.php).
 *
 * Mirrors the real Lipa City Health Office paper form: patient Name/
 * Address/Age/Sex come from the patient's real Firestore doc
 * (hc_get_patient_profile()); the 3 test rows come from
 * hc_get_serology_rapid_docs_for_patient() — a row is editable only
 * while its lab_results doc is still "pending" (the medtech hasn't
 * saved a result yet); "completed" rows show their real saved result
 * read-only; a test never ordered for this patient at all has no doc,
 * so it renders as "Not requested" and can't be filled in here.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../firestore/xray_results.php'; // hc_get_all_xray_result_docs() — hc_get_pending_lab_count() (sidebar badge) needs both collections

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

$patientId = trim($_GET['patientId'] ?? '');
$patient = hc_get_patient_profile($patientId);
// Facility isolation — same rule as includes/doctor/patient_profile_doctor_firestore.php's
// own copy of this check: a real patient from another facility must
// render exactly like no order exists at all, never let a medical
// technologist outside that facility view or fill in their result.
if ($patient !== null && $patient['facility'] !== $staffProfile['facility']) {
    $patient = null;
}

// Real uploaded reference template per test, if any (medical_technician/
// lab_test_catalog.php's Upload Template action), keyed the same as
// hc_serology_rapid_test_catalog() — see includes/firestore/lab_results.php's
// hc_get_lab_test_template() header comment. Reference only; this
// page's own fixed fields never change based on it.
$serologyRows = [];
if ($patient !== null) {
    $docsByTest = hc_get_serology_rapid_docs_for_patient($patientId);
    foreach (hc_serology_rapid_test_catalog() as $testName => $displayLabel) {
        $doc = $docsByTest[$testName] ?? null;
        $labTemplate = hc_get_lab_test_template($testName);
        if ($doc === null) {
            $serologyRows[] = [
                'testName' => $testName,
                'displayLabel' => $displayLabel,
                'state' => 'not_requested',
                'docId' => null,
                'result' => null,
                'labTemplate' => $labTemplate,
            ];
            continue;
        }
        $f = $doc['fields'];
        $serologyRows[] = [
            'testName' => $testName,
            'displayLabel' => $displayLabel,
            'state' => ($f['status'] ?? '') === 'completed' ? 'completed' : 'pending',
            'docId' => $doc['id'],
            'result' => ($f['status'] ?? '') === 'completed' ? ($f['resultBadgeLabel'] ?? '') : null,
            'labTemplate' => $labTemplate,
        ];
    }
}

$hasEditableRow = (bool) array_filter($serologyRows, fn ($r) => $r['state'] === 'pending');
$hasCompletedRow = (bool) array_filter($serologyRows, fn ($r) => $r['state'] === 'completed');

// "Proceed to Doctor" state — api/proceed-lab-result-to-doctor.php
// stamps every completed doc for this patient+test together, so
// checking the first completed row's own doc is enough to know
// whether this patient's Serology results have already been routed.
$routedToDoctorName = null;
if ($patient !== null) {
    foreach (hc_get_serology_rapid_docs_for_patient($patientId) as $doc) {
        if (($doc['fields']['status'] ?? '') === 'completed' && !empty($doc['fields']['routedToDoctorName'])) {
            $routedToDoctorName = $doc['fields']['routedToDoctorName'];
            break;
        }
    }
}
