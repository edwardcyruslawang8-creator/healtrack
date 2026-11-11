<?php
/**
 * =====================================================================
 * CBC Form — Data
 * ---------------------------------------------------------------------
 * Backs medical_technician/cbc_form.php, reached from a "Fill CBC Form"
 * row action on lab_xray_consultation.php whenever a patient has a
 * pending "CBC" referral. Mirrors the real Lipa City Health Office
 * "HEMATOLOGY" CBC paper form — 8 hematology values + 5 differential
 * count values, each with its own fixed real reference range/unit
 * (hc_cbc_hematology_fields()/hc_cbc_differential_fields(),
 * includes/firestore/lab_results.php), plus Blood Type/Rh.
 *
 * Patient Name/Address/Age/Sex come from the patient's real Firestore
 * doc (hc_get_patient_profile()). $currentDoc is this patient's most
 * relevant CBC lab_results doc: a still-"pending" one if any (the one
 * this form can actually fill in), else their latest "completed" one
 * (read-only — for reviewing/reprinting a past result), else null if
 * CBC was never ordered for them at all.
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
    $cbcDocs = hc_get_cbc_docs_for_patient($patientId);
    foreach ($cbcDocs as $doc) {
        if (($doc['fields']['status'] ?? '') === 'pending') {
            $currentDoc = $doc;
            break;
        }
    }
    $currentDoc ??= $cbcDocs[0] ?? null; // latest completed, already sorted newest-first
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
$labTemplate = hc_get_lab_test_template('CBC');

/**
 * Existing saved value for $fieldKey, or '' if none/still pending.
 * Searches whichever group (1/2/3) has a row with this label.
 * $fieldLabels is either hc_cbc_hematology_fields()/
 * hc_cbc_differential_fields() (each entry an array with its own
 * 'label') or a plain ['key' => 'Label'] map (Blood Type/Rh, which
 * have no reference range/unit of their own).
 */
function hc_cbc_saved_value(?array $doc, string $fieldKey, array $fieldLabels): string
{
    if ($doc === null) {
        return '';
    }
    $entry = $fieldLabels[$fieldKey] ?? '';
    $label = is_array($entry) ? ($entry['label'] ?? '') : $entry;
    $groups = $doc['fields']['resultGroups'] ?? [];
    foreach (['group1_rows', 'group2_rows', 'group3_rows'] as $key) {
        foreach ($groups[$key] ?? [] as $row) {
            if (($row['test'] ?? '') === $label) {
                return $row['result'] ?? '';
            }
        }
    }
    return '';
}
