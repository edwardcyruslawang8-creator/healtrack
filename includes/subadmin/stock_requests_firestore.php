<?php
/**
 * =====================================================================
 * Stock Requests — Data
 * ---------------------------------------------------------------------
 * Reads live from the real "stock_requests" Firestore collection
 * (auto-ID documents added via FirestoreRestClient::addDocument(), same
 * pattern as "audit_logs" — see includes/audit_log_writer.php). This is
 * a brand-new collection: it didn't exist anywhere in the app before
 * this session, so it was seeded with a realistic sample of requests
 * tied to the real pharmacist account (Liza Reyes) plus a few
 * fictitious pharmacist names for variety, referencing real medicine
 * names from the "medicines" collection.
 *
 * The status/pharmacist filters and search box now really filter
 * $stockRequests. Approve/Reject are now real too (three-dot menu ->
 * assets/js/stock_requests.js -> api/update-stock-request-status.php,
 * pending-only, both write real status changes to the same doc). The
 * date range button, "+ New Stock Request" button, each row's "View
 * Items" link, Release/Edit/Print/Delete, and pagination beyond page 1
 * are still not wired up.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/stock_requests.php';
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items() — $medicineNames/$vaccineNames below
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav(), hc_matches_date_range_key()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Sub-Administrator',
    },
    'facility' => $myDoc['facility'] ?? 'Unassigned Facility',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$requestTypeMeta = hc_stock_request_type_meta();
$requestStatusMeta = hc_stock_request_status_meta();

// Real medicine/vaccine names for the Edit Request modal's item-name
// datalist (includes/pharmacist/new_stock_request_modal.php, reused
// here — same autocomplete-only convenience pharmacist's own "+ New
// Stock Request"/"Edit Request" modal already has).
$medicineNames = array_column(hc_get_inventory_items('medicine'), 'name');
$vaccineNames = array_column(hc_get_inventory_items('vaccine'), 'name');

// ---------------------------------------------------------------------
// Real stock request list — every stock_requests/{autoId} doc, newest
// first, scoped to this sub-admin's own real facility (each doc's own
// "facility" field) — a sub-admin manages their own site's requests,
// not every facility's combined.
// ---------------------------------------------------------------------
$allRequests = array_values(array_filter(hc_get_all_stock_requests(), fn ($r) => $r['facility'] === $adminProfile['facility']));
$seenPharmacists = array_unique(array_column($allRequests, 'pharmacist'));
sort($seenPharmacists);

$pharmacistOptions = array_merge(['All Pharmacists'], $seenPharmacists);
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
// Apply the status/pharmacist filters and search box (?status=,
// ?pharmacist=, ?q=) for real.
// ---------------------------------------------------------------------
$statusFilter = $_GET['status'] ?? 'All Status';
$pharmacistFilter = $_GET['pharmacist'] ?? 'All Pharmacists';
$searchTerm = trim($_GET['q'] ?? '');

$filteredRequests = array_values(array_filter($allRequests, function ($r) use ($statusFilter, $pharmacistFilter, $searchTerm, $requestStatusMeta, $requestDateRangeFilter) {
    if ($statusFilter !== 'All Status' && ($requestStatusMeta[$r['status']]['label'] ?? '') !== $statusFilter) {
        return false;
    }
    if ($pharmacistFilter !== 'All Pharmacists' && $r['pharmacist'] !== $pharmacistFilter) {
        return false;
    }
    if (!hc_matches_date_range_key($r['requestedAtRaw'] ?? '', $requestDateRangeFilter)) {
        return false;
    }
    if ($searchTerm !== '') {
        $itemNames = implode(' ', array_column($r['items'], 'name'));
        if (!hc_fuzzy_match($r['id'] . ' ' . $r['pharmacist'] . ' ' . $itemNames, $searchTerm)) {
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
