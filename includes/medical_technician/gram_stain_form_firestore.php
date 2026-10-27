<?php
/**
 * =====================================================================
 * Gram Stain Form — Data
 * ---------------------------------------------------------------------
 * Backs medical_technician/gram_stain_form.php, reached from a "Fill
 * Gram Stain Form" row action on lab_xray_consultation.php whenever a
 * patient has a pending "Gram Stain" referral. Mirrors the real Lipa
 * City Health Office "GRAM STAIN" paper form — unlike CD4 Count, both
 * Specimen and Result are free text the medtech fills in per patient
 * (no fixed specimen/reference range; a Gram stain read is descriptive,
 * not a single number).
 *
 * Patient Name/Address/Age/Sex come from the patient's real Firestore
 * doc (hc_get_patient_profile()). $currentDoc is this patient's most
 * relevant Gram Stain lab_results doc: a still-"pending" one if any
 * (the one this form can actually fill in), else their latest
 * "completed" one (read-only — for reviewing/reprinting a past
 * result), else null if Gram Stain was never ordered for them at all.
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
    $gramStainDocs = hc_get_gram_stain_docs_for_patient($patientId);
    foreach ($gramStainDocs as $doc) {
        if (($doc['fields']['status'] ?? '') === 'pending') {
            $currentDoc = $doc;
            break;
        }
    }
    $currentDoc ??= $gramStainDocs[0] ?? null; // latest completed, already sorted newest-first
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
$labTemplate = hc_get_lab_test_template('Gram Stain');

/**
 * The real saved Specimen or Result text, or '' if none/still pending.
 * Specimen reuses the doc's existing top-level "specimenType" field
 * (the same field every other lab_results doc already has); Result is
 * the descriptive finding, stored in resultGroups.group1_rows[0].result
 * so it also renders on the existing shared result-detail view.
 */
function hc_gram_stain_saved_value(?array $doc, string $field): string
{
    if ($doc === null) {
        return '';
    }
    if ($field === 'specimen') {
        $specimen = $doc['fields']['specimenType'] ?? '';
        return $specimen === 'Not specified' ? '' : $specimen;
    }
    $rows = $doc['fields']['resultGroups']['group1_rows'] ?? [];
    return $rows[0]['result'] ?? '';
}
