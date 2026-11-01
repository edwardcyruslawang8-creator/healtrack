<?php
/**
 * =====================================================================
 * Manage Medicine / Manage Vaccine — Data (admin/manage_medicine.php)
 * ---------------------------------------------------------------------
 * Same real item + full batch history pharmacist's and sub-admin's own
 * manage_medicine_firestore.php files read (hc_get_inventory_item(),
 * hc_get_batches_for_medicine($id, true)) — a System Administrator sees
 * the exact same real batches, purely for monitoring. This page's own
 * admin/manage_medicine.php renders no Restock/Dispense/Archive/Edit/
 * Delete triggers at all (an admin has no write access to this
 * collection — same scope line drawn for every other admin/*.php
 * page), so this data file needs nothing beyond the item and its
 * batches.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_item()
require_once __DIR__ . '/../firestore/medicine_batches.php'; // hc_get_batches_for_medicine()

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'admin' => 'System Administrator',
        default => 'Administrator',
    },
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
