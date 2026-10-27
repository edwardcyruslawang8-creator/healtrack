<?php
/**
 * =====================================================================
 * Laboratory Result Details (Doctor) — Data
 * ---------------------------------------------------------------------
 * Reached two ways: doctor/active_consultation.php's "Lab Results" card
 * (the "View Full Result" link on each linked test — always a lab
 * result, never sends &type=) and, now, doctor/patient_profile.php's
 * "Laboratory & X-ray" tab (doctor/patient_laboratory_xray.php's View
 * buttons, which DO send a real &type=lab|xray&recordId=) — same
 * drill-down detail view as medical_technician's/medical-staff's/
 * subadmin's own laboratory_result_details.php, resolved by the exact
 * same includes/firestore/lab_results.php's hc_resolve_lab_result() and
 * includes/firestore/xray_results.php's hc_resolve_xray_result() so
 * there's only ever one place that logic lives.
 *
 * $patient is resolved directly from ?id= (the patientId), same
 * reasoning as the medical technologist's copy: this isn't reached from
 * a per-patient profile tab strip.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../firestore/xray_results.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$doctorProfile = [
    'name'     => $_SESSION['name'] ?? 'Doctor',
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$requestedPatientId = $_GET['id'] ?? '';
$patient = hc_get_patient_profile($requestedPatientId);
// Facility isolation — same rule as includes/doctor/patient_profile_doctor_firestore.php's
// own copy of this check (this page is reached directly by &id=, not
// through that profile tab strip, so it needs its own copy): a real
// patient from another facility must render exactly like an invalid
// id, never leak that their result exists at all to a doctor outside
// the facility that registered them.
if ($patient !== null && $patient['facility'] !== $doctorProfile['facility']) {
    $patient = null;
    $requestedPatientId = '';
}
$patient = $patient ?? hc_not_found_patient_profile($requestedPatientId);

$statusMeta = [
    'completed' => ['label' => 'Completed', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'pending'   => ['label' => 'Pending',   'bg' => '#FDECDD', 'color' => '#E8710A'],
];

$requestedRecordId = $_GET['recordId'] ?? '';
// The consultation this result was linked from, if any — carried
// through purely so "Back" can return to the exact workspace the
// doctor came from instead of a generic page. Blank (not carried)
// means this was opened from patient_laboratory_xray.php's own View
// buttons instead (the Patient Profile's "Laboratory & X-ray" tab),
// which never sends &consultationId= — doctor/laboratory_result_details.php
// uses this to decide which of those two pages "Back" returns to.
$backConsultationId = $_GET['consultationId'] ?? '';

// &type=lab|xray, sent by the new doctor/patient_laboratory_xray.php
// tab's View buttons (see that page's own header comment) — missing/
// empty (every pre-existing caller, e.g. active_consultation.php's "Lab
// Results" card) still means "lab", exactly as this page behaved before
// x-ray support existed here, so none of those links change behavior.
$requestedType = $_GET['type'] ?? '';

// Which ONE of the two halves below actually gets shown — see
// doctor/laboratory_result_details.php's own use of this. Resolving
// BOTH hc_resolve_lab_result()/hc_resolve_xray_result() below
// regardless of $resultType is intentional (the one that wasn't
// requested still needs an empty-state-safe value if its section were
// ever shown), but previously BOTH sections rendered unconditionally —
// a doctor opening an X-ray's "View Full Result" also saw an unrelated
// lab test (whichever lab result happened to be this patient's most
// recent, since hc_resolve_lab_result()'s own $wantThisType=false
// fallback is "most recent", not "nothing") paired alongside it. Now
// only the half that was actually requested renders.
$resultType = $requestedType === 'xray' ? 'xray' : 'lab';

$labBundle = hc_resolve_lab_result($patient['id'], $requestedType !== 'xray', $requestedRecordId);
$labTest = $labBundle['labTest'];
$labResultBadge = $labBundle['labResultBadge'];
$labResultGroups = $labBundle['labResultGroups'];
$labInterpretation = $labBundle['labInterpretation'];

$xrayBundle = hc_resolve_xray_result($patient['id'], $requestedType === 'xray', $requestedRecordId);
$xrayExam = $xrayBundle['xrayExam'];
$xrayFindings = $xrayBundle['xrayFindings'];
$xrayImpression = $xrayBundle['xrayImpression'];

// The real Lipa City Health Office paper-form URL for this test, if
// one of the 4 exists (medical_technician/print_*.php — ?embed=1 has
// both allow the doctor role and skip the auto window.print()/hide
// the Print button, since this renders inside an <iframe> here, not
// its own tab). Only these 4 tests have a real paper template; every
// other test falls back to the plain field list below instead.
$paperFormUrl = null;
if ($labTest['testName'] === 'Urinalysis') {
    $paperFormUrl = '../medical_technician/print_urinalysis.php';
} elseif ($labTest['testName'] === 'CD4') {
    $paperFormUrl = '../medical_technician/print_cd4_count.php';
} elseif ($labTest['testName'] === 'Gram Stain') {
    $paperFormUrl = '../medical_technician/print_gram_stain.php';
} elseif (array_key_exists($labTest['testName'], hc_serology_rapid_test_catalog())) {
    $paperFormUrl = '../medical_technician/print_serology_rapid_test.php';
}
if ($paperFormUrl !== null) {
    $paperFormUrl .= '?embed=1&patientId=' . urlencode($patient['id']);
}
