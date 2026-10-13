<?php
/**
 * =====================================================================
 * Analytics & Reports (Pharmacist) — Data
 * ---------------------------------------------------------------------
 * Stock requests are scoped to the signed-in pharmacist's OWN requests
 * (stock_requests/{id}.pharmacistUid === their uid, the same real field
 * pharmacist/stock_requests.php already scopes "My Requests" by) and
 * dispensing to items THEY personally dispensed
 * (pharmacy_orders/{id}.dispensedBy === their name, the same field
 * api/dispense-pharmacy-order.php stamps) — a pharmacist's own activity,
 * not the whole facility's (that's subadmin/analytics_reports.php's
 * job). Inventory Status Summary is the one facility-wide number here
 * on purpose — stock levels aren't owned by any one pharmacist, so
 * scoping that to "mine" would just be wrong.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/stock_requests.php';
require_once __DIR__ . '/../firestore/medicines.php';
require_once __DIR__ . '/../firestore/pharmacy_orders.php';
require_once __DIR__ . '/../shared/forecast_helpers.php'; // hc_facility_trend_by_range() — real Daily/Weekly/Monthly range switch for "My Stock Requests Over Time"

$myUid = $_SESSION['uid'] ?? '';
$myName = $_SESSION['name'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $myName ?: 'Pharmacist',
    'role'     => 'Pharmacist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$analyticsDateRangeLabel = 'Last 14 Days';

$statusMeta = hc_stock_request_status_meta();

$myRequests = array_values(array_filter(hc_get_all_stock_requests(), fn ($r) => $r['pharmacistUid'] === $myUid));

$myDispensed = [];
foreach (hc_get_all_pharmacy_order_docs() as $doc) {
    $f = $doc['fields'];
    if (($f['status'] ?? '') === 'dispensed' && ($f['dispensedBy'] ?? '') === $myName) {
        $myDispensed[] = $f;
    }
}

$inventoryStatusCounts = ['available' => 0, 'low' => 0, 'unavailable' => 0];
foreach (array_merge(hc_get_inventory_items('medicine'), hc_get_inventory_items('vaccine')) as $item) {
    $inventoryStatusCounts[$item['status']] = ($inventoryStatusCounts[$item['status']] ?? 0) + 1;
}

// ---------------------------------------------------------------------
// KPI cards
// ---------------------------------------------------------------------
$pendingRequestsCount = count(array_filter($myRequests, fn ($r) => $r['status'] === 'pending'));
$lowOrOutCount = $inventoryStatusCounts['low'] + $inventoryStatusCounts['unavailable'];

$kpiCards = [
    ['icon' => 'bi-box-seam-fill',   'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'My Stock Requests',  'value' => count($myRequests),        'sub' => 'All-time',  'trend' => 'up'],
    ['icon' => 'bi-hourglass-split', 'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Pending Requests',   'value' => $pendingRequestsCount,     'sub' => 'Awaiting',  'trend' => 'up'],
    ['icon' => 'bi-capsule',         'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Items I Dispensed', 'value' => count($myDispensed),       'sub' => 'All-time',  'trend' => 'up'],
    ['icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Low / Out of Stock', 'value' => $lowOrOutCount, 'sub' => 'Facility-wide', 'trend' => 'up'],
];

// ---------------------------------------------------------------------
// Trend — my stock requests, real Daily/Weekly/Monthly range switch
// (same hc_facility_trend_by_range() bucketing admin/subadmin's own
// Patient Trend / Consultations Over Time cards use — now shared via
// includes/shared/forecast_helpers.php instead of copied).
// ---------------------------------------------------------------------
$requestsTrendRangeOptions = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
$requestsTrendRange = $_GET['requestsRange'] ?? 'daily';
if (!array_key_exists($requestsTrendRange, $requestsTrendRangeOptions)) {
    $requestsTrendRange = 'daily';
}
$requestsTrendBucketed = hc_facility_trend_by_range(array_column($myRequests, 'requestedAtRaw'), $requestsTrendRange);
$trendLabels = $requestsTrendBucketed['labels'];
$trendCounts = $requestsTrendBucketed['values'];

// ---------------------------------------------------------------------
// My Stock Requests by Status (donut)
// ---------------------------------------------------------------------
$byStatus = [];
foreach ($myRequests as $r) {
    $byStatus[$r['status']] = ($byStatus[$r['status']] ?? 0) + 1;
}
$requestsByStatus = [];
foreach ($statusMeta as $key => $meta) {
    if (($byStatus[$key] ?? 0) > 0) {
        $requestsByStatus[] = ['label' => $meta['label'], 'value' => $byStatus[$key], 'color' => $meta['color']];
    }
}
$requestsByStatusTotal = array_sum(array_column($requestsByStatus, 'value'));

// ---------------------------------------------------------------------
// Inventory Status Summary (donut) — facility-wide, medicines + vaccines.
// ---------------------------------------------------------------------
$medicineStatusMeta = hc_medicine_status_meta();
$inventoryStatusSummary = [];
foreach ($medicineStatusMeta as $key => $meta) {
    if (($inventoryStatusCounts[$key] ?? 0) > 0) {
        $inventoryStatusSummary[] = ['label' => $meta['label'], 'value' => $inventoryStatusCounts[$key], 'color' => $meta['color']];
    }
}
$inventoryStatusTotal = array_sum(array_column($inventoryStatusSummary, 'value'));

// ---------------------------------------------------------------------
// Top 5 Medicines/Vaccines I Dispensed.
// ---------------------------------------------------------------------
$itemCounts = [];
foreach ($myDispensed as $f) {
    $name = trim((string) ($f['itemName'] ?? ''));
    if ($name === '') {
        continue;
    }
    $itemCounts[$name] = ($itemCounts[$name] ?? 0) + 1;
}
arsort($itemCounts);
$topMedicinesDispensed = [];
foreach (array_slice($itemCounts, 0, 5, true) as $label => $value) {
    $topMedicinesDispensed[] = ['label' => $label, 'value' => $value];
}
$topMedicinesDispensedMax = $topMedicinesDispensed !== [] ? max(array_column($topMedicinesDispensed, 'value')) : 1;
