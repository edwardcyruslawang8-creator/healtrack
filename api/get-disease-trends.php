<?php
/**
 * =====================================================================
 * Get Disease Trends (api/get-disease-trends.php)
 * ---------------------------------------------------------------------
 * Backs the "Potential Notifiable Disease Cases" card's date-range
 * filter (?action=summary) and its "View Trends" modal's weekly/
 * monthly aggregation (?action=trend) on admin/facility_analytics.php
 * AND subadmin/predictive_analytics.php.
 *
 * Requires the SAME real pipeline those pages use
 * (includes/admin/facility_analytics_firestore.php) to get
 * $pt1['notifiableCases'], then applies the requested date range/
 * aggregation on top of it — same "one real pipeline, two ways to
 * reach it" pattern as api/get-patient-trend.php, so this never drifts
 * from what the full page itself would compute.
 *
 * A subadmin's own facility_id is always forced to their own real
 * facility (never trusted from the query string) — same lock
 * api/get-patient-trend.php already enforces for the same reason.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['admin', 'subadmin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($role === 'subadmin') {
    require_once __DIR__ . '/../includes/shared/facility_resolver.php'; // hc_resolve_own_facility_id()
    $_GET['facility_id'] = hc_resolve_own_facility_id() ?? '';
}

require_once __DIR__ . '/../includes/admin/facility_analytics_firestore.php';
// $pt1['notifiableCases'] — see that file's own header comment.
require_once __DIR__ . '/../includes/shared/predictive_analytics_core.php'; // hc_notifiable_disease_summary(), hc_notifiable_disease_trend_aggregated()

$notifiableCases = $pt1['notifiableCases'] ?? [];

// -------------------- resolve the requested date range --------------------
$startDate = null;
$endDate = null;
$startRaw = trim((string) ($_GET['start'] ?? ''));
$endRaw = trim((string) ($_GET['end'] ?? ''));
if ($startRaw !== '' && $endRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startRaw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endRaw) && $startRaw <= $endRaw) {
    $startDate = $startRaw;
    $endDate = $endRaw;
} else {
    $daysRaw = trim((string) ($_GET['days'] ?? '90'));
    $days = in_array($daysRaw, ['7', '30', '90'], true) ? (int) $daysRaw : 90;
    $startDate = date('Y-m-d', strtotime("-$days days"));
    $endDate = date('Y-m-d');
}

$action = $_GET['action'] ?? 'summary';

if ($action === 'trend') {
    $granularity = ($_GET['granularity'] ?? 'week') === 'month' ? 'month' : 'week';
    $trend = hc_notifiable_disease_trend_aggregated($notifiableCases, $startDate, $endDate, $granularity);
    echo json_encode(['success' => true, 'trend' => $trend, 'granularity' => $granularity, 'start' => $startDate, 'end' => $endDate]);
    exit;
}

$summary = hc_notifiable_disease_summary($notifiableCases, $startDate, $endDate);
echo json_encode(['success' => true, 'summary' => $summary, 'start' => $startDate, 'end' => $endDate]);
