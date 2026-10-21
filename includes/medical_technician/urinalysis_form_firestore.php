<?php
/**
 * =====================================================================
 * Urinalysis Form — Data
 * ---------------------------------------------------------------------
 * Backs medical_technician/urinalysis_form.php, reached from a "Fill
 * Urinalysis Form" row action on lab_xray_consultation.php whenever a
 * patient has a pending "Urinalysis" referral. Mirrors the real Lipa
 * City Health Office "URINALYSIS" paper form (PHYSICAL EXAMINATION:
 * Color/Transparency/Specific Gravity/pH/Glucose/Albumin, MICROSCOPIC
 * EXAMINATION: Red/White Blood Cells/Epithelial Cells/Amorphous
 * Urates/Amorphous Phosphates/Mucus Threads/Bacteria/Other Findings —
 * see hc_urinalysis_physical_fields()/hc_urinalysis_microscopic_fields(),
 * includes/firestore/lab_results.php).
 *
 * Patient Name/Address/Age/Sex come from the patient's real Firestore
 * doc (hc_get_patient_profile()). $currentDoc is this patient's most
 * relevant Urinalysis lab_results doc: a still-"pending" one if any
 * (the one this form can actually fill in), else their latest
 * "completed" one (read-only — for reviewing/reprinting a past
 * result), else null if Urinalysis was never ordered for them at all.
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

$currentDoc = null;
if ($patient !== null) {
    $urinalysisDocs = hc_get_urinalysis_docs_for_patient($patientId);
    foreach ($urinalysisDocs as $doc) {
        if (($doc['fields']['status'] ?? '') === 'pending') {
            $currentDoc = $doc;
            break;
        }
    }
    $currentDoc ??= $urinalysisDocs[0] ?? null; // latest completed, already sorted newest-first
}

$isEditable = $currentDoc !== null && ($currentDoc['fields']['status'] ?? '') === 'pending';

// "Proceed to Doctor" state — see api/proceed-lab-result-to-doctor.php.
$routedToDoctorName = ($currentDoc !== null && ($currentDoc['fields']['status'] ?? '') === 'completed')
    ? ($currentDoc['fields']['routedToDoctorName'] ?? null)
    : null;

/** Existing saved value for $fieldKey (from resultGroups), or '' if none/still pending. */
function hc_urinalysis_saved_value(?array $doc, string $groupNum, string $fieldKey, array $fieldLabels): string
{
    if ($doc === null) {
        return '';
    }
    $rows = $doc['fields']['resultGroups']['group' . $groupNum . '_rows'] ?? [];
    $label = $fieldLabels[$fieldKey] ?? '';
    foreach ($rows as $row) {
        if (($row['test'] ?? '') === $label) {
            return $row['result'] ?? '';
        }
    }
    return '';
}
