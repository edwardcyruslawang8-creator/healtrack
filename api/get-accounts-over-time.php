<?php
/**
 * =====================================================================
 * Get Accounts Over Time (api/get-accounts-over-time.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/account-monitoring-ajax.js whenever a range
 * (7/30/90 Days) is picked from the "Accounts Over Time" card's
 * dropdown on admin/account_monitoring.php — returns just that card's
 * real $accountsOverTime data as JSON (same computation
 * includes/admin/account_monitoring_firestore.php's own header comment
 * on that block explains) so the line chart re-draws in place instead
 * of the whole page reloading.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../includes/admin/account_monitoring_firestore.php';
// $accountsOverTimeRange, $accountsOverTime, $accountsOverTimeSeries

echo json_encode([
    'range' => $accountsOverTimeRange,
    'overTime' => [
        'labels' => $accountsOverTime['labels'],
        'series' => array_map(fn ($s) => [
            'key' => $s['key'],
            'label' => $s['label'],
            'color' => $s['color'],
            'data' => $accountsOverTime[$s['key']],
        ], $accountsOverTimeSeries),
    ],
]);
