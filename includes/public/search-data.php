<?php
/**
 * =====================================================================
 * Search Page — Data
 * ---------------------------------------------------------------------
 * CONNECTED for the two categories this app actually has real
 * Firestore data for — Medicines (firebase/firestore.php's
 * getAllMedicines()) and Announcements (getAllAnnouncements()) —
 * fuzzy-matched (includes/shared/fuzzy_search.php's hc_fuzzy_match())
 * against $_GET['q'], same matching every other real search box in
 * this app uses.
 *
 * "Services" and "Vaccines" stay real, selectable category checkboxes
 * — but genuinely return zero results, since neither is backed by a
 * Firestore collection anywhere in this app yet (Vaccines specifically:
 * getAllMedicines() only returns "medicine"-typed docs, same gap noted
 * on patient/medicine.php — the real vaccine-typed docs live in the
 * same "medicines" collection, just excluded by that function today).
 * That's honest empty-state behavior for an unmodeled category, not a
 * bug — same principle as includes/subadmin/user_management_firestore.php's
 * trimmed role list not inventing options with nothing behind them.
 * "Health Tips" was removed from the filter list entirely (by request)
 * rather than left in this same always-empty state.
 *
 * $popularSearches is real now — includes/shared/search_log.php logs
 * every real, non-empty search term to Firestore's "search_logs"
 * collection and ranks the real top 5 by count (see that file's header
 * comment). [] when nobody's searched anything yet (an honest empty
 * state, not a hardcoded placeholder) — patient/search.php hides the
 * whole row in that case.
 *
 * The results panel itself only ever shows real data for an actual
 * typed query — an empty $queryTerm now returns zero results rather
 * than browsing every medicine/announcement in the system, so what's
 * on screen is never "the same fixed list" regardless of what was
 * searched; see $searchResults below.
 * =====================================================================
 */

require_once __DIR__ . '/../../firebase/firestore.php'; // getAllMedicines(), getAllAnnouncements()
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()
require_once __DIR__ . '/../shared/search_log.php'; // hc_log_search_term(), hc_get_popular_searches()

$queryTerm = trim($_GET['q'] ?? '');
if ($queryTerm !== '') {
    hc_log_search_term($queryTerm);
}
$popularSearches = hc_get_popular_searches(5);
$selectedCategories = $_GET['category'] ?? [];
$hasCategoryFilter = !empty($selectedCategories) && !in_array('all', $selectedCategories, true);

// Category filters — 'key' matches the category used in $searchResults below.
$filterCategories = [
    ['key' => 'all',           'label' => 'All Categories', 'icon' => 'bi-grid-fill',        'color' => 'dark',   'checked' => !$hasCategoryFilter],
    ['key' => 'service',       'label' => 'Services',       'icon' => 'bi-clipboard2-pulse', 'color' => 'blue',   'checked' => in_array('service', $selectedCategories, true)],
    ['key' => 'medicine',      'label' => 'Medicines',      'icon' => 'bi-capsule',          'color' => 'green',  'checked' => in_array('medicine', $selectedCategories, true)],
    ['key' => 'vaccine',       'label' => 'Vaccines',       'icon' => 'bi-droplet-half',     'color' => 'purple', 'checked' => in_array('vaccine', $selectedCategories, true)],
    ['key' => 'announcement',  'label' => 'Announcements',  'icon' => 'bi-megaphone-fill',   'color' => 'orange', 'checked' => in_array('announcement', $selectedCategories, true)],
];

// ---------------------------------------------------------------------
// Real results — medicines + announcements, fuzzy-matched against
// $queryTerm, merged into one unified card shape. Only runs for an
// ACTUAL typed query — an empty $queryTerm used to "browse everything"
// (every medicine + announcement in the system), which looked like a
// fixed, unchanging list on every visit since it's the same real data
// every time regardless of what was searched. Requiring a real query
// means the panel only ever shows something once a visitor searches
// for it.
// ---------------------------------------------------------------------
$searchResults = [];

if ($queryTerm !== '') {
    if (!$hasCategoryFilter || in_array('medicine', $selectedCategories, true)) {
        foreach (getAllMedicines() as $m) {
            if (!hc_fuzzy_match($m['name'] . ' ' . $m['desc'], $queryTerm)) {
                continue;
            }
            $searchResults[] = [
                'icon' => $m['icon'],
                'category' => 'medicine',
                'catLabel' => 'Medicine',
                'title' => $m['name'],
                'desc' => $m['desc'],
                'status' => $m['status'] === 'unavailable' ? 'unavailable' : 'available',
                'statusLabel' => $m['status'] === 'unavailable' ? 'Not Available' : 'Available',
                'href' => 'medicine.php?q=' . urlencode($m['name']),
            ];
        }
    }

    if (!$hasCategoryFilter || in_array('announcement', $selectedCategories, true)) {
        foreach (getAllAnnouncements() as $a) {
            if (!hc_fuzzy_match($a['title'] . ' ' . $a['desc'], $queryTerm)) {
                continue;
            }
            $searchResults[] = [
                'icon' => $a['icon'],
                'category' => 'announcement',
                'catLabel' => 'Announcement',
                'title' => $a['title'],
                'desc' => $a['desc'],
                'status' => null,
                'statusLabel' => '',
                'href' => 'announcements.php?q=' . urlencode($a['title']),
            ];
        }
    }
    // "service", "vaccine" — deliberately no real data source yet (see
    // header comment), so they contribute zero results here regardless
    // of $selectedCategories.
}

// ---------------------------------------------------------------------
// Pagination — real total/page-count from the merged, filtered result
// above.
// ---------------------------------------------------------------------
$totalResults = count($searchResults);
$resultsPerPage = 6;
$currentPageNum = max(1, (int) ($_GET['page'] ?? 1));
$totalPages = max(1, (int) ceil($totalResults / $resultsPerPage));
$currentPageNum = min($currentPageNum, $totalPages);

$searchResults = array_slice($searchResults, ($currentPageNum - 1) * $resultsPerPage, $resultsPerPage);
