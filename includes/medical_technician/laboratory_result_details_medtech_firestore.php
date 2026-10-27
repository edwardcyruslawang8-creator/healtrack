<?php
/**
 * =====================================================================
 * Laboratory Result Details (Medical Technologist) — Data
 * ---------------------------------------------------------------------
 * Reached from medical_technician/laboratory_xray.php's "View
 * Report"/eye-icon links, one lab test and one x-ray exam side by
 * side — same shape as medical-staff's/subadmin's
 * laboratory_result_details.php, both resolved by
 * includes/firestore/lab_results.php's hc_resolve_lab_result() and
 * includes/firestore/xray_results.php's hc_resolve_xray_result().
 *
 * Unlike the medical-staff/subadmin version, this page isn't reached
 * from a per-patient profile tab strip, so $patient is resolved
 * directly from ?id= here rather than via a shared
 * patient_profile_*_firestore.php include — there's no medical
 * technician patient-profile page (this role works a request queue,
 * not per-patient charts).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../firestore/xray_results.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$requestedPatientId = $_GET['id'] ?? '';
$patient = hc_get_patient_profile($requestedPatientId);
// Facility isolation — same rule as includes/doctor/patient_profile_doctor_firestore.php's
// own copy of this check: a real patient from another facility must
// render exactly like an invalid id, never leak that their result
// exists at all to a medical technologist outside the facility that
// registered them.
if ($patient !== null && $patient['facility'] !== $staffProfile['facility']) {
    $patient = null;
    $requestedPatientId = '';
}
$patient = $patient ?? hc_not_found_patient_profile($requestedPatientId);

$statusMeta = [
    'completed' => ['label' => 'Completed', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'pending'   => ['label' => 'Pending',   'bg' => '#FDECDD', 'color' => '#E8710A'],
];

$requestedType = $_GET['type'] ?? '';
$requestedRecordId = $_GET['recordId'] ?? '';
// Which ONE of the two halves the template actually renders — this
// page shows only the result type actually requested, never both.
$resultType = $requestedType === 'xray' ? 'xray' : 'lab';

$labBundle = hc_resolve_lab_result($patient['id'], $requestedType === 'lab', $requestedRecordId);
$labTest = $labBundle['labTest'];
$labResultBadge = $labBundle['labResultBadge'];
$labResultGroups = $labBundle['labResultGroups'];
$labInterpretation = $labBundle['labInterpretation'];

$xrayBundle = hc_resolve_xray_result($patient['id'], $requestedType === 'xray', $requestedRecordId);
$xrayExam = $xrayBundle['xrayExam'];
$xrayFindings = $xrayBundle['xrayFindings'];
$xrayImpression = $xrayBundle['xrayImpression'];
