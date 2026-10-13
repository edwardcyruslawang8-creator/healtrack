<?php
/**
 * =====================================================================
 * Medicine & Vaccine Availability — Data
 * ---------------------------------------------------------------------
 * Both tabs read live from the same "medicines" Firestore collection
 * index.php's widget uses, split by a real "type" field —
 * firebase/firestore.php's getAllMedicines() (Medicines tab) and its
 * sibling getAllVaccines() (Vaccines tab). $medicineResults /
 * $vaccineResults are each the search/filter-applied, paginated result
 * for their own tab — every control on patient/medicine.php's toolbar
 * is real, not inert, on both tabs.
 *
 * Only the ACTIVE tab's collection is actually queried/filtered below
 * (not both on every request) — no need to pay for a Firestore read
 * the current page view won't use.
 * =====================================================================
 */

require_once __DIR__ . '/../../firebase/firestore.php';
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()

// Which pill tab is active — defaults to Medicines. Computed here (not
// patient/medicine.php) since it decides which collection this file
// even needs to query below.
$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'vaccines') ? 'vaccines' : 'medicines';

$medicineCategoryOptions     = ['All Categories', 'Analgesic', 'Antibiotic', 'Antihistamine', 'Respiratory', 'Electrolyte', 'Vitamin'];
$medicineFormOptions         = ['All Forms', 'Tablet', 'Capsule', 'Syrup', 'Sachet'];
$medicineAvailabilityOptions = ['All Availability', 'Available', 'Low Stock', 'Out of Stock'];

// Real vaccines have no useful "category" spread (every doc's category
// is just the literal string "Vaccine"), so the Vaccines tab has no
// category filter at all — only the two real Form values on file and
// the same 3-state availability every inventory item uses.
$vaccineFormOptions         = ['All Forms', 'Injection', 'Oral Drops'];
$vaccineAvailabilityOptions = $medicineAvailabilityOptions;

// Status key -> the availability filter option / display label it maps to.
$medicineStatusMeta = [
    'available'   => 'Available',
    'low'         => 'Low Stock',
    'unavailable' => 'Out of Stock',
];

// ---------------------------------------------------------------------
// Apply the search box (?q=) and filter dropdowns for real — category/
// form/availability all read here regardless of active tab (so the
// search bar's hidden fields, shared by both tabs, always have a real
// value to carry over), but only the active tab's own option list
// governs what actually filters below.
// ---------------------------------------------------------------------
$searchTerm = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category'] ?? 'All Categories';
$formFilter = $_GET['form'] ?? 'All Forms';
$availabilityFilter = $_GET['availability'] ?? 'All Availability';

if ($activeTab === 'medicines') {
    $allMedicines = getAllMedicines();

    $filteredMedicines = array_values(array_filter($allMedicines, function ($m) use ($searchTerm, $categoryFilter, $formFilter, $availabilityFilter, $medicineStatusMeta) {
        if ($searchTerm !== '' && !hc_fuzzy_match($m['name'] . ' ' . $m['desc'], $searchTerm)) {
            return false;
        }
        if ($categoryFilter !== 'All Categories' && $m['category'] !== $categoryFilter) {
            return false;
        }
        if ($formFilter !== 'All Forms' && $m['form'] !== $formFilter) {
            return false;
        }
        if ($availabilityFilter !== 'All Availability' && ($medicineStatusMeta[$m['status']] ?? '') !== $availabilityFilter) {
            return false;
        }
        return true;
    }));

    foreach ($filteredMedicines as &$medicine) {
        $medicine['statusLabel'] = $medicineStatusMeta[$medicine['status']] ?? ucfirst($medicine['status']);
    }
    unset($medicine);

    // Pagination — real total/page-count from the filtered result above.
    // The pagination links themselves still render as "#" placeholders
    // (see patient/medicine.php) rather than real ?page= links; with the
    // current catalog size everything fits on page 1 regardless.
    $medicineTotalResults   = count($filteredMedicines);
    $medicinePerPage        = 8;
    $medicineCurrentPageNum = max(1, (int) ($_GET['page'] ?? 1));
    $medicineTotalPages     = max(1, (int) ceil($medicineTotalResults / $medicinePerPage));
    $medicineCurrentPageNum = min($medicineCurrentPageNum, $medicineTotalPages);

    $medicineResults = array_slice($filteredMedicines, ($medicineCurrentPageNum - 1) * $medicinePerPage, $medicinePerPage);
} else {
    $allVaccines = getAllVaccines();

    $filteredVaccines = array_values(array_filter($allVaccines, function ($v) use ($searchTerm, $formFilter, $availabilityFilter, $medicineStatusMeta) {
        if ($searchTerm !== '' && !hc_fuzzy_match($v['name'] . ' ' . $v['desc'], $searchTerm)) {
            return false;
        }
        if ($formFilter !== 'All Forms' && $v['form'] !== $formFilter) {
            return false;
        }
        if ($availabilityFilter !== 'All Availability' && ($medicineStatusMeta[$v['status']] ?? '') !== $availabilityFilter) {
            return false;
        }
        return true;
    }));

    foreach ($filteredVaccines as &$vaccine) {
        $vaccine['statusLabel'] = $medicineStatusMeta[$vaccine['status']] ?? ucfirst($vaccine['status']);
    }
    unset($vaccine);

    $vaccineTotalResults   = count($filteredVaccines);
    $vaccinePerPage        = 8;
    $vaccineCurrentPageNum = max(1, (int) ($_GET['page'] ?? 1));
    $vaccineTotalPages     = max(1, (int) ceil($vaccineTotalResults / $vaccinePerPage));
    $vaccineCurrentPageNum = min($vaccineCurrentPageNum, $vaccineTotalPages);

    $vaccineResults = array_slice($filteredVaccines, ($vaccineCurrentPageNum - 1) * $vaccinePerPage, $vaccinePerPage);
}
