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

// Usable vs. expired stock, computed live from the real batches on every
// page load. "Total Current Stock" shows only usable (non-expired) stock —
// the stored medicines/{id}.currentStock rollup sums EVERY batch and is
// only recomputed on a write, so a batch that simply passes its expiry
// date would otherwise keep counting. Expired stock is shown separately
// until it's archived (api/archive-medicine-batch.php).
$itemUsableStock = 0;
$itemExpiredStock = 0;
foreach ($itemBatches as $b) {
    if ($b['status']['key'] === 'expired') {
        $itemExpiredStock += $b['quantity'];
    } else {
        $itemUsableStock += $b['quantity'];
    }
}

// Whether to show the page-level "This item has expired and can no
// longer be dispensed" note — real per-BATCH expiry (hc_batch_status()'s
// own 'key'), not $item['expiryStatus'] (a single, largely vestigial
// expiryDate field on the medicine doc itself, left over from before
// multi-batch inventory existed — stale/misleading once an item has
// more than one batch with different real expiry dates, confirmed from
// a live screenshot: one expired batch made this note appear even
// though a second, still-valid batch had real dispensable stock).
// True only when there IS real stock somewhere but every batch holding
// it is expired — an item with simply no stock yet (nothing expired)
// correctly doesn't show an "expired" message at all.
$batchesWithStock = array_filter($itemBatches, fn($b) => $b['quantity'] > 0);
$itemAllStockExpired = $batchesWithStock !== [] && count(array_filter($batchesWithStock, fn($b) => $b['status']['key'] !== 'expired')) === 0;
