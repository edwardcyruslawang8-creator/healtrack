<?php
/**
 * =====================================================================
 * Get Stock Projection Detail (api/get-stock-projection.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/stock-projection.js whenever the "Projected
 * Stock Decline" card's dropdown selection or forecast-period select
 * changes — on admin/facility_analytics.php, subadmin/predictive_analytics.php,
 * AND pharmacist/analytics_reports.php (system-wide inventory, not
 * owned by one role). Returns one real inventory item's full detail
 * (summary indicators, chart data, stock status) via
 * includes/shared/predictive_analytics_core.php's own
 * hc_stock_projection_detail(), the SAME function each page's own
 * default render calls, so the numbers never drift between them.
 *
 * Resolved by real doc id (?id=), not name — two or more real
 * inventory docs can share the exact same display name (a real,
 * confirmed case in this system), so name alone can't reliably pick
 * one.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php'; // hc_get_inventory_items()
require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php'; // hc_get_all_pharmacy_order_docs()
require_once __DIR__ . '/../includes/shared/predictive_analytics_core.php'; // hc_stock_projection_detail()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['admin', 'subadmin', 'pharmacist'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing item id.']);
    exit;
}

$daysRaw = trim((string) ($_GET['days'] ?? '30'));
$days = in_array($daysRaw, ['7', '14', '30'], true) ? (int) $daysRaw : 30;

$allInventoryItems = array_merge(
    array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine')),
    array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'))
);

$item = null;
foreach ($allInventoryItems as $i) {
    if ($i['id'] === $id) {
        $item = $i;
        break;
    }
}
if ($item === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Item not found.']);
    exit;
}

$sharedName = count(array_filter($allInventoryItems, fn ($i) => $i['name'] === $item['name'])) > 1;
$pharmacyOrderDocs = hc_get_all_pharmacy_order_docs();
$detail = hc_stock_projection_detail($item, $pharmacyOrderDocs, $days, $sharedName);

echo json_encode(['success' => true, 'detail' => $detail]);
