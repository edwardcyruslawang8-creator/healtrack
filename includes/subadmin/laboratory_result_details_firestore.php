<?php
/**
 * =====================================================================
 * Laboratory Result Details — Data
 * ---------------------------------------------------------------------
 * Reuses includes/patient_profile_firestore.php for everything about the
 * patient themselves ($adminProfile, $patient) — $profileTabs isn't
 * used here since this page has no tab strip (it's a drill-down detail
 * view reached via a "View" button, not one of the patient profile's
 * own tabs).
 *
 * This page shows ONE lab test and ONE x-ray exam side by side; both
 * bundles are resolved by includes/firestore/lab_results.php's
 * hc_resolve_lab_result() and includes/firestore/xray_results.php's
 * hc_resolve_xray_result() — shared with medical-staff's identical
 * page. Clicking a lab row shows that lab test paired with the
 * patient's most recent x-ray (and vice versa); if the patient has no
 * lab result (or no x-ray) at all, that half shows an empty-state
 * shape instead of erroring.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient
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
