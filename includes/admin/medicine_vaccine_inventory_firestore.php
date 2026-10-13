<?php
/**
 * =====================================================================
 * Medicine & Vaccine Inventory (Admin) — Data
 * ---------------------------------------------------------------------
 * UNLIKE admin/patient_management.php, this page has no facility
 * picker: the real "medicines" Firestore collection has no facility
 * field at all — it's one shared, system-wide inventory, not tracked
 * per facility (confirmed by inspecting real seeded documents; every
 * medicine/vaccine has exactly one currentStock number, not one per
 * facility). So a System Administrator sees the exact same real
 * inventory subadmin/pharmacist already see — see
 * includes/shared/medicine_vaccine_inventory_shared.php's
 * hc_compute_medicine_vaccine_inventory(), the same real computation
 * includes/subadmin/medicine_vaccine_inventory_firestore.php calls.
 *
 * Read-only here — no Add/Edit/Adjust Stock/Delete actions. Those
 * endpoints (api/create-medicine.php, api/update-medicine.php,
 * api/delete-medicine.php, api/adjust-medicine-stock.php) are pharmacist/
 * subadmin-only; admin's role on this page is oversight, matching the
 * same read-only scope decision made for admin/patient_management.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/medicine_vaccine_inventory_shared.php'; // hc_compute_medicine_vaccine_inventory(), hc_mvi_medicines_query(), hc_mvi_vaccines_query()

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'admin' => 'System Administrator',
        default => 'Administrator',
    },
];

$dashboardDateLabel = date('F j, Y');

// Summary cards, $medicines/$vaccines, filters, pagination — see
// includes/shared/medicine_vaccine_inventory_shared.php.
extract(hc_compute_medicine_vaccine_inventory());
