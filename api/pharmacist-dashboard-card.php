<?php
/**
 * =====================================================================
 * Pharmacist Dashboard — one card's data (api/pharmacist-dashboard-card.php)
 * ---------------------------------------------------------------------
 * Backs pharmacist/dashboard.php's 2 filterable cards (Dispensing
 * Trend, Medicines by Category) — assets/js/dashboard-range-filters.js
 * calls this instead of following the filter dropdown's own href (a
 * full ?trendRange=.../?categoryRange=... page reload), so picking a
 * range only re-fetches and re-renders that ONE card instead of the
 * whole page.
 *
 * Reuses includes/pharmacist/pharmacist_dashboard_firestore.php AS-IS
 * — the exact same file/computation pharmacist/dashboard.php's own
 * full load already runs, reading the same $_GET['trendRange']/
 * ['categoryRange'] it always has — so this can never drift from what
 * a full page reload would have shown for the same range. Same
 * tradeoff as api/doctor-dashboard-card.php and
 * api/medical-technician-dashboard-card.php.
 * =====================================================================
 */

session_start();

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$card = $_GET['card'] ?? '';
if (!in_array($card, ['dispensingTrend', 'medicinesByCategory'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unknown card.']);
    exit;
}

require __DIR__ . '/../includes/pharmacist/pharmacist_dashboard_firestore.php';
// $dispensingTrendRange, $dispensingTrend, $medicinesByCategoryRange,
// $medicinesByCategory — all real, recomputed for whichever range this
// request's own ?trendRange=/?categoryRange= selects.

switch ($card) {
    case 'dispensingTrend':
        echo json_encode([
            'success' => true,
            'rangeLabel' => $dispensingTrendRange,
            'labels' => $dispensingTrend['labels'],
            'values' => $dispensingTrend['values'],
        ]);
        break;

    case 'medicinesByCategory':
        echo json_encode([
            'success' => true,
            'rangeLabel' => $medicinesByCategoryRange,
            'labels' => $medicinesByCategory['labels'],
            'values' => $medicinesByCategory['values'],
        ]);
        break;
}
