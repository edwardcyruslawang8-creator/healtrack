<?php
/**
 * =====================================================================
 * Stock Requests (Pharmacist) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — reads the real "stock_requests" Firestore collection via
 * includes/firestore/stock_requests.php, the same collection
 * subadmin/stock_requests.php reads. UNLIKE subadmin's view (which
 * sees every pharmacist's requests, since deciding on them is a
 * sub-admin capability), this page is scoped to ONLY the signed-in
 * pharmacist's own requests ($r['pharmacistUid'] === the real session
 * uid) — a pharmacist manages their own requests here; reviewing
 * everyone's belongs on the subadmin side. The "All Pharmacists"
 * filter that used to sit here is gone along with that broader scope
 * (nothing left to filter by once every row is already "you").
 *
 * The status filter and search box really filter $stockRequests.
 * "+ New Stock Request" (includes/pharmacist/new_stock_request_modal.php
 * -> assets/js/new-stock-request.js -> api/create-stock-request.php)
 * creates a real request; the SAME modal opens pre-filled for "Edit
 * Request" on any of the pharmacist's own still-pending rows
 * (api/update-stock-request.php). Delete Request
 * (api/delete-stock-request.php) and Print Request are real too. Both
 * write endpoints re-check ownership (pharmacistUid) and, for edit,
 * pending-only status server-side — never trusting the UI having
 * already scoped the list. Approve/Reject/Release Items were removed
 * from this page entirely — deciding on a request is subadmin's job,
 * not the requesting pharmacist's own. View Request (read-only
 * modal), each row's "View Items" link, the date range button, and
 * pagination beyond page 1 are still not wired up.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/stock_requests.php';
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items()
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav(), hc_matches_date_range_key()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Pharmacist',
    'role'     => 'Pharmacist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$requestTypeMeta = hc_stock_request_type_meta();
$requestStatusMeta = hc_stock_request_status_meta();

// Real medicine/vaccine names for the "+ New Stock Request"/"Edit
// Request" modal's item picker — only the name is needed there (not
// full stock levels).
$medicineNames = array_column(hc_get_inventory_items('medicine'), 'name');
$vaccineNames = array_column(hc_get_inventory_items('vaccine'), 'name');

// ---------------------------------------------------------------------
// Real stock request list — ONLY this signed-in pharmacist's own
// requests (see header comment for why this differs from subadmin's
// see-everyone view), newest first.
// ---------------------------------------------------------------------
$allRequests = array_values(array_filter(hc_get_all_stock_requests(), fn ($r) => $r['pharmacistUid'] === $myUid));

$requestStatusOptions = ['All Status', 'Pending', 'Approved', 'Released', 'Rejected'];
$requestDateRangeOptions = ['all' => 'All Dates', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month'];
$requestDateRangeFilter = array_key_exists($_GET['daterange'] ?? '', $requestDateRangeOptions) ? $_GET['daterange'] : 'all';
$requestDateRangeLabel = $requestDateRangeOptions[$requestDateRangeFilter];

// ---------------------------------------------------------------------
// Stock Request Summary — real counts over $allRequests.
// ---------------------------------------------------------------------
$stockRequestSummary = [
    'totalRequests' => count($allRequests),
    'pending' => count(array_filter($allRequests, fn ($r) => $r['status'] === 'pending')),
];

// ---------------------------------------------------------------------
// Apply the status filter and search box (?status=, ?q=) for real.
// ---------------------------------------------------------------------
$statusFilter = $_GET['status'] ?? 'All Status';
$searchTerm = trim($_GET['q'] ?? '');

$filteredRequests = array_values(array_filter($allRequests, function ($r) use ($statusFilter, $searchTerm, $requestStatusMeta, $requestDateRangeFilter) {
    if ($statusFilter !== 'All Status' && ($requestStatusMeta[$r['status']]['label'] ?? '') !== $statusFilter) {
        return false;
    }
    if (!hc_matches_date_range_key($r['requestedAtRaw'] ?? '', $requestDateRangeFilter)) {
        return false;
    }
    if ($searchTerm !== '') {
        $itemNames = implode(' ', array_column($r['items'], 'name'));
        if (!hc_fuzzy_match($r['id'] . ' ' . $itemNames, $searchTerm)) {
            return false;
        }
    }
    return true;
}));

// ---------------------------------------------------------------------
// Pagination — real total/page-count from the filtered result above.
// ---------------------------------------------------------------------
$stockRequestsPerPage = 8;
$stockRequestsPagination = hc_paginate($filteredRequests, 'page', $stockRequestsPerPage);
$stockRequests = $stockRequestsPagination['items'];
$stockRequestsTotal = $stockRequestsPagination['total'];
$stockRequestsTotalPages = $stockRequestsPagination['totalPages'];
$stockRequestsCurrentPage = $stockRequestsPagination['page'];
