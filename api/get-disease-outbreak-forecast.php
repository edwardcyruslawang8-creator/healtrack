<?php
/**
 * =====================================================================
 * Get Disease Outbreak Forecast (api/get-disease-outbreak-forecast.php)
 * ---------------------------------------------------------------------
 * Backs the "Disease Outbreak Monitoring & Forecasting" card's live
 * filters (only Disease and Barangay — there is no Date Range or
 * Forecast Horizon control at all any more: the chart always shows the
 * real last 14 days of actual cases followed by a real 7-day-ahead
 * forecast, continuously shifting forward as today's real date changes,
 * see includes/shared/disease_outbreak_forecast.php's own header
 * comment) on admin/facility_analytics.php AND subadmin/predictive_analytics.php —
 * same "one real pipeline, two ways to reach it" pattern as
 * api/get-disease-trends.php (whose auth/facility-lock shape this
 * mirrors exactly), so this card's filters never need a full page
 * reload and can never compute a different answer than the full page
 * itself would.
 *
 * Requires the SAME real pipeline api/get-disease-trends.php uses
 * (includes/admin/facility_analytics_firestore.php) to get
 * $pt1['notifiableCases'], then hands it to
 * hc_disease_outbreak_monitoring() (includes/shared/disease_outbreak_forecast.php),
 * which applies the GIS-eligibility filter + this request's own
 * filters on top of it.
 *
 * A subadmin's own facility_id is always forced to their own real
 * facility (never trusted from the query string) — same lock
 * api/get-disease-trends.php/api/get-patient-trend.php already enforce
 * for the same reason.
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
require_once __DIR__ . '/../includes/shared/disease_outbreak_forecast.php'; // hc_disease_outbreak_monitoring()

$filters = [
    'disease' => trim((string) ($_GET['disease'] ?? 'all')),
    'barangay' => trim((string) ($_GET['barangay'] ?? 'all')),
];

// hc_disease_outbreak_monitoring() already isolates a single bad
// disease's own computation internally (logged, skipped, every other
// disease still returned) — this top-level guard is the backstop for
// anything else unexpected (a genuinely broken Firestore read, a
// malformed filter combination this endpoint didn't already sanitize),
// so a real failure here always comes back as real, parseable JSON the
// card's own JS can show an error state for, never a raw PHP fatal/stack
// trace reaching the browser as broken JSON.
try {
    $result = hc_disease_outbreak_monitoring($pt1['notifiableCases'] ?? [], $filters);
    echo json_encode(['success' => true] + $result);
} catch (\Throwable $e) {
    error_log('api/get-disease-outbreak-forecast.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load disease outbreak data. Please try again.']);
}
