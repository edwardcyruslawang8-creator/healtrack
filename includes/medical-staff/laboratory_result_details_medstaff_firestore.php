<?php
/**
 * =====================================================================
 * Laboratory Result Details (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/patient_profile_medstaff_firestore.php for everything
 * about the patient themselves ($staffProfile, $patient) — $profileTabs
 * isn't used here since this page has no tab strip (it's a drill-down
 * detail view reached via a "View" button, not one of the patient
 * profile's own tabs).
 *
 * This page shows ONE lab test and ONE x-ray exam side by side; both
 * bundles are resolved by includes/firestore/lab_results.php's
 * hc_resolve_lab_result() and includes/firestore/xray_results.php's
 * hc_resolve_xray_result() — shared with subadmin's identical page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../firestore/xray_results.php';

$statusMeta = [
    'completed' => ['label' => 'Completed', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'pending'   => ['label' => 'Pending',   'bg' => '#FDECDD', 'color' => '#E8710A'],
];

$requestedType = $_GET['type'] ?? '';
$requestedRecordId = $_GET['recordId'] ?? '';

$labBundle = hc_resolve_lab_result($patient['id'], $requestedType === 'lab', $requestedRecordId);
$labTest = $labBundle['labTest'];
$labResultBadge = $labBundle['labResultBadge'];
$labResultGroups = $labBundle['labResultGroups'];
$labInterpretation = $labBundle['labInterpretation'];

$xrayBundle = hc_resolve_xray_result($patient['id'], $requestedType === 'xray', $requestedRecordId);
$xrayExam = $xrayBundle['xrayExam'];
$xrayFindings = $xrayBundle['xrayFindings'];
$xrayImpression = $xrayBundle['xrayImpression'];
