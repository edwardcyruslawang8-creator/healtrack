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
require_once __DIR__ . '/../shared/analytics_comparison.php'; // hc_facility_growth_pct()

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

// Real "operational issue" comparative/anomaly view (hc_operational_trends_overview())
// over MY OWN stock requests — same mean/standard-deviation anomaly
// method the disease/consultation-volume checks already use, applied
// to real request volume + real processing time (request -> decision).
$operationalTrends = hc_operational_trends_overview($myRequests);

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

// Real "vs last month" on "Items I Dispensed" — the one KPI card here
// with a natural monthly reading (real dispensedDate per item, see
// api/dispense-pharmacy-order.php) — same hc_facility_growth_pct()
// helper every other role's own copy of this now uses.
$dispensedThisMonthPrefix = date('Y-m');
$dispensedLastMonthPrefix = date('Y-m', strtotime('first day of last month'));
$dispensedThisMonth = count(array_filter($myDispensed, fn ($f) => str_starts_with($f['dispensedDate'] ?? '', $dispensedThisMonthPrefix)));
$dispensedLastMonth = count(array_filter($myDispensed, fn ($f) => str_starts_with($f['dispensedDate'] ?? '', $dispensedLastMonthPrefix)));
$dispensedGrowthPct = hc_facility_growth_pct($dispensedThisMonth, $dispensedLastMonth);

$kpiCards = [
    ['icon' => 'bi-box-seam-fill',   'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'My Stock Requests',  'value' => count($myRequests),        'sub' => 'All-time',  'trend' => 'up'],
    ['icon' => 'bi-hourglass-split', 'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Pending Requests',   'value' => $pendingRequestsCount,     'sub' => 'Awaiting',  'trend' => 'up'],
    ['icon' => 'bi-capsule',         'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Items I Dispensed', 'value' => count($myDispensed),       'sub' => abs($dispensedGrowthPct) . '% vs last month', 'trend' => $dispensedGrowthPct < 0 ? 'down' : 'up'],
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

// ---------------------------------------------------------------------
// Stock Movement Summary + Inventory Expiry Monitor — real, same as
// subadmin/inventory_reports.php's own cards (facility-wide inventory
// data, not owned by one pharmacist — same reasoning as Inventory
// Status Summary above). See
// includes/shared/inventory_movement_monitor.php's own header comment.
// ---------------------------------------------------------------------
require_once __DIR__ . '/../shared/inventory_movement_monitor.php';

$irMovementDefaultDays = 30;
$irMovementStart = date('Y-m-d', strtotime("-{$irMovementDefaultDays} days"));
$irMovementEnd = date('Y-m-d');
$irStockMovement = hc_compute_stock_movement_summary($irMovementStart, $irMovementEnd);
$stockMovementSummary = $irStockMovement['byCategory'];
$stockMovementTotal = $irStockMovement['total'];

$irExpiryMonitor = hc_compute_inventory_expiry_monitor();
$irExpiryAllItems = $irExpiryMonitor['items'];
$irExpiryExpiredCount = $irExpiryMonitor['expiredCount'];
$irExpiryExpiringSoonCount = $irExpiryMonitor['expiringSoonCount'];
$irExpiryTop5 = array_slice(array_values(array_filter(
    $irExpiryAllItems,
    fn ($r) => in_array($r['status'], ['expired', 'today', 'expiring_soon'], true)
)), 0, 5);

// ---------------------------------------------------------------------
// Projected Stock Decline — the same full, interactive per-item card as
// admin/facility_analytics.php's and subadmin/predictive_analytics.php's
// own Predictive & Prescriptive tab (searchable dropdown over every
// real item, 7/14/30-day period, real summary indicators, chart,
// recommendation) — see includes/admin/facility_analytics_firestore.php's
// own copy of this exact block for the full reasoning (duplicated here
// rather than shared, since that file assumes a $facility_id query
// param + a different Firestore read path this role's page doesn't
// have). System-wide, same as everything else inventory-related on
// this page.
// ---------------------------------------------------------------------
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // hc_stock_projection_detail()

$pt2AllInventoryItemsForProjection = array_merge(
    array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine')),
    array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'))
);
$pt2AllPharmacyOrderDocsForProjection = hc_get_all_pharmacy_order_docs();

$pt2ItemList = $pt2AllInventoryItemsForProjection;
usort($pt2ItemList, fn ($a, $b) => strcasecmp($a['name'], $b['name']) ?: $a['currentStock'] <=> $b['currentStock']);
$pt2ItemNameCounts = array_count_values(array_column($pt2ItemList, 'name'));
$pt2ItemList = array_map(function ($i) use ($pt2ItemNameCounts) {
    return [
        'id' => $i['id'],
        'name' => $i['name'],
        'itemType' => $i['itemType'],
        'label' => $pt2ItemNameCounts[$i['name']] > 1 ? ($i['name'] . ' — ' . number_format($i['currentStock']) . ' in stock') : $i['name'],
    ];
}, $pt2ItemList);

$pt2DefaultItemId = null;
foreach (['out_of_stock', 'critical', 'low'] as $priorityStatus) {
    foreach ($pt2AllInventoryItemsForProjection as $i) {
        $sharedName = $pt2ItemNameCounts[$i['name']] > 1;
        $probe = hc_stock_projection_detail($i, $pt2AllPharmacyOrderDocsForProjection, 30, $sharedName);
        if ($probe['stockStatus'] === $priorityStatus) {
            $pt2DefaultItemId = $i['id'];
            break 2;
        }
    }
}
if ($pt2DefaultItemId === null && $pt2ItemList !== []) {
    $pt2DefaultItemId = $pt2ItemList[0]['id'];
}
$pt2DefaultDetail = null;
if ($pt2DefaultItemId !== null) {
    foreach ($pt2AllInventoryItemsForProjection as $i) {
        if ($i['id'] === $pt2DefaultItemId) {
            $pt2DefaultDetail = hc_stock_projection_detail($i, $pt2AllPharmacyOrderDocsForProjection, 30, $pt2ItemNameCounts[$i['name']] > 1);
            break;
        }
    }
}
