<?php
/**
 * =====================================================================
 * Manage Medicine / Manage Vaccine — Data (pharmacist/manage_medicine.php)
 * ---------------------------------------------------------------------
 * The detailed batch-level view a "Manage Medicine"/"Manage Vaccine"
 * link on pharmacist/medicine_vaccine_inventory.php's own table now
 * navigates to, instead of opening a modal over that list. One real
 * medicines/{id} doc (hc_get_inventory_item(), includes/firestore/medicines.php)
 * plus every real batch it has ever had
 * (hc_get_batches_for_medicine($id, true) — includeEmpty=true, since
 * this page is meant to show the item's FULL batch history, not just
 * what's currently on the shelf; a fully-depleted batch still shows
 * its own row here, correctly flagged "Out of Stock").
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_item()
require_once __DIR__ . '/../firestore/medicine_batches.php'; // hc_get_batches_for_medicine()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Pharmacist',
    'role'     => 'Pharmacist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

$medicineStatusMeta = hc_medicine_status_meta();
$medicineExpiryBadgeMeta = hc_medicine_expiry_badge_meta();

$itemId = trim((string) ($_GET['id'] ?? ''));
$requestedType = ($_GET['type'] ?? 'medicine') === 'vaccine' ? 'vaccine' : 'medicine';

$item = $itemId !== '' ? hc_get_inventory_item($itemId) : null;
if ($item === null || $item['type'] !== $requestedType || $item['archived']) {
    header('Location: medicine_vaccine_inventory.php?notFound=1');
    exit;
}

$itemBatches = hc_get_batches_for_medicine($itemId, true);

// Same real counts the main inventory page's own summary cards show
// (pharmacist/medicine_vaccine_inventory.php) — kept here too so this
// detail page's shell matches it exactly rather than dropping the row.
$allInventory = array_merge(hc_get_inventory_items('medicine'), hc_get_inventory_items('vaccine'));
$inventorySummary = [
    'totalItems' => count($allInventory),
    'inStock' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'available')),
    'lowStock' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'low')),
    'outOfStock' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'unavailable')),
    'overstocked' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'overstocked')),
];
