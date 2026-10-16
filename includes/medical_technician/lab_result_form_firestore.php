<?php
/**
 * =====================================================================
 * Lab Result Form — Data
 * ---------------------------------------------------------------------
 * Backs medical_technician/lab_result_form.php — the generic "fill in
 * a result" form for any lab test NOT covered by one of the 4 named
 * paper-form replicas (serology_rapid_test_form.php, urinalysis_form.php,
 * cd4_count_form.php, gram_stain_form.php). hc_lab_test_catalog() lists
 * ~22 orderable tests; only those 4 have a dedicated page, so every
 * other one (CBC, Fasting Blood Sugar, Viral Load, etc.)
 * reaches this page instead — see
 * includes/medical_technician/_lab_xray_referral_table.php's Consult
 * button routing.
 *
 * Unlike the 4 named forms (each finds "this patient's one pending
 * {test name}" since a patient can only have one pending order per
 * named test at a time), this page is reached with an explicit
 * &recordId= — a patient could have several different pending "other"
 * tests at once (e.g. both CBC and Fasting Blood Sugar), so the exact
 * lab_results doc must be targeted directly, not found by name search.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../firestore/xray_results.php'; // hc_get_pending_lab_xray_count() (sidebar badge) needs both collections

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

$patientId = trim($_GET['patientId'] ?? '');
$recordId = trim($_GET['recordId'] ?? '');
$patient = hc_get_patient_profile($patientId);

$currentDoc = null;
if ($patient !== null && $recordId !== '') {
    foreach (hc_get_lab_result_docs_for_patient($patientId) as $doc) {
        if ($doc['id'] === $recordId) {
            $currentDoc = $doc;
            break;
        }
    }
}

$isEditable = $currentDoc !== null && ($currentDoc['fields']['status'] ?? '') === 'pending';

// "Proceed to Doctor" state — see api/proceed-lab-result-to-doctor.php's
// 'lab_generic' testType branch.
$routedToDoctorName = ($currentDoc !== null && ($currentDoc['fields']['status'] ?? '') === 'completed')
    ? ($currentDoc['fields']['routedToDoctorName'] ?? null)
    : null;
