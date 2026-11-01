<?php
/**
 * =====================================================================
 * Manage Medicine / Manage Vaccine — Data (subadmin/manage_medicine.php)
 * ---------------------------------------------------------------------
 * Same real data pharmacist's own includes/pharmacist/manage_medicine_firestore.php
 * reads (hc_get_inventory_item(), hc_get_batches_for_medicine($id, true) —
 * includeEmpty=true, the item's FULL batch history) — a sub-admin sees
 * the exact same real batches a pharmacist would, just from their own
 * page with its own sidebar/topbar and its own extra Edit/Delete
 * actions (subadmin/manage_medicine.php's own header comment).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_item()
require_once __DIR__ . '/../firestore/medicine_batches.php'; // hc_get_batches_for_medicine()

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
