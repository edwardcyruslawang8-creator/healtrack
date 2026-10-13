<?php
/**
 * =====================================================================
 * Get Patient Trend (api/get-patient-trend.php)
 * ---------------------------------------------------------------------
 * Backs the Patient Trend card's Daily/Weekly/Monthly/Yearly range
 * dropdown on admin/facility_analytics.php AND subadmin/facility_analytics.php
 * (assets/js/facility_analytics.js) — selecting a range now updates
 * just that one card via fetch(), instead of a full page reload.
 * Simply requires the SAME real data file the page itself uses
 * (includes/admin/facility_analytics_firestore.php, which reads
 * $_GET['facility_id']/$_GET['range'] itself) and echoes back just
 * $patientTrend, rather than a second, parallel computation that could
 * drift from what the full page shows — the one real pipeline, two
 * ways to reach it (full page load, or this lighter refresh).
 *
 * A subadmin must NEVER be able to fetch another facility's predictive
 * data by editing the query string — unlike admin (who may legitimately
 * view any facility), a subadmin's own $_GET['facility_id'] is ignored
 * outright and forced to hc_resolve_own_facility_id()'s real result
 * (includes/shared/facility_resolver.php — the same lock
 * includes/subadmin/facility_analytics_firestore.php's own page-load
 * path already enforces).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL

$role = $_SESSION['role'] ?? null;

// Only a signed-in admin or sub-admin (same gate the two facility_analytics.php pages themselves enforce).
if (!in_array($role, ['admin', 'subadmin'], true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($role === 'subadmin') {
    require_once __DIR__ . '/../includes/shared/facility_resolver.php'; // hc_resolve_own_facility_id()
    $_GET['facility_id'] = hc_resolve_own_facility_id() ?? '';
}

require_once __DIR__ . '/../includes/admin/facility_analytics_firestore.php';
// $patientTrend, $patientTrendRange, $patientTrendRangeOptions — see that file's header comment

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'patientTrend' => $patientTrend,
    'range' => $patientTrendRange,
    'rangeLabel' => $patientTrendRangeOptions[$patientTrendRange],
]);