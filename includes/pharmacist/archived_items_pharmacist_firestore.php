<?php
/**
 * =====================================================================
 * Archived Items (Pharmacist) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — reads the real "medicines" Firestore collection via
 * includes/firestore/medicines.php's hc_get_archived_inventory_items(),
 * the mirror image of hc_get_inventory_items() (which excludes
 * archived items from every other read path). This is the one place
 * a pharmacist can actually see what's been archived
 * (api/archive-medicine.php) — same Medicines/Vaccines tab shape,
 * search, category filter, and pagination as
 * pharmacist/medicine_vaccine_inventory.php, kept in separate $_GET
 * namespaces (q/category/page for Medicines, vq/vcategory/vpage for
 * Vaccines) for the same reason that page does. No Status filter here
 * — an archived item's in-stock/low/out status isn't the point of
 * this page, whether it's still archived or not is.
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
$medicineExpiryBadgeMeta = hc_medicine_expiry_badge_meta();

// ---------------------------------------------------------------------
// Medicines tab — real archived list, search, category filter, pagination.
// ---------------------------------------------------------------------
$allArchivedMedicines = hc_get_archived_inventory_items('medicine');
$seenMedicineCategories = array_values(array_unique(array_column($allArchivedMedicines, 'category')));
sort($seenMedicineCategories);
$medicineCategoryOptions = array_merge(['All Categories'], $seenMedicineCategories);

$searchTerm = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category'] ?? 'All Categories';

$filteredMedicines = array_values(array_filter($allArchivedMedicines, function ($m) use ($searchTerm, $categoryFilter) {
    if ($searchTerm !== '' && !hc_fuzzy_match($m['name'] . ' ' . $m['category'], $searchTerm)) {
        return false;
    }
    if ($categoryFilter !== 'All Categories' && $m['category'] !== $categoryFilter) {
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
$allArchivedVaccines = hc_get_archived_inventory_items('vaccine');
$seenVaccineCategories = array_values(array_unique(array_column($allArchivedVaccines, 'category')));
sort($seenVaccineCategories);
$vaccineCategoryOptions = array_merge(['All Categories'], $seenVaccineCategories);

$vaccineSearchTerm = trim($_GET['vq'] ?? '');
$vaccineCategoryFilter = $_GET['vcategory'] ?? 'All Categories';

$filteredVaccines = array_values(array_filter($allArchivedVaccines, function ($v) use ($vaccineSearchTerm, $vaccineCategoryFilter) {
    if ($vaccineSearchTerm !== '' && !hc_fuzzy_match($v['name'] . ' ' . $v['category'], $vaccineSearchTerm)) {
        return false;
    }
    if ($vaccineCategoryFilter !== 'All Categories' && $v['category'] !== $vaccineCategoryFilter) {
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
// Summary — real counts over archived medicines AND vaccines combined.
// ---------------------------------------------------------------------
$archivedSummary = [
    'totalArchived' => count($allArchivedMedicines) + count($allArchivedVaccines),
    'archivedMedicines' => count($allArchivedMedicines),
    'archivedVaccines' => count($allArchivedVaccines),
];
