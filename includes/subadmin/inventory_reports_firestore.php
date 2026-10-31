<?php
/**
 * =====================================================================
 * Inventory Reports — Placeholder Data
 * ---------------------------------------------------------------------
 * Static stand-ins for subadmin/inventory_reports.php, following the
 * same per-page "-data.php" convention as the rest of the app. This is
 * the "Inventory Reports" tab of the Analytics & Reports section — a
 * sibling page to subadmin/analytics_reports.php ("Overview") and
 * subadmin/patient_reports.php ("Patient Reports"), sharing their
 * $adminProfile/$facilityOptions/$analyticsTabs shape (see
 * includes/analytics_reports_firestore.php's header comment for why that
 * shape is duplicated per-page rather than shared).
 *
 * CONNECTED to real Firestore data. The stat cards (Total/In Stock/Low
 * Stock/Out of Stock/Expiring Soon Items), the Inventory Status
 * Overview donut, the Inventory by Category donut (Medicines vs.
 * Vaccines — the only two "categories" that actually exist as a type
 * split), and the Top 5 Lowest Stock Items table all read from the
 * real "medicines" collection (same source as
 * includes/medicine_vaccine_inventory_firestore.php).
 *
 * The filter toolbar (category / form / status) is real — "form" is a
 * real field on every "medicines" document (Tablet/Capsule/Syrup/
 * Injection/etc.); "category" maps to the real "type" field
 * (medicine/vaccine — the only two that actually exist, so the
 * Category filter only ever offers those two now); "status" maps to
 * the real "status" field plus the same real Expiring-Soon (≤30 days)
 * check the stat cards already use. The date-range dropdown (Today/
 * This Week/This Month/Custom Range) filters by each item's real
 * "lastUpdated" field. Every table on this page also has real
 * click-to-sort columns (assets/js/sortable-table.js) — inventory is
 * system-wide, not scoped per facility (see below), so this collection
 * isn't filtered by this sub-admin's own facility the way patients/
 * consultations/stock requests are.
 *
 * Stock Movement Summary (Opening/Received/Issued/Closing) and the
 * Inventory Expiry Monitor (real per-batch expiry, replacing the old
 * per-medicine-item expiryDate check) are now both real too — see
 * includes/shared/inventory_movement_monitor.php's own header comment
 * for exactly what each figure is computed from (and, for Received/
 * Issued, what's honestly left uncounted rather than guessed at).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/query_helpers.php';
require_once __DIR__ . '/../shared/date_range_filter.php';
require_once __DIR__ . '/../shared/inventory_movement_monitor.php'; // hc_compute_stock_movement_summary(), hc_compute_inventory_expiry_monitor()

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

// This page's topbar date control is a range (not a single day) — same
// label shown on analytics_reports.php / patient_reports.php for
// continuity between the tabs.
$analyticsDateRangeLabel = date('F j, Y'); // real, up-to-date single date (was a frozen placeholder date)
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Analytics tabs — each is its own page (not client-side tab-switching).
// Inventory Reports stays active (this IS that page).
// ---------------------------------------------------------------------
$analyticsTabs = [
    ['key' => 'overview',              'label' => 'Overview',              'href' => 'analytics_reports.php'],
    ['key' => 'patient_reports',       'label' => 'Patient Reports',       'href' => 'patient_reports.php'],
    ['key' => 'inventory_reports',     'label' => 'Inventory Reports',     'href' => 'inventory_reports.php', 'active' => true],
    ['key' => 'stock_request_reports', 'label' => 'Stock Request Reports', 'href' => 'stock_request_reports.php'],
    ['key' => 'consultation_reports',  'label' => 'Consultation Reports',  'href' => 'consultation_reports.php'],
    ['key' => 'vaccine_reports',       'label' => 'Vaccine Reports',       'href' => 'vaccine_reports.php'],
    ['key' => 'lab_xray_reports',      'label' => 'Lab & X-ray Reports',   'href' => 'lab_xray_reports.php'],
    ['key' => 'predictive_analytics',  'label' => 'Predictive & Prescriptive', 'href' => 'predictive_analytics.php'],
];

// ---------------------------------------------------------------------
// Filter toolbar dropdown options — all real now (see header comment).
// ---------------------------------------------------------------------
$categoryFilterOptions = ['All Categories', 'Medicines', 'Vaccines'];
$formFilterOptions = ['All Forms', 'Tablet', 'Capsule', 'Syrup', 'Injection', 'Inhaler'];
$statusFilterOptions = ['All Status', 'In Stock', 'Low Stock', 'Out of Stock', 'Expiring Soon'];

$irDateFilter = hc_resolve_date_range_filter();
$irCategoryFilter = (string) ($_GET['category'] ?? '');
$irFormFilter = (string) ($_GET['form'] ?? '');
$irStatusFilter = (string) ($_GET['status'] ?? '');

$irCategoryToType = ['Medicines' => 'medicine', 'Vaccines' => 'vaccine'];
$irStatusToReal = ['In Stock' => 'available', 'Low Stock' => 'low', 'Out of Stock' => 'unavailable'];

// ---------------------------------------------------------------------
// Real medicines, then this page's own real filters applied on top —
// used by every block below.
// ---------------------------------------------------------------------
$expiringSoonThreshold = strtotime('+30 days');
$allInventoryItems = [];
foreach ($firestore->listDocuments('medicines') as $doc) {
    $fields = $doc['fields'];

    if (!hc_date_in_range($fields['lastUpdated'] ?? null, $irDateFilter)) {
        continue;
    }
    if ($irCategoryFilter !== '' && ($fields['type'] ?? 'medicine') !== ($irCategoryToType[$irCategoryFilter] ?? '__none__')) {
        continue;
    }
    if ($irFormFilter !== '' && ($fields['form'] ?? '') !== $irFormFilter) {
        continue;
    }
    if ($irStatusFilter !== '' && $irStatusFilter !== 'Expiring Soon' && ($fields['status'] ?? 'available') !== ($irStatusToReal[$irStatusFilter] ?? '__none__')) {
        continue;
    }
    $itemExpiryRaw = $fields['expiryDate'] ?? null;
    $itemExpiringSoon = $itemExpiryRaw && strtotime($itemExpiryRaw) <= $expiringSoonThreshold;
    if ($irStatusFilter === 'Expiring Soon' && !$itemExpiringSoon) {
        continue;
    }

    $allInventoryItems[] = [
        'name' => $fields['name'] ?? '(unnamed)',
        'category' => $fields['category'] ?? 'Uncategorized',
        'type' => $fields['type'] ?? 'medicine',
        'status' => $fields['status'] ?? 'available',
        'currentStock' => (int) ($fields['currentStock'] ?? 0),
        'expiryDateRaw' => $itemExpiryRaw,
    ];
}
$inventoryTotalCount = count($allInventoryItems);

$inStockCount = count(array_filter($allInventoryItems, fn ($m) => $m['status'] === 'available'));
$lowStockCount = count(array_filter($allInventoryItems, fn ($m) => $m['status'] === 'low'));
$outOfStockCount = count(array_filter($allInventoryItems, fn ($m) => $m['status'] === 'unavailable'));
$overstockedCount = count(array_filter($allInventoryItems, fn ($m) => $m['status'] === 'overstocked'));
$expiringSoonCount = count(array_filter($allInventoryItems, fn ($m) => $m['expiryDateRaw'] && strtotime($m['expiryDateRaw']) <= $expiringSoonThreshold));

$pct = fn (int $n) => $inventoryTotalCount > 0 ? round($n / $inventoryTotalCount * 100, 1) : 0;

// ---------------------------------------------------------------------
// Summary stat cards — real.
// ---------------------------------------------------------------------
$inventoryStatCards = [
    ['icon' => 'bi-boxes',                     'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Total Items',                'value' => $inventoryTotalCount, 'sub' => '100% of total items'],
    ['icon' => 'bi-box-seam-fill',              'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'In Stock Items',             'value' => $inStockCount,  'sub' => $pct($inStockCount) . '% of total items'],
    ['icon' => 'bi-exclamation-triangle-fill',  'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Low Stock Items',            'value' => $lowStockCount, 'sub' => $pct($lowStockCount) . '% of total items'],
    ['icon' => 'bi-box-fill',                   'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Out of Stock Items',         'value' => $outOfStockCount, 'sub' => $pct($outOfStockCount) . '% of total items'],
    ['icon' => 'bi-box-seam',                   'iconBg' => '#E3F6FB', 'iconColor' => '#12A6C6', 'label' => 'Overstocked Items',          'value' => $overstockedCount, 'sub' => $pct($overstockedCount) . '% of total items'],
    ['icon' => 'bi-clock-history',               'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'label' => 'Expiring Soon (≤30 days)', 'value' => $expiringSoonCount, 'sub' => $pct($expiringSoonCount) . '% of total items'],
];

// ---------------------------------------------------------------------
// Inventory Status Overview (doughnut) — real. Note: an item can be
// both e.g. Low Stock AND Expiring Soon, so these 4 values can overlap
// rather than partition the total (same non-mutually-exclusive shape
// as this page's original placeholder design).
// ---------------------------------------------------------------------
$inventoryStatusOverview = [
    ['label' => 'In Stock',                'value' => $inStockCount,     'pct' => $pct($inStockCount),     'color' => '#1E8E3E'],
    ['label' => 'Low Stock',                'value' => $lowStockCount,    'pct' => $pct($lowStockCount),    'color' => '#E8710A'],
    ['label' => 'Out of Stock',             'value' => $outOfStockCount,  'pct' => $pct($outOfStockCount),  'color' => '#D93025'],
    ['label' => 'Overstocked',              'value' => $overstockedCount, 'pct' => $pct($overstockedCount), 'color' => '#12A6C6'],
    ['label' => 'Expiring Soon (≤30 days)', 'value' => $expiringSoonCount,'pct' => $pct($expiringSoonCount),'color' => '#8B5CF6'],
];
$inventoryStatusOverviewTotal = $inventoryTotalCount ?: 1;

// ---------------------------------------------------------------------
// Inventory by Category (doughnut) — real; Medicines/Vaccines are the
// only two categories that actually exist as a type split in the
// "medicines" collection.
// ---------------------------------------------------------------------
$categoryMedicineCount = count(array_filter($allInventoryItems, fn ($m) => $m['type'] === 'medicine'));
$categoryVaccineCount = count(array_filter($allInventoryItems, fn ($m) => $m['type'] === 'vaccine'));
$inventoryByCategory = [
    ['label' => 'Medicines', 'value' => $categoryMedicineCount, 'pct' => $pct($categoryMedicineCount), 'color' => '#1A73E8'],
    ['label' => 'Vaccines',  'value' => $categoryVaccineCount,  'pct' => $pct($categoryVaccineCount),  'color' => '#1E8E3E'],
];
$inventoryByCategoryTotal = $inventoryTotalCount ?: 1;

// ---------------------------------------------------------------------
// Top 5 Items with Lowest Stock — real, sorted ascending by currentStock.
// ---------------------------------------------------------------------
$sortedByStock = $allInventoryItems;
usort($sortedByStock, fn ($a, $b) => $a['currentStock'] <=> $b['currentStock']);
$lowestStockItems = array_map(fn ($m) => [
    'name' => $m['name'],
    'category' => $m['type'] === 'vaccine' ? 'Vaccine' : 'Medicine',
    'currentStock' => $m['currentStock'],
    'status' => $m['status'] === 'unavailable' ? 'out_of_stock' : 'low_stock',
], array_slice($sortedByStock, 0, 5));

$stockStatusMeta = [
    'low_stock'    => ['label' => 'Low Stock',    'bg' => '#FDECDD', 'color' => '#E8710A'],
    'out_of_stock' => ['label' => 'Out of Stock', 'bg' => '#FCE4E4', 'color' => '#D93025'],
];

// ---------------------------------------------------------------------
// Stock Movement Summary — real now. Default window is the last 30
// real days; the card's own date-range filter (7/30/90/custom) re-
// fetches via api/get-stock-movement.php, same real
// hc_compute_stock_movement_summary() either way — see that function's
// own header comment for exactly what's real vs. algebraically derived
// (never fabricated) in Opening/Received/Issued/Closing.
// ---------------------------------------------------------------------
$irMovementDefaultDays = 30;
$irMovementStart = date('Y-m-d', strtotime("-{$irMovementDefaultDays} days"));
$irMovementEnd = date('Y-m-d');
$irStockMovement = hc_compute_stock_movement_summary($irMovementStart, $irMovementEnd);
$stockMovementSummary = $irStockMovement['byCategory'];
$stockMovementTotal = $irStockMovement['total'];

// ---------------------------------------------------------------------
// Inventory Expiry Monitor (real per-batch expiry — see
// hc_compute_inventory_expiry_monitor()'s own header comment for why
// this is per real medicine_batches BATCH, not just each medicine's own
// single expiryDate field). The card shows only the first 5 "relevant"
// (expired or expiring within 30 days) rows; the full real list (every
// batch, every status, including "ok"/no-expiry ones) backs its own
// "View All Items" modal.
// ---------------------------------------------------------------------
$irExpiryMonitor = hc_compute_inventory_expiry_monitor();
$irExpiryAllItems = $irExpiryMonitor['items'];
$irExpiryExpiredCount = $irExpiryMonitor['expiredCount'];
$irExpiryExpiringSoonCount = $irExpiryMonitor['expiringSoonCount'];
$irExpiryTop5 = array_slice(array_values(array_filter(
    $irExpiryAllItems,
    fn ($r) => in_array($r['status'], ['expired', 'today', 'expiring_soon'], true)
)), 0, 5);
