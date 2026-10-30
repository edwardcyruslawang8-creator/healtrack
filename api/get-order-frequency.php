<?php
/**
 * =====================================================================
 * Get Order/Prescription Frequency (api/get-order-frequency.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/order-frequency.js whenever the "Order/
 * Prescription Frequency" card's date-range dropdown changes (category
 * switches and the "View All Items" modal's search/sort/pagination stay
 * entirely client-side against whatever this endpoint last returned —
 * no extra round trip for those).
 *
 * Same real data sources and matching logic as
 * includes/shared/predictive_analytics_core.php's own
 * hc_medicine_vaccine_shortage() (system-wide, since medicine/vaccine
 * inventory has no per-facility field in this app's schema — see that
 * function's own header comment) — hc_order_frequency_breakdown() is
 * the shared helper both this endpoint and that function call, so the
 * numbers never drift between the two.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/medicines.php'; // hc_get_inventory_items()
require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php'; // hc_get_all_pharmacy_order_docs()
require_once __DIR__ . '/../includes/shared/predictive_analytics_core.php'; // hc_order_frequency_breakdown()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['admin', 'subadmin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

$daysRaw = trim((string) ($_GET['days'] ?? '30'));
$days = in_array($daysRaw, ['7', '30', '90'], true) ? (int) $daysRaw : null; // anything else (incl. "0"/"all") = all-time

$allInventoryItems = array_merge(
    array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine')),
    array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'))
);
$pharmacyOrderDocs = hc_get_all_pharmacy_order_docs();
$medicationDocs = $firestore->listDocuments('medications');

$items = hc_order_frequency_breakdown($allInventoryItems, $pharmacyOrderDocs, $medicationDocs, $days);

$totalRequests = array_sum(array_column($items, 'total'));
$itemsRequested = count(array_filter($items, fn ($i) => $i['total'] > 0));

echo json_encode([
    'success' => true,
    'items' => $items,
    'totalRequests' => $totalRequests,
    'itemsRequested' => $itemsRequested,
    'totalItems' => count($items),
]);
