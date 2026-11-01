<?php
/**
 * =====================================================================
 * Medicine & Vaccine Inventory — shared computation
 * ---------------------------------------------------------------------
 * hc_compute_medicine_vaccine_inventory() is the exact same real-data
 * pipeline includes/subadmin/medicine_vaccine_inventory_firestore.php
 * used to compute inline, extracted here so
 * includes/admin/medicine_vaccine_inventory_firestore.php can reuse it
 * instead of duplicating it a second time. Unlike Patient Management's
 * own shared computation, this one takes NO facility parameter — the
 * real "medicines" Firestore collection has no facility field at all
 * (it's one shared, system-wide inventory, not tracked per facility;
 * see admin/medicine_vaccine_inventory.php's own header comment for why
 * that page has no facility picker), so both callers see the exact same
 * real data.
 *
 * Both callers do `extract(hc_compute_medicine_vaccine_inventory())`
 * right after requiring this file — see
 * includes/shared/patient_management_shared.php's own header comment
 * for why extract() is safe/used here the same way.
 * =====================================================================
 */

require_once __DIR__ . '/fuzzy_search.php'; // hc_fuzzy_match()
require_once __DIR__ . '/../firestore/medicines.php'; // hc_inventory_expiry_status(), hc_medicine_expiry_badge_meta()

if (!function_exists('hc_compute_medicine_vaccine_inventory')) {
    function hc_compute_medicine_vaccine_inventory(): array
    {
        global $firestore;

        $result = [];

        // Category -> icon/color meta for the table's thumbnail. Keyed by
        // the real "category" values found in the medicines collection.
        $result['medicineCategoryMeta'] = [
            'Antibiotic'     => ['icon' => 'bi-capsule',  'bg' => '#FCE4E4', 'color' => '#D93025'],
            'Antihistamine'  => ['icon' => 'bi-capsule',  'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
            'Analgesic'      => ['icon' => 'bi-capsule',  'bg' => '#E8F0FE', 'color' => '#1A73E8'],
            'Electrolyte'    => ['icon' => 'bi-bag-fill', 'bg' => '#FDF3DC', 'color' => '#B7791F'],
            'Respiratory'    => ['icon' => 'bi-wind',     'bg' => '#E3F6FB', 'color' => '#12A6C6'],
            'Vitamin'        => ['icon' => 'bi-capsule',  'bg' => '#FDF3DC', 'color' => '#B7791F'],
        ];

        // Same idea as medicineCategoryMeta, for the real "category"
        // values found on type==='vaccine' documents.
        $result['vaccineCategoryMeta'] = [
            'Vaccine' => ['icon' => 'bi-droplet-half', 'bg' => '#E3F6FB', 'color' => '#12A6C6'],
        ];

        // Status key -> badge meta, reusing the real "status" field's values.
        $medicineStatusMeta = [
            'available'   => ['label' => 'In Stock',     'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
            'low'         => ['label' => 'Low Stock',    'bg' => '#FDECDD', 'color' => '#E8710A'],
            'unavailable' => ['label' => 'Out of Stock', 'bg' => '#FCE4E4', 'color' => '#D93025'],
            'overstocked' => ['label' => 'Overstocked',  'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
        ];
        $medicineStatusMeta['in_stock'] = $medicineStatusMeta['available'];
        $medicineStatusMeta['low_stock'] = $medicineStatusMeta['low'];
        $medicineStatusMeta['out_of_stock'] = $medicineStatusMeta['unavailable'];
        $result['medicineStatusMeta'] = $medicineStatusMeta;

        // Real expiry badge ("Expired"/"Expiring Soon") meta, keyed by
        // each row's own real 'expiryStatus' (set below in $formatDates).
        $result['medicineExpiryBadgeMeta'] = hc_medicine_expiry_badge_meta();

        // -------------------------------------------------------------
        // Real medicine + vaccine lists — every medicines/{id} doc, split
        // by its real "type" field.
        // -------------------------------------------------------------
        $allMedicines = [];
        $allVaccines = [];
        $seenCategories = [];
        $seenVaccineCategories = [];
        foreach ($firestore->listDocuments('medicines') as $doc) {
            $fields = $doc['fields'];
            // Archived (api/archive-medicine.php) — excluded from the
            // default inventory list, same as every other read path over
            // this collection (firebase/firestore.php's getAllMedicines()/
            // getAllVaccines(), includes/firestore/medicines.php's
            // hc_get_inventory_items()).
            if ($fields['archived'] ?? false) {
                continue;
            }
            $category = $fields['category'] ?? 'Uncategorized';

            $row = [
                'id' => $doc['id'],
                'name' => $fields['name'] ?? '(unnamed)',
                'category' => $category,
                'form' => $fields['form'] ?? '—',
                'unit' => $fields['unit'] ?? '—',
                'currentStock' => (int) ($fields['currentStock'] ?? 0),
                'minLevel' => (int) ($fields['minLevel'] ?? 0),
                'reorderLevel' => (int) ($fields['reorderLevel'] ?? 0),
                'maxLevel' => (int) ($fields['maxLevel'] ?? 0),
                'expiryDate' => $fields['expiryDate'] ?? '',
                'status' => $fields['status'] ?? 'available',
                'lastUpdated' => $fields['lastUpdated'] ?? '',
                'desc' => $fields['desc'] ?? '',
                'dosage' => $fields['dosage'] ?? '',
            ];

            if (($fields['type'] ?? 'medicine') === 'vaccine') {
                $seenVaccineCategories[$category] = true;
                $allVaccines[] = $row;
            } else {
                $seenCategories[$category] = true;
                $allMedicines[] = $row;
            }
        }

        // FEFO — First Expiry, First Out.
        $fefoSort = function ($a, $b) {
            $expiryA = $a['expiryDate'] !== '' ? $a['expiryDate'] : '9999-99-99';
            $expiryB = $b['expiryDate'] !== '' ? $b['expiryDate'] : '9999-99-99';

            return $expiryA <=> $expiryB ?: strcasecmp($a['name'], $b['name']);
        };
        usort($allMedicines, $fefoSort);
        usort($allVaccines, $fefoSort);

        $formatDates = function (&$row) {
            $row['expiryDateRaw'] = $row['expiryDate'];
            $row['expiryStatus'] = hc_inventory_expiry_status($row['expiryDate']);
            $row['expiryDate'] = $row['expiryDate'] !== '' ? strtoupper(date('M d, Y', strtotime($row['expiryDate']))) : '—';
            $row['lastUpdated'] = $row['lastUpdated'] !== '' ? date('M j, Y g:i A', strtotime($row['lastUpdated'])) : '—';
        };
        foreach ($allMedicines as &$m) {
            $formatDates($m);
        }
        unset($m);
        foreach ($allVaccines as &$v) {
            $formatDates($v);
        }
        unset($v);

        $result['medicineCategoryOptions'] = array_merge(['All Categories'], array_keys($seenCategories));
        $result['vaccineCategoryOptions'] = array_merge(['All Categories'], array_keys($seenVaccineCategories));
        $result['medicineStatusOptions'] = ['All Status', 'In Stock', 'Low Stock', 'Out of Stock', 'Overstocked'];

        // -------------------------------------------------------------
        // Inventory Summary — real counts over medicines AND vaccines.
        // -------------------------------------------------------------
        $allInventory = array_merge($allMedicines, $allVaccines);
        $result['inventorySummary'] = [
            'totalItems' => count($allInventory),
            'inStock' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'available')),
            'lowStock' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'low')),
            'outOfStock' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'unavailable')),
            'overstocked' => count(array_filter($allInventory, fn ($i) => $i['status'] === 'overstocked')),
        ];

        // -------------------------------------------------------------
        // Medicines tab — search box (?q=) + category/status filters.
        // -------------------------------------------------------------
        $searchTerm = trim($_GET['q'] ?? '');
        $categoryFilter = $_GET['category'] ?? 'All Categories';
        $statusFilterLabel = $_GET['status'] ?? 'All Status';
        $statusLabelToKey = ['In Stock' => 'available', 'Low Stock' => 'low', 'Out of Stock' => 'unavailable', 'Overstocked' => 'overstocked'];
        $result['searchTerm'] = $searchTerm;
        $result['categoryFilter'] = $categoryFilter;
        $result['statusFilterLabel'] = $statusFilterLabel;

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

        $result['medicinesTotal'] = $medicinesTotal;
        $result['medicinesPerPage'] = $medicinesPerPage;
        $result['medicinesCurrentPage'] = $medicinesCurrentPage;
        $result['medicinesTotalPages'] = $medicinesTotalPages;
        $result['medicines'] = array_slice($filteredMedicines, ($medicinesCurrentPage - 1) * $medicinesPerPage, $medicinesPerPage);

        $medicinePaginationWindow = [];
        if ($medicinesTotalPages <= 6) {
            for ($p = 1; $p <= $medicinesTotalPages; $p++) {
                $medicinePaginationWindow[] = $p;
            }
        } else {
            $medicinePaginationWindow = [1, 2, 3, 4, '...', $medicinesTotalPages];
        }
        $result['medicinePaginationWindow'] = $medicinePaginationWindow;

        // -------------------------------------------------------------
        // Vaccines tab — same shape as Medicines above, separate $_GET
        // namespace (vq/vcategory/vstatus/vpage) so filtering one tab
        // doesn't reset the other.
        // -------------------------------------------------------------
        $vaccineSearchTerm = trim($_GET['vq'] ?? '');
        $vaccineCategoryFilter = $_GET['vcategory'] ?? 'All Categories';
        $vaccineStatusFilterLabel = $_GET['vstatus'] ?? 'All Status';
        $result['vaccineSearchTerm'] = $vaccineSearchTerm;
        $result['vaccineCategoryFilter'] = $vaccineCategoryFilter;
        $result['vaccineStatusFilterLabel'] = $vaccineStatusFilterLabel;

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

        $result['vaccinesTotal'] = $vaccinesTotal;
        $result['vaccinesPerPage'] = $vaccinesPerPage;
        $result['vaccinesCurrentPage'] = $vaccinesCurrentPage;
        $result['vaccinesTotalPages'] = $vaccinesTotalPages;
        $result['vaccines'] = array_slice($filteredVaccines, ($vaccinesCurrentPage - 1) * $vaccinesPerPage, $vaccinesPerPage);

        $vaccinePaginationWindow = [];
        if ($vaccinesTotalPages <= 6) {
            for ($p = 1; $p <= $vaccinesTotalPages; $p++) {
                $vaccinePaginationWindow[] = $p;
            }
        } else {
            $vaccinePaginationWindow = [1, 2, 3, 4, '...', $vaccinesTotalPages];
        }
        $result['vaccinePaginationWindow'] = $vaccinePaginationWindow;

        return $result;
    }
}

if (!function_exists('hc_mvi_medicines_query')) {
    /** Builds a ?query string carrying the Medicines tab's current search/category/status filters, with $page overriding the page number — used by that tab's Previous/page-number/Next links so paging never drops the active filter. */
    function hc_mvi_medicines_query(int $page): string
    {
        global $searchTerm, $categoryFilter, $statusFilterLabel;

        return http_build_query(array_filter([
            'q' => $searchTerm,
            'category' => $categoryFilter,
            'status' => $statusFilterLabel,
            'page' => $page,
        ], fn ($v) => $v !== '' && $v !== null && $v !== 'All Categories' && $v !== 'All Status'));
    }
}

if (!function_exists('hc_mvi_vaccines_query')) {
    /** Same as hc_mvi_medicines_query() but for the Vaccines tab's own vq/vcategory/vstatus/vpage querystring namespace. */
    function hc_mvi_vaccines_query(int $page): string
    {
        global $vaccineSearchTerm, $vaccineCategoryFilter, $vaccineStatusFilterLabel;

        return http_build_query(array_filter([
            'vq' => $vaccineSearchTerm,
            'vcategory' => $vaccineCategoryFilter,
            'vstatus' => $vaccineStatusFilterLabel,
            'vpage' => $page,
        ], fn ($v) => $v !== '' && $v !== null && $v !== 'All Categories' && $v !== 'All Status'));
    }
}
