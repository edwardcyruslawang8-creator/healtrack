<?php
/**
 * =====================================================================
 * Get Consultation Trend (api/get-consultation-trend.php)
 * ---------------------------------------------------------------------
 * Backs the "Consultations Over Time" card's Daily/Weekly/Monthly range
 * dropdown on subadmin/analytics_reports.php (assets/js/analytics_reports.js)
 * — selecting a range now updates just that one card via fetch(), instead
 * of a full page reload, same pattern as api/get-patient-trend.php.
 * Simply requires the SAME real data file the page itself uses
 * (includes/subadmin/analytics_reports_firestore.php, which reads
 * $_GET['consultRange'] itself and is already locked to the signed-in
 * sub-admin's own facility via hc_resolve_own_facility_id() —
 * includes/subadmin/predictive_analytics_firestore.php, required at the
 * top of that file) and echoes back just the trend, rather than a
 * second, parallel computation that could drift from what the full page
 * shows.
 *
 * Sub-admin only — there is no admin equivalent of this Overview page.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../includes/subadmin/analytics_reports_firestore.php';
// $consultationsOverTimeLabels, $consultationsOverTimeValues, $consultTrendRange, $consultTrendRangeOptions

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'labels' => $consultationsOverTimeLabels,
    'values' => $consultationsOverTimeValues,
    'range' => $consultTrendRange,
    'rangeLabel' => $consultTrendRangeOptions[$consultTrendRange],
]);
