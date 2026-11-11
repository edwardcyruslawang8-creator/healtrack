<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Medicine & Vaccine Availability Page (patient/medicine.php)
 * ---------------------------------------------------------------------
 * Both tabs read live from Firestore — includes/public/medicine-data.php
 * queries the same "medicines" collection index.php's widget uses,
 * split by a real "type" field (firebase/firestore.php's
 * getAllMedicines() / getAllVaccines()), and applies $_GET['q'] plus
 * each tab's own real filters. $activeTab is resolved in that data
 * file (it decides which collection even needs to be queried).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php';         // BASE_URL
require_once __DIR__ . '/../includes/public/data.php';             // $navItems (shared sidebar)
require_once __DIR__ . '/../includes/public/medicine-data.php';    // $activeTab, filter options, $medicineResults/$vaccineResults, pagination

// The keyword currently searched.
$queryTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

$pageTitle   = 'Medicine & Vaccine Availability';
$pageCss     = ['medicine.css', 'quick-search.css', 'styled-select.css'];
$pageScripts = ['styled-select.js', 'medicine.js', 'quick-search.js'];
require_once __DIR__ . '/../includes/shared/header.php';
require_once __DIR__ . '/../includes/shared/sidebar.php';
?>

<div class="main-wrapper">
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>Medicine &amp; Vaccine Availability</h1>
        <p>Search and view real-time availability of medicines and vaccines in the facility.</p>
    </div>

    <!-- SEARCH BAR -->
    <form class="search-page-bar" role="search" action="medicine.php" method="get">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
        <!-- Preserve the active filters (see includes/medicine-data.php)
             when searching, same as the filter toolbar below preserves
             the search term. -->
        <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
        <input type="hidden" name="form" value="<?= htmlspecialchars($formFilter) ?>">
        <div class="qs-field">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($queryTerm) ?>"
                   placeholder="Search medicine or vaccine (e.g., Paracetamol, Influenza Vaccine, etc.)"
                   autocomplete="off" data-quick-search="<?= BASE_URL ?>/api/quick-search-patient.php?scope=medicines">
        </div>
        <button type="submit">Search</button>
    </form>

    <!-- MEDICINES / VACCINES TABS -->
    <div class="med-tabs">
        <a href="medicine.php?tab=medicines" class="tab-btn <?= $activeTab === 'medicines' ? 'active' : '' ?>">
            <i class="bi bi-capsule"></i> Medicines
        </a>
        <a href="medicine.php?tab=vaccines" class="tab-btn <?= $activeTab === 'vaccines' ? 'active' : '' ?>">
            <i class="bi bi-droplet-half"></i> Vaccines
        </a>
    </div>

    <!-- MAIN PANEL -->
    <div class="medicine-panel">

        <?php if ($activeTab === 'medicines'): ?>

            <!-- FILTER TOOLBAR — category/form both really
                 filter $medicineResults now (see includes/medicine-data.php);
                 each select keeps its current value selected, and the
                 search term carries over via the hidden field below. -->
            <form class="filter-toolbar" id="medicineFilterToolbar" action="medicine.php" method="get">
                <input type="hidden" name="tab" value="medicines">
                <input type="hidden" name="q" value="<?= htmlspecialchars($queryTerm) ?>">
                <div class="filter-select-wrap">
                    <i class="bi bi-grid-fill leading-icon"></i>
                    <select data-styled-select class="filter-select" name="category">
                        <?php foreach ($medicineCategoryOptions as $opt): ?>
                        <option <?= $opt === $categoryFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="filter-select-wrap">
                    <i class="bi bi-file-earmark-text leading-icon"></i>
                    <select data-styled-select class="filter-select" name="form">
                        <?php foreach ($medicineFormOptions as $opt): ?>
                        <option <?= $opt === $formFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <button type="submit" class="btn-apply-filters-inline">Apply Filters</button>
                <a href="medicine.php?tab=medicines" class="clear-filters-link">
                    <i class="bi bi-arrow-counterclockwise"></i> Clear Filters
                </a>
            </form>

            <!-- RESULTS META -->
            <div class="medicine-meta-row">
                <span class="results-count">
                    Showing <?= $medicineTotalResults > 0 ? (($medicineCurrentPageNum - 1) * $medicinePerPage + 1) : 0 ?>&ndash;<?= min($medicineCurrentPageNum * $medicinePerPage, $medicineTotalResults) ?> of <?= $medicineTotalResults ?> medicines
                </span>
                <!-- form="medicineFilterToolbar" — submits the filter form
                     above (carrying category/form/q along with it) instead
                     of needing its own <form>; assets/js/medicine.js
                     submits it on change, same pattern as search.php's own
                     sort select (assets/js/search.js). Real sort:
                     includes/public/medicine-data.php. -->
                <select data-styled-select class="sort-select" name="sort" id="medicineSortSelect" form="medicineFilterToolbar">
                    <?php foreach ($medicineSortOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $value === $medicineSort ? 'selected' : '' ?>>Sort by: <?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- TABLE -->
            <div class="medicine-table-wrap">
                <table class="medicine-table">
                    <thead>
                        <tr>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Form</th>
                            <th>Availability</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicineResults as $m): ?>
                        <tr>
                            <td>
                                <div class="med-name-cell">
                                    <div class="med-row-icon <?= htmlspecialchars($m['color']) ?>"><i class="bi <?= htmlspecialchars($m['icon']) ?>"></i></div>
                                    <div>
                                        <div class="med-row-title"><?= htmlspecialchars($m['name']) ?></div>
                                        <div class="med-row-desc"><?= htmlspecialchars($m['desc']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Category"><?= htmlspecialchars($m['category']) ?></td>
                            <td data-label="Form"><?= htmlspecialchars($m['form']) ?></td>
                            <td data-label="Availability">
                                <?php if ($m['status'] !== 'unavailable'): ?>
                                <span class="status-badge available"><i class="bi bi-check-lg"></i> Available</span>
                                <?php else: ?>
                                <span class="status-badge unavailable"><i class="bi bi-x-lg"></i> Not Available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <nav class="pagination-custom" aria-label="Medicine list pages">
                <?php if ($medicineCurrentPageNum > 1): ?>
                <a href="<?= htmlspecialchars(hc_url_with_params(['page' => $medicineCurrentPageNum - 1])) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true" aria-label="Previous page"><i class="bi bi-chevron-left"></i></span>
                <?php endif; ?>
                <?php for ($p = 1; $p <= min(5, $medicineTotalPages); $p++): ?>
                <a href="<?= htmlspecialchars(hc_url_with_params(['page' => $p])) ?>" class="page-btn <?= $p === $medicineCurrentPageNum ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($medicineTotalPages > 6): ?>
                <span class="page-ellipsis">&hellip;</span>
                <?php endif; ?>
                <?php if ($medicineTotalPages > 5): ?>
                <a href="<?= htmlspecialchars(hc_url_with_params(['page' => $medicineTotalPages])) ?>" class="page-btn <?= $medicineTotalPages === $medicineCurrentPageNum ? 'active' : '' ?>"><?= $medicineTotalPages ?></a>
                <?php endif; ?>
                <?php if ($medicineCurrentPageNum < $medicineTotalPages): ?>
                <a href="<?= htmlspecialchars(hc_url_with_params(['page' => $medicineCurrentPageNum + 1])) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true" aria-label="Next page"><i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
            </nav>

        <?php else: ?>

            <!-- VACCINES TAB — real, same as Medicines above (see
                 includes/public/medicine-data.php). No category filter
                 here: every real vaccine doc's category is just the
                 literal string "Vaccine", not a useful filter axis. -->
            <form class="filter-toolbar" action="medicine.php" method="get">
                <input type="hidden" name="tab" value="vaccines">
                <input type="hidden" name="q" value="<?= htmlspecialchars($queryTerm) ?>">
                <div class="filter-select-wrap">
                    <i class="bi bi-file-earmark-text leading-icon"></i>
                    <select data-styled-select class="filter-select" name="form">
                        <?php foreach ($vaccineFormOptions as $opt): ?>
                        <option <?= $opt === $formFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <button type="submit" class="btn-apply-filters-inline">Apply Filters</button>
                <a href="medicine.php?tab=vaccines" class="clear-filters-link">
                    <i class="bi bi-arrow-counterclockwise"></i> Clear Filters
                </a>
            </form>

            <div class="medicine-meta-row">
                <span class="results-count">
                    Showing <?= $vaccineTotalResults > 0 ? (($vaccineCurrentPageNum - 1) * $vaccinePerPage + 1) : 0 ?>&ndash;<?= min($vaccineCurrentPageNum * $vaccinePerPage, $vaccineTotalResults) ?> of <?= $vaccineTotalResults ?> vaccines
                </span>
            </div>

            <div class="medicine-table-wrap">
                <table class="medicine-table">
                    <thead>
                        <tr>
                            <th>Vaccine Name</th>
                            <th>Form</th>
                            <th>Availability</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vaccineResults)): ?>
                        <tr>
                            <td colspan="3" style="text-align:center; padding:28px; color:var(--text-muted);">No vaccines match your search/filters.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($vaccineResults as $v): ?>
                        <tr>
                            <td>
                                <div class="med-name-cell">
                                    <div class="med-row-icon <?= htmlspecialchars($v['color']) ?>"><i class="bi <?= htmlspecialchars($v['icon']) ?>"></i></div>
                                    <div>
                                        <div class="med-row-title"><?= htmlspecialchars($v['name']) ?></div>
                                        <div class="med-row-desc"><?= htmlspecialchars($v['desc']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Form"><?= htmlspecialchars($v['form']) ?></td>
                            <td data-label="Availability">
                                <?php if ($v['status'] !== 'unavailable'): ?>
                                <span class="status-badge available"><i class="bi bi-check-lg"></i> Available</span>
                                <?php else: ?>
                                <span class="status-badge unavailable"><i class="bi bi-x-lg"></i> Not Available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <nav class="pagination-custom" aria-label="Vaccine list pages">
                <?php if ($vaccineCurrentPageNum > 1): ?>
                <a href="<?= htmlspecialchars(hc_url_with_params(['page' => $vaccineCurrentPageNum - 1])) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true" aria-label="Previous page"><i class="bi bi-chevron-left"></i></span>
                <?php endif; ?>
                <?php for ($p = 1; $p <= min(5, $vaccineTotalPages); $p++): ?>
                <a href="<?= htmlspecialchars(hc_url_with_params(['page' => $p])) ?>" class="page-btn <?= $p === $vaccineCurrentPageNum ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($vaccineTotalPages > 6): ?>
                <span class="page-ellipsis">&hellip;</span>
                <?php endif; ?>
                <?php if ($vaccineTotalPages > 5): ?>
                <a href="<?= htmlspecialchars(hc_url_with_params(['page' => $vaccineTotalPages])) ?>" class="page-btn <?= $vaccineTotalPages === $vaccineCurrentPageNum ? 'active' : '' ?>"><?= $vaccineTotalPages ?></a>
                <?php endif; ?>
                <?php if ($vaccineCurrentPageNum < $vaccineTotalPages): ?>
                <a href="<?= htmlspecialchars(hc_url_with_params(['page' => $vaccineCurrentPageNum + 1])) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true" aria-label="Next page"><i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
            </nav>

        <?php endif; ?>

    </div>

</main>
</div>

<?php require_once __DIR__ . '/../includes/shared/footer.php'; ?>
