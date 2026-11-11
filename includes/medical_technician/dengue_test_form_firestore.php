<?php
/**
 * =====================================================================
 * Dengue Test Form — Data
 * ---------------------------------------------------------------------
 * Backs medical_technician/dengue_test_form.php, reached from a "Fill
 * Dengue Test Form" row action on lab_xray_consultation.php whenever a
 * patient has a pending "Dengue Test" referral. Mirrors the real Lipa
 * City Health Office "SEROLOGY — DENGUE TEST" paper form — ONE order,
 * ONE result sheet, 3 lines (NS1 Ag, IgM, IgG), unlike the 3
 * independent pending docs the Serology Rapid Tests (HBsAg/Syphilis/
 * HIV) use — see hc_get_dengue_test_docs_for_patient()'s own header
 * comment (includes/firestore/lab_results.php).
 *
 * Patient Name/Address/Age/Sex come from the patient's real Firestore
 * doc (hc_get_patient_profile()). $currentDoc is this patient's most
 * relevant Dengue Test lab_results doc: a still-"pending" one if any
 * (the one this form can actually fill in), else their latest
 * "completed" one (read-only — for reviewing/reprinting a past
 * result), else null if a Dengue Test was never ordered for them at
 * all.
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
    $dengueDocs = hc_get_dengue_test_docs_for_patient($patientId);
    foreach ($dengueDocs as $doc) {
        if (($doc['fields']['status'] ?? '') === 'pending') {
            $currentDoc = $doc;
            break;
        }
    }
    $currentDoc ??= $dengueDocs[0] ?? null; // latest completed, already sorted newest-first
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
$labTemplate = hc_get_lab_test_template('Dengue Test');

/** The 3 real result rows this form/print page share — label => Firestore row key. */
if (!function_exists('hc_dengue_test_rows')) {
    function hc_dengue_test_rows(): array
    {
        return [
            'ns1' => 'Dengue NS1 Ag',
            'igm' => 'Dengue IgM',
            'igg' => 'Dengue IgG',
        ];
    }
}

/** Existing saved value for $rowKey (from resultGroups), or '' if none/still pending. */
function hc_dengue_test_saved_value(?array $doc, string $rowKey): string
{
    if ($doc === null) {
        return '';
    }
    $rows = $doc['fields']['resultGroups']['group1_rows'] ?? [];
    $label = hc_dengue_test_rows()[$rowKey] ?? '';
    foreach ($rows as $row) {
        if (($row['test'] ?? '') === $label) {
            return $row['result'] ?? '';
        }
    }
    return '';
}
