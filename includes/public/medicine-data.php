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
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_url_with_params() — used by patient/medicine.php's pagination links

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

// Filter options built from the real values on file (sorted, blanks
// dropped) instead of a fixed list — the filters compare exactly, so a
// hardcoded "Vitamin" never matched a real "Vitamin & Supplement", and
// categories like "Antihypertensive" had no option at all.
if (!function_exists('hc_medicine_filter_options')) {
    function hc_medicine_filter_options(array $items, string $field, string $allLabel): array
    {
        $values = array_unique(array_filter(array_map(fn ($i) => trim((string) ($i[$field] ?? '')), $items), 'strlen'));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        return array_merge([$allLabel], $values);
    }
}

if ($activeTab === 'medicines') {
    $allMedicines = getAllMedicines();
    $medicineCategoryOptions = hc_medicine_filter_options($allMedicines, 'category', 'All Categories');
    $medicineFormOptions     = hc_medicine_filter_options($allMedicines, 'form', 'All Forms');

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

    // Real sort — the "Sort by: Name" dropdown (patient/medicine.php's
    // Medicines tab only). Applied before pagination slices the page out
    // below, same ordering every sort in this app follows (validate
    // against a real option list, default to the first one).
    $medicineSortOptions = ['name_asc' => 'Name (A–Z)', 'name_desc' => 'Name (Z–A)'];
    $medicineSort = isset($medicineSortOptions[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'name_asc';
    usort($filteredMedicines, fn ($a, $b) => $medicineSort === 'name_desc'
        ? strnatcasecmp($b['name'], $a['name'])
        : strnatcasecmp($a['name'], $b['name']));

    // Pagination — real total/page-count from the filtered result above.
    $medicineTotalResults   = count($filteredMedicines);
    $medicinePerPage        = 8;
    $medicineCurrentPageNum = max(1, (int) ($_GET['page'] ?? 1));
    $medicineTotalPages     = max(1, (int) ceil($medicineTotalResults / $medicinePerPage));
    $medicineCurrentPageNum = min($medicineCurrentPageNum, $medicineTotalPages);

    $medicineResults = array_slice($filteredMedicines, ($medicineCurrentPageNum - 1) * $medicinePerPage, $medicinePerPage);
} else {
    $allVaccines = getAllVaccines();
    $vaccineFormOptions = hc_medicine_filter_options($allVaccines, 'form', 'All Forms');

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
