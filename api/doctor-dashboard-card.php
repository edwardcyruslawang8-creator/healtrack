<?php
/**
 * =====================================================================
 * Doctor Dashboard — one card's data (api/doctor-dashboard-card.php)
 * ---------------------------------------------------------------------
 * Backs doctor/dashboard.php's 2 filterable cards (Consultations Trend,
 * Patients by Age Group) — assets/js/dashboard-range-filters.js calls
 * this instead of following the filter dropdown's own href (a full
 * ?trendRange=... page reload), so picking a range only re-fetches and
 * re-renders that ONE card instead of the whole page.
 *
 * Reuses includes/doctor/doctor_dashboard_firestore.php AS-IS — the
 * exact same file/computation doctor/dashboard.php's own full load
 * already runs, reading the same $_GET['trendRange']/['ageRange'] it
 * always has — so this can never drift from what a full page reload
 * would have shown for the same range. Same tradeoff as
 * api/subadmin-dashboard-card.php (the first role with this pattern).
 * =====================================================================
 */

session_start();

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$card = $_GET['card'] ?? '';
if (!in_array($card, ['consultationsTrend', 'patientsByAge'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unknown card.']);
    exit;
}

require __DIR__ . '/../includes/doctor/doctor_dashboard_firestore.php';
// $consultationsTrendRange, $consultationsTrend, $patientsByAgeRange,
// $patientsByAge — all real, recomputed for whichever range this
// request's own ?trendRange=/?ageRange= selects.

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
}
