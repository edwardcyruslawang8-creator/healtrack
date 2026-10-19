<?php
/**
 * =====================================================================
 * Medical Technician Dashboard — one card's data (api/medical-technician-dashboard-card.php)
 * ---------------------------------------------------------------------
 * Backs medical_technician/dashboard.php's 2 filterable cards (Tests
 * Processed Trend, Tests by Type) — assets/js/dashboard-range-filters.js
 * calls this instead of following the filter dropdown's own href (a
 * full ?trendRange=.../?typeRange=... page reload), so picking a range
 * only re-fetches and re-renders that ONE card instead of the whole
 * page.
 *
 * Reuses includes/medical_technician/medical_technician_dashboard_firestore.php
 * AS-IS — the exact same file/computation medical_technician/dashboard.php's
 * own full load already runs, reading the same $_GET['trendRange']/
 * ['typeRange'] it always has — so this can never drift from what a
 * full page reload would have shown for the same range. Same tradeoff
 * as api/subadmin-dashboard-card.php (the first role with this
 * pattern).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // hc_medtech_unread_notification_count(), used by the stat cards the required firestore file below also builds

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$card = $_GET['card'] ?? '';
if (!in_array($card, ['testsTrend', 'testsByType'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unknown card.']);
    exit;
}

require __DIR__ . '/../includes/medical_technician/medical_technician_dashboard_firestore.php';
// $testsTrendRange, $testsTrend, $testsByTypeRange, $testsByType — all
// real, recomputed for whichever range this request's own
// ?trendRange=/?typeRange= selects.

switch ($card) {
    case 'testsTrend':
        echo json_encode([
            'success' => true,
            'rangeLabel' => $testsTrendRange,
            'labels' => $testsTrend['labels'],
            'values' => $testsTrend['values'],
        ]);
        break;

    case 'testsByType':
        echo json_encode([
            'success' => true,
            'rangeLabel' => $testsByTypeRange,
            'labels' => $testsByType['labels'],
            'values' => $testsByType['values'],
        ]);
        break;
}
