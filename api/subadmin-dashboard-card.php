<?php
/**
 * =====================================================================
 * Sub-Admin Dashboard — one card's data (api/subadmin-dashboard-card.php)
 * ---------------------------------------------------------------------
 * Backs subadmin/dashboard.php's 3 filterable cards (Consultations
 * Trend, Patients by Age Group, Top 5 Common Health Conditions) —
 * assets/js/dashboard-range-filters.js (shared by every role's
 * dashboard) calls this instead of following the filter dropdown's own
 * href (a full ?trendRange=... page reload), so picking a range only
 * re-fetches and re-renders that ONE card instead of the whole page.
 *
 * Reuses includes/subadmin/subadmin_dashboard_firestore.php AS-IS — the
 * exact same file/computation subadmin/dashboard.php's own full load
 * already runs, reading the same $_GET['trendRange']/['ageRange']/
 * ['conditionsRange'] it always has — so this can never drift from what
 * a full page reload would have shown for the same range. Slightly
 * more Firestore work than a hand-trimmed single-card query would be
 * (it still computes the other cards too, just unused here), but that
 * matches this app's existing "fine at this app's scale" tradeoff
 * elsewhere rather than risking a second, hand-maintained copy of the
 * real facility-scoping/date-range logic drifting out of sync.
 * =====================================================================
 */

session_start();

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$card = $_GET['card'] ?? '';
if (!in_array($card, ['consultationsTrend', 'patientsByAge', 'healthConditions'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unknown card.']);
    exit;
}

require __DIR__ . '/../includes/subadmin/subadmin_dashboard_firestore.php';
// $consultationsTrendRange, $consultationsTrend, $patientsByAgeRange,
// $patientsByAge, $healthConditionsRange, $topHealthConditions,
// $topHealthConditionsAxis, $topHealthConditionsAxisMax — all real,
// recomputed for whichever range this request's own ?trendRange=/
// ?ageRange=/?conditionsRange= selects.

switch ($card) {
    case 'consultationsTrend':
        echo json_encode([
            'success' => true,
            'rangeLabel' => $consultationsTrendRange,
            'labels' => $consultationsTrend['labels'],
            'values' => $consultationsTrend['values'],
        ]);
        break;

    case 'patientsByAge':
        echo json_encode([
            'success' => true,
            'rangeLabel' => $patientsByAgeRange,
            'labels' => $patientsByAge['labels'],
            'values' => $patientsByAge['values'],
        ]);
        break;

    case 'healthConditions':
        ob_start();
        require __DIR__ . '/../includes/subadmin/_dashboard_health_conditions_list.php';
        $html = ob_get_clean();

        echo json_encode([
            'success' => true,
            'rangeLabel' => $healthConditionsRange,
            'html' => $html,
        ]);
        break;
}
