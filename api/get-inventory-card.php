<?php
/**
 * =====================================================================
 * Get Inventory Card (api/get-inventory-card.php)
 * ---------------------------------------------------------------------
 * Backs the Medicine Inventory / Vaccine Inventory cards' own real
 * category-filter dropdown on admin/facility_analytics.php AND
 * subadmin/facility_analytics.php (assets/js/facility_analytics.js) —
 * selecting a category updates
 * just that one card (pill counts, its low-stock/stock-overview list,
 * and its status-distribution donut) via fetch(), instead of a full
 * page reload. Inventory is system-wide in this app's schema (no
 * per-facility field — see includes/admin/facility_analytics_firestore.php's
 * header comment), so unlike api/get-patient-trend.php this doesn't
 * need a facility_id at all.
 *
 * Reuses the exact same hc_medicine_*() helpers (includes/firestore/medicines.php)
 * includes/admin/facility_analytics_firestore.php's own card computation
 * calls, so the full-page render and this lighter refresh can never
 * disagree.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php';

header('Content-Type: application/json');

// Only a signed-in admin or sub-admin (same gate the two facility_analytics.php
// pages themselves enforce). No facility lock needed here — inventory is
// system-wide, not scoped per facility (see includes/admin/facility_analytics_firestore.php's
// own header comment), so a subadmin sees the exact same real numbers an
// admin would for this same query.
if (!in_array($_SESSION['role'] ?? null, ['admin', 'subadmin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$type = $_GET['type'] ?? '';
if (!in_array($type, ['medicine', 'vaccine'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid item type.']);
    exit;
}

$allItems = hc_get_inventory_items($type);
$categories = hc_medicine_categories($allItems);

$category = $_GET['category'] ?? 'All';
if ($category !== 'All' && !in_array($category, $categories, true)) {
    $category = 'All';
}

$items = hc_medicine_filter_by_category($allItems, $category);
$inventory = hc_medicine_status_pills($items);
$stockDistribution = hc_medicine_stock_distribution($inventory);

// Medicine's list is "Top Low Stock" (low/out-of-stock only, ascending
// by stock); Vaccine's is "Stock Overview" (first 5, any status, in
// hc_get_inventory_items()'s own FEFO/expiry order) — same distinct
// behavior includes/admin/facility_analytics_firestore.php's own two
// card computations already keep, preserved here rather than unified
// into one generic list style.
if ($type === 'medicine') {
    $lowItems = array_values(array_filter($items, fn ($i) => $i['status'] !== 'available'));
    usort($lowItems, fn ($a, $b) => $a['currentStock'] <=> $b['currentStock']);
    $list = array_map(fn ($i) => ['name' => $i['name'], 'stock' => $i['currentStock']], array_slice($lowItems, 0, 5));
    $listLabel = 'Top Low Stock Medicines';
} else {
    $list = array_map(fn ($i) => ['name' => $i['name'], 'stock' => $i['currentStock']], array_slice($items, 0, 5));
    $listLabel = 'Vaccine Stock Overview';
}
$listMax = $list === [] ? 1 : (max(array_column($list, 'stock')) ?: 1);

echo json_encode([
    'success' => true,
    'inventory' => $inventory,
    'list' => $list,
    'listMax' => $listMax,
    'listLabel' => $listLabel,
    'stockDistribution' => $stockDistribution,
    'category' => $category,
]);