<?php
/**
 * =====================================================================
 * Get Recent Stock Requests Table (api/stock-request-reports-recent-table.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/srr-recent-pagination.js whenever a First/
 * Previous/page-number/Next/Last link inside subadmin/stock_request_reports.php's
 * "Recent Stock Requests" card is clicked — returns just that card's
 * HTML fragment (same markup includes/subadmin/_srr_recent_requests_card.php
 * renders on a normal full page load) so only this one card re-renders,
 * instead of a full page reload that would also needlessly re-fetch
 * every stat card and both doughnuts next to it.
 *
 * Re-requires the SAME data file subadmin/stock_request_reports.php
 * does, so every query param this page already understands (srrPage,
 * plus the filter toolbar's range/status/category/requestedBy) is read
 * exactly the same way — this file just runs that one data file, then
 * echoes one fragment instead of the whole page.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../includes/subadmin/stock_request_reports_firestore.php';
// $recentRequests, $requestStatusMeta, $recentRequestsTotal,
// $recentRequestsCurrentPage, $recentRequestsTotalPages

header('Content-Type: application/json');

ob_start();
require __DIR__ . '/../includes/subadmin/_srr_recent_requests_card.php';
$html = ob_get_clean();

echo json_encode(['success' => true, 'html' => $html]);
