<?php
/**
 * =====================================================================
 * Get Stock Movement (api/get-stock-movement.php)
 * ---------------------------------------------------------------------
 * Backs the "Stock Movement Summary" card's date-range filter
 * (?action=summary) and its "View Stock Movements" modal's real
 * transaction list (?action=transactions) on BOTH
 * subadmin/inventory_reports.php and pharmacist/analytics_reports.php
 * — the card is system-wide inventory data, not owned by one role, so
 * the same endpoint serves both (see includes/shared/inventory_movement_monitor.php's
 * own header comment). Both pages call the SAME real functions their
 * own default render already uses, so these never drift from what
 * either page itself would compute.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL
require_once __DIR__ . '/../includes/shared/inventory_movement_monitor.php';

header('Content-Type: application/json');

// Same roles the two pages embedding this card are gated to
// (subadmin/inventory_reports.php, pharmacist/analytics_reports.php)
// — inventory is system-wide, not facility-scoped (see
// inventory_reports_firestore.php's own header comment), so no
// facility lock is needed here.
if (!in_array($_SESSION['role'] ?? null, ['subadmin', 'pharmacist'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$startDate = null;
$endDate = null;
$startRaw = trim((string) ($_GET['start'] ?? ''));
$endRaw = trim((string) ($_GET['end'] ?? ''));
if ($startRaw !== '' && $endRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startRaw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endRaw) && $startRaw <= $endRaw) {
    $startDate = $startRaw;
    $endDate = $endRaw;
} else {
    $daysRaw = trim((string) ($_GET['days'] ?? '30'));
    $days = in_array($daysRaw, ['7', '30', '90'], true) ? (int) $daysRaw : 30;
    $startDate = date('Y-m-d', strtotime("-$days days"));
    $endDate = date('Y-m-d');
}

$action = $_GET['action'] ?? 'summary';

if ($action === 'transactions') {
    $transactions = hc_compute_stock_movement_transactions($startDate, $endDate);
    echo json_encode(['success' => true, 'transactions' => $transactions, 'start' => $startDate, 'end' => $endDate]);
    exit;
}

$summary = hc_compute_stock_movement_summary($startDate, $endDate);
echo json_encode(['success' => true, 'summary' => $summary, 'start' => $startDate, 'end' => $endDate]);
