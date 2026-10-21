<?php
/**
 * =====================================================================
 * Radio Technologist Dashboard — one card's data (api/radiotech-dashboard-card.php)
 * ---------------------------------------------------------------------
 * Backs radio_technologist/dashboard.php's one filterable card (Exams
 * Processed Trend) — assets/js/dashboard-range-filters.js calls this
 * instead of following the filter dropdown's own href (a full
 * ?trendRange=... page reload), so picking a range only re-fetches and
 * re-renders that ONE card instead of the whole page.
 *
 * Reuses includes/radio_technologist/radio_technologist_dashboard_firestore.php
 * AS-IS — the exact same file/computation the page's own full load
 * already runs, reading the same $_GET['trendRange'] it always has —
 * so this can never drift from what a full page reload would have
 * shown for the same range. Same pattern as
 * api/medical-technician-dashboard-card.php.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // hc_radiotech_unread_notification_count(), used by the stat cards the required firestore file below also builds

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'radio_technologist') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$card = $_GET['card'] ?? '';
if ($card !== 'examsTrend') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unknown card.']);
    exit;
}

require __DIR__ . '/../includes/radio_technologist/radio_technologist_dashboard_firestore.php';
// $examsTrendRange, $examsTrend — real, recomputed for whichever range
// this request's own ?trendRange= selects.

echo json_encode([
    'success' => true,
    'rangeLabel' => $examsTrendRange,
    'labels' => $examsTrend['labels'],
    'values' => $examsTrend['values'],
]);
