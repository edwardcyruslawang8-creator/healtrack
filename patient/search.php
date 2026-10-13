<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Search Page (patient/search.php)
 * ---------------------------------------------------------------------
 * Results/filters/pagination/Popular Searches are all real, backed by
 * Firestore — see includes/public/search-data.php's header comment.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php';        // BASE_URL
require_once __DIR__ . '/../includes/public/data.php';            // $navItems (shared sidebar)
require_once __DIR__ . '/../includes/public/search-data.php';     // $popularSearches, $filterCategories, $searchResults, etc.

// The keyword currently searched (from the homepage hero search, or this page's own bar).
$queryTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

$pageTitle   = 'Search';
$pageCss     = ['search.css', 'quick-search.css'];
$pageScripts = ['search.js', 'quick-search.js'];
require_once __DIR__ . '/../includes/shared/header.php';
require_once __DIR__ . '/../includes/shared/sidebar.php';
?>

<div class="main-wrapper">
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>Search</h1>
        <p>Search for services, medicines, vaccines, announcements, and more across the system.</p>
    </div>

    <!-- SEARCH BAR -->
    <form class="search-page-bar" role="search" action="search.php" method="get">
        <div class="qs-field">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($queryTerm) ?>"
                   placeholder="Search by keyword (e.g., Paracetamol, Consultation, Vaccine Schedule, Dengue, etc.)"
                   autocomplete="off" data-quick-search="<?= BASE_URL ?>/api/quick-search-patient.php?scope=all">
        </div>
        <button type="submit">Search</button>
    </form>

    <!-- POPULAR SEARCHES — real top searched terms (includes/shared/search_log.php);
         hidden entirely once there's nothing real to show yet, rather than a fake placeholder list. -->
    <?php if (!empty($popularSearches)): ?>
    <div class="popular-searches">
        <span class="popular-label">Popular Searches:</span>
        <?php foreach ($popularSearches as $term): ?>
        <a href="search.php?q=<?= urlencode($term) ?>" class="chip"><?= htmlspecialchars($term) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- FILTERS + RESULTS -->
    <div class="search-shell">

        <!-- FILTERS PANEL (~25%) -->
        <aside class="filters-panel">
            <div class="filters-header">
                <h2><i class="bi bi-funnel-fill"></i> Filters</h2>
                <a href="search.php" class="clear-all">Clear All</a>
            </div>

            <form id="filtersForm" action="search.php" method="get">
                <!-- Carries the current search term along with the filter
                     change — without this, auto-submitting on a checkbox
                     click would silently drop whatever was typed in the
                     search bar above (a separate <form>), since this one
                     otherwise has no "q" field of its own at all. -->
                <input type="hidden" name="q" value="<?= htmlspecialchars($queryTerm) ?>">
                <div class="filter-group-title">Category</div>
                <?php foreach ($filterCategories as $cat): ?>
                <div class="filter-checkbox-item">
                    <input type="checkbox" name="category[]" id="cat-<?= htmlspecialchars($cat['key']) ?>"
                           value="<?= htmlspecialchars($cat['key']) ?>" <?= $cat['checked'] ? 'checked' : '' ?>>
                    <span class="cat-icon <?= htmlspecialchars($cat['color']) ?>"><i class="bi <?= htmlspecialchars($cat['icon']) ?>"></i></span>
                    <label for="cat-<?= htmlspecialchars($cat['key']) ?>"><?= htmlspecialchars($cat['label']) ?></label>
                </div>
                <?php endforeach; ?>

                <div class="filter-group-title">Date Range</div>
                <div class="date-range-input">
                    <input type="text" name="date_range" id="dateRangeInput" placeholder="Select date range" readonly>
                    <i class="bi bi-calendar3"></i>
                </div>
            </form>
        </aside>

        <!-- RESULTS PANEL (~75%) -->
        <section class="results-panel">
            <div class="results-toolbar">
                <h2>Search Results</h2>
                <?php if ($queryTerm !== ''): ?>
                <span class="results-count">Showing 1&ndash;<?= count($searchResults) ?> of <?= $totalResults ?> results</span>
                <?php endif; ?>
                <select class="sort-select" name="sort">
                    <option>Sort by: Relevance</option>
                    <option>Sort by: Name (A&ndash;Z)</option>
                    <option>Sort by: Newest</option>
                </select>
            </div>

            <?php if ($queryTerm === ''): ?>
            <p class="results-empty">Type a keyword above (or pick a Popular Search) to search across medicines, vaccines, services, and announcements.</p>
            <?php elseif (empty($searchResults)): ?>
            <p class="results-empty">No results for "<?= htmlspecialchars($queryTerm) ?>".</p>
            <?php endif; ?>
            <?php foreach ($searchResults as $r): ?>
            <a href="<?= htmlspecialchars($r['href']) ?>" class="result-card">
                <div class="result-icon <?= htmlspecialchars($r['category'] === 'medicine' ? 'green' : ($r['category'] === 'service' ? 'blue' : ($r['category'] === 'vaccine' ? 'purple' : ($r['category'] === 'announcement' ? 'orange' : 'red')))) ?>">
                    <i class="bi <?= htmlspecialchars($r['icon']) ?>"></i>
                </div>
                <div class="result-info">
                    <div class="result-title"><?= htmlspecialchars($r['title']) ?></div>
                    <span class="result-category <?= htmlspecialchars($r['category'] === 'medicine' ? 'green' : ($r['category'] === 'service' ? 'blue' : ($r['category'] === 'vaccine' ? 'purple' : ($r['category'] === 'announcement' ? 'orange' : 'red')))) ?>"><?= htmlspecialchars($r['catLabel']) ?></span>
                    <p class="result-desc"><?= htmlspecialchars($r['desc']) ?></p>
                </div>
                <?php if ($r['status']): ?>
                <span class="status-badge result-status <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['statusLabel']) ?></span>
                <?php endif; ?>
                <i class="bi bi-chevron-right result-chevron"></i>
            </a>
            <?php endforeach; ?>

            <!-- PAGINATION -->
            <nav class="pagination-custom" aria-label="Search results pages">
                <a href="#" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php for ($p = 1; $p <= min(4, $totalPages); $p++): ?>
                <a href="#" class="page-btn <?= $p === $currentPageNum ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($totalPages > 5): ?>
                <span class="page-ellipsis">&hellip;</span>
                <a href="#" class="page-btn"><?= $totalPages ?></a>
                <?php endif; ?>
                <a href="#" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            </nav>
        </section>

    </div>

</main>
</div>

<?php require_once __DIR__ . '/../includes/shared/footer.php'; ?>
