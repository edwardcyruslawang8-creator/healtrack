<?php
/**
 * =====================================================================
 * CD4 Count Form — Data
 * ---------------------------------------------------------------------
 * Backs medical_technician/cd4_count_form.php, reached from a "Fill
 * CD4 Count Form" row action on lab_xray_consultation.php whenever a
 * patient has a pending "CD4" referral. Mirrors the real Lipa City
 * Health Office "CD4 COUNT" paper form: a pre-printed Specimen
 * ("WHOLE BLOOD") and reference range ("850 - 1150 c/uL" —
 * hc_cd4_specimen_label()/hc_cd4_reference_range(), includes/firestore/lab_results.php)
 * plus one Result value the medtech fills in.
 *
 * Patient Name/Address/Age/Sex come from the patient's real Firestore
 * doc (hc_get_patient_profile()). $currentDoc is this patient's most
 * relevant CD4 lab_results doc: a still-"pending" one if any (the one
 * this form can actually fill in), else their latest "completed" one
 * (read-only — for reviewing/reprinting a past result), else null if
 * CD4 was never ordered for them at all.
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

$currentDoc = null;
if ($patient !== null) {
    $cd4Docs = hc_get_cd4_count_docs_for_patient($patientId);
    foreach ($cd4Docs as $doc) {
        if (($doc['fields']['status'] ?? '') === 'pending') {
            $currentDoc = $doc;
            break;
        }
    }
    $currentDoc ??= $cd4Docs[0] ?? null; // latest completed, already sorted newest-first
}

$isEditable = $currentDoc !== null && ($currentDoc['fields']['status'] ?? '') === 'pending';

// "Proceed to Doctor" state — see api/proceed-lab-result-to-doctor.php.
$routedToDoctorName = ($currentDoc !== null && ($currentDoc['fields']['status'] ?? '') === 'completed')
    ? ($currentDoc['fields']['routedToDoctorName'] ?? null)
    : null;

// Real uploaded reference template, if any (medical_technician/
// lab_test_catalog.php's Upload Template action) — see
// includes/firestore/lab_results.php's hc_get_lab_test_template()
// header comment. Reference only; this page's own fixed fields never
// change based on it.
$labTemplate = hc_get_lab_test_template('CD4');

/** The real saved numeric result (from resultGroups.group1_rows[0]), or '' if none/still pending. */
function hc_cd4_saved_result(?array $doc): string
{
    if ($doc === null) {
        return '';
    }
    $rows = $doc['fields']['resultGroups']['group1_rows'] ?? [];
    return $rows[0]['result'] ?? '';
}
