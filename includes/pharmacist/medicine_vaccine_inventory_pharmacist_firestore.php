<?php
/**
 * =====================================================================
 * Medicine & Vaccine Inventory (Pharmacist) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — reads the real "medicines" Firestore collection via
 * includes/firestore/medicines.php (the same collection index.php/
 * patient/medicine.php and subadmin/medicine_vaccine_inventory.php
 * read). Both the Medicines tab AND the Vaccines tab are real here —
 * subadmin's version still leaves Vaccines as an inert placeholder,
 * but this page needed a real vaccine list too so "+ Add Vaccine"
 * has something real to add to. Search/category/status filters and
 * pagination are real for both tabs (kept in separate $_GET
 * namespaces — q/category/status/page for Medicines,
 * vq/vcategory/vstatus/vpage for Vaccines — so filtering one tab
 * doesn't reset the other). "+ Add Medicine"/"+ Add Vaccine" write
 * real documents via api/create-medicine.php; each row's Edit button
 * now writes real updates via api/update-medicine.php too (same
 * shared modal, pre-filled — see pharmacist/medicine_vaccine_inventory.php's
 * header comment). The three-dot actions menu (View Details/Adjust
 * Stock/View Stock History/Archive/Delete) is still not wired up.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php';
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Pharmacist',
    'role'     => 'Pharmacist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$medicineStatusMeta = hc_medicine_status_meta();
// Alias so the page template's lookup by the old placeholder keys
// (in_stock/low_stock/out_of_stock) still resolves either way.
$medicineStatusMeta['in_stock'] = $medicineStatusMeta['available'];
$medicineStatusMeta['low_stock'] = $medicineStatusMeta['low'];
$medicineStatusMeta['out_of_stock'] = $medicineStatusMeta['unavailable'];

$medicineStatusOptions = ['All Status', 'In Stock', 'Low Stock', 'Out of Stock'];
$statusLabelToKey = ['In Stock' => 'available', 'Low Stock' => 'low', 'Out of Stock' => 'unavailable'];

// Real expiry badge ("Expired"/"Expiring Soon") shown per row next to
// the Expiry Date column — see includes/firestore/medicines.php's
// hc_inventory_expiry_status()/hc_medicine_expiry_badge_meta() (each
// item from hc_get_inventory_items() below already carries its own
// real 'expiryStatus').
$medicineExpiryBadgeMeta = hc_medicine_expiry_badge_meta();

// ---------------------------------------------------------------------
// Medicines tab — real list, search, category/status filters, pagination.
// ---------------------------------------------------------------------
$allMedicines = hc_get_inventory_items('medicine');
$seenMedicineCategories = array_values(array_unique(array_column($allMedicines, 'category')));
sort($seenMedicineCategories);
$medicineCategoryOptions = array_merge(['All Categories'], $seenMedicineCategories);

$searchTerm = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category'] ?? 'All Categories';
$statusFilterLabel = $_GET['status'] ?? 'All Status';

$filteredMedicines = array_values(array_filter($allMedicines, function ($m) use ($searchTerm, $categoryFilter, $statusFilterLabel, $statusLabelToKey) {
    if ($searchTerm !== '' && !hc_fuzzy_match($m['name'] . ' ' . $m['category'], $searchTerm)) {
        return false;
    }
    if ($categoryFilter !== 'All Categories' && $m['category'] !== $categoryFilter) {
        return false;
    }
    if ($statusFilterLabel !== 'All Status' && $m['status'] !== ($statusLabelToKey[$statusFilterLabel] ?? '')) {
        return false;
    }
    return true;
}));

$medicinesTotal = count($filteredMedicines);
$medicinesPerPage = 8;
$medicinesCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$medicinesTotalPages = max(1, (int) ceil($medicinesTotal / $medicinesPerPage));
$medicinesCurrentPage = min($medicinesCurrentPage, $medicinesTotalPages);
$medicines = array_slice($filteredMedicines, ($medicinesCurrentPage - 1) * $medicinesPerPage, $medicinesPerPage);

$medicinePaginationWindow = [];
if ($medicinesTotalPages <= 6) {
    for ($p = 1; $p <= $medicinesTotalPages; $p++) {
        $medicinePaginationWindow[] = $p;
    }
} else {
    $medicinePaginationWindow = [1, 2, 3, 4, '...', $medicinesTotalPages];
}

// ---------------------------------------------------------------------
// Vaccines tab — same shape as Medicines, separate $_GET namespace.
// ---------------------------------------------------------------------
$allVaccines = hc_get_inventory_items('vaccine');
$seenVaccineCategories = array_values(array_unique(array_column($allVaccines, 'category')));
sort($seenVaccineCategories);
$vaccineCategoryOptions = array_merge(['All Categories'], $seenVaccineCategories);

$vaccineSearchTerm = trim($_GET['vq'] ?? '');
$vaccineCategoryFilter = $_GET['vcategory'] ?? 'All Categories';
$vaccineStatusFilterLabel = $_GET['vstatus'] ?? 'All Status';

$filteredVaccines = array_values(array_filter($allVaccines, function ($v) use ($vaccineSearchTerm, $vaccineCategoryFilter, $vaccineStatusFilterLabel, $statusLabelToKey) {
    if ($vaccineSearchTerm !== '' && !hc_fuzzy_match($v['name'] . ' ' . $v['category'], $vaccineSearchTerm)) {
        return false;
    }
    if ($vaccineCategoryFilter !== 'All Categories' && $v['category'] !== $vaccineCategoryFilter) {
        return false;
    }
    if ($vaccineStatusFilterLabel !== 'All Status' && $v['status'] !== ($statusLabelToKey[$vaccineStatusFilterLabel] ?? '')) {
        return false;
    }
    return true;
}));

$vaccinesTotal = count($filteredVaccines);
$vaccinesPerPage = 8;
$vaccinesCurrentPage = max(1, (int) ($_GET['vpage'] ?? 1));
$vaccinesTotalPages = max(1, (int) ceil($vaccinesTotal / $vaccinesPerPage));
$vaccinesCurrentPage = min($vaccinesCurrentPage, $vaccinesTotalPages);
$vaccines = array_slice($filteredVaccines, ($vaccinesCurrentPage - 1) * $vaccinesPerPage, $vaccinesPerPage);

$vaccinePaginationWindow = [];
if ($vaccinesTotalPages <= 6) {
    for ($p = 1; $p <= $vaccinesTotalPages; $p++) {
        $vaccinePaginationWindow[] = $p;
    }
} else {
    $vaccinePaginationWindow = [1, 2, 3, 4, '...', $vaccinesTotalPages];
}

// ---------------------------------------------------------------------
// Inventory Summary — real counts over medicines AND vaccines combined
// (matches the summary cards sitting above both tabs).
// ---------------------------------------------------------------------
$allInventory = array_merge($allMedicines, $allVaccines);
$inventorySummary = [
    'totalItems' => count($allInventory),
    'inStock' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'available')),
    'lowStock' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'low')),
    'outOfStock' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'unavailable')),
    'overstocked' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'overstocked')),
];
