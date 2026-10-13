<?php
/**
 * =====================================================================
 * Get Stock Requests Trend (api/get-stock-requests-trend.php)
 * ---------------------------------------------------------------------
 * Backs the "My Stock Requests Over Time" card's Daily/Weekly/Monthly
 * range dropdown on pharmacist/analytics_reports.php
 * (assets/js/analytics_reports.js) — selecting a range now updates just
 * that one card via fetch(), instead of a full page reload, same
 * pattern as api/get-consultation-trend.php. Simply requires the SAME
 * real data file the page itself uses
 * (includes/pharmacist/analytics_reports_pharmacist_firestore.php,
 * which reads $_GET['requestsRange'] itself and is already scoped to
 * the signed-in pharmacist's own stock requests) and echoes back just
 * the trend, rather than a second, parallel computation that could
 * drift from what the full page shows.
 *
 * Pharmacist only.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL

if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../includes/pharmacist/analytics_reports_pharmacist_firestore.php';
// $trendLabels, $trendCounts, $requestsTrendRange, $requestsTrendRangeOptions

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'labels' => $trendLabels,
    'values' => $trendCounts,
    'range' => $requestsTrendRange,
    'rangeLabel' => $requestsTrendRangeOptions[$requestsTrendRange],
]);
