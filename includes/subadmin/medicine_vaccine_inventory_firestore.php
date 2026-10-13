<?php
/**
 * =====================================================================
 * Medicine & Vaccine Inventory — Data
 * ---------------------------------------------------------------------
 * Reads live from the real "medicines" Firestore collection — the same
 * collection index.php/patient/medicine.php read via
 * firebase/firestore.php's getAllMedicines(). This page instead queries
 * $firestore directly (the established subadmin/*.php convention — see
 * includes/user_management_firestore.php) since it needs the full field set
 * (currentStock/minLevel/reorderLevel/unit/expiryDate/lastUpdated)
 * rather than that helper's patient-facing shape.
 *
 * Those stock-tracking fields didn't exist on the original medicine/
 * announcement seed data — they were added to all 10 real documents
 * (see the scratchpad seed script referenced in this session) alongside
 * the pre-existing name/category/status fields. "status" (available/
 * low/unavailable) already existed and is reused here as the
 * in_stock/low_stock/out_of_stock signal rather than inventing a
 * parallel field. The "Medicines" tab filters to type==='medicine'
 * (same split as patient/medicine.php).
 *
 * The "Vaccines" tab is now real too — same shape as Medicines, kept in
 * a separate $_GET namespace (vq/vcategory/vstatus/vpage vs
 * q/category/status/page) so filtering one tab doesn't reset the
 * other, same convention pharmacist/medicine_vaccine_inventory.php's
 * data file already established.
 *
 * The category/status filters and search box now really filter
 * $medicines/$vaccines (they had no <form> around them before). "+ Add
 * Medicine", each row's Edit button, and the three-dot actions menu
 * (View Details/Adjust Stock/View Stock History/Archive/Delete) are
 * still not wired up — this pass connects the listing, not the write
 * side (same scope line drawn for User Management). Unlike the
 * pharmacist page, subadmin has no write endpoint access at all —
 * api/create-medicine.php and api/update-medicine.php are pharmacist-only.
 *
 * The actual stat-summary/search/filter/pagination computation below
 * now lives in includes/shared/medicine_vaccine_inventory_shared.php's
 * hc_compute_medicine_vaccine_inventory() — this file just resolves the
 * sub-admin's own profile/facility and hands off to it, so the same
 * logic isn't duplicated a second time for
 * includes/admin/medicine_vaccine_inventory_firestore.php. See that
 * shared file's own header comment for why no facility parameter is
 * needed (this collection isn't tracked per facility at all).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/medicine_vaccine_inventory_shared.php'; // hc_compute_medicine_vaccine_inventory(), hc_mvi_medicines_query(), hc_mvi_vaccines_query()

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
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// Summary cards, $medicines/$vaccines, filters, pagination — see
// includes/shared/medicine_vaccine_inventory_shared.php.
extract(hc_compute_medicine_vaccine_inventory());
