<?php
/**
 * =====================================================================
 * Announcements Page — Data
 * ---------------------------------------------------------------------
 * Reads live from the same "announcements" Firestore collection
 * index.php's widget uses (firebase/firestore.php's
 * getAllAnnouncements() — the full-field, unlimited variant of
 * getAnnouncements()). This is the full announcements listing/search/
 * filter page; getAnnouncements() still separately powers the small
 * "latest 3" widget on index.php.
 *
 * The category filter (?category=) and search box (?q=) filter
 * $allAnnouncements exactly as they did when this was a static array —
 * that logic didn't need to change, only where the array came from.
 * $announcementCategoryCounts is now a real per-category count instead
 * of the placeholder numbers this file used before Firestore was wired
 * in here. Pagination is real too, though with the current catalog
 * size everything fits on page 1.
 * =====================================================================
 */

require_once __DIR__ . '/../../firebase/firestore.php';
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()

$categoryMeta = [
    'general' => ['label' => 'General', 'color' => 'green'],
    'alert'   => ['label' => 'Alert',   'color' => 'red'],
    'event'   => ['label' => 'Event',   'color' => 'blue'],
];

// Only ever show approved announcements here — a pending (awaiting
// admin review) or rejected one must never reach a patient. See
// api/create-announcement.php / admin/announcements.php for the real
// approval workflow this now goes through.
$allAnnouncements = array_values(array_filter(getAllAnnouncements(), fn ($a) => $a['status'] === 'approved'));

// ---------------------------------------------------------------------
// Sidebar "Announcement Categories" counts — real per-category tallies
// over $allAnnouncements.
// ---------------------------------------------------------------------
$categoryTallies = ['general' => 0, 'alert' => 0, 'event' => 0];
foreach ($allAnnouncements as $announcement) {
    if (isset($categoryTallies[$announcement['category']])) {
        $categoryTallies[$announcement['category']]++;
    }
}
$announcementCategoryCounts = [
    ['key' => 'all',     'label' => 'All Announcements', 'color' => 'dark',  'count' => count($allAnnouncements)],
    ['key' => 'general', 'label' => 'General',           'color' => 'green', 'count' => $categoryTallies['general']],
    ['key' => 'alert',   'label' => 'Alerts',             'color' => 'red',   'count' => $categoryTallies['alert']],
    ['key' => 'event',   'label' => 'Events',             'color' => 'blue',  'count' => $categoryTallies['event']],
];

// ---------------------------------------------------------------------
// Apply the category filter (?category=) and search box (?q=).
// ---------------------------------------------------------------------
$activeCategory = $_GET['category'] ?? 'all';
if (!isset($categoryMeta[$activeCategory])) {
    $activeCategory = 'all';
}
$searchTerm = trim($_GET['q'] ?? '');

$filteredAnnouncements = array_values(array_filter($allAnnouncements, function ($a) use ($activeCategory, $searchTerm) {
    if ($activeCategory !== 'all' && $a['category'] !== $activeCategory) {
        return false;
    }
    if ($searchTerm !== '' && !hc_fuzzy_match($a['title'] . ' ' . $a['desc'], $searchTerm)) {
        return false;
    }
    return true;
}));

// ---------------------------------------------------------------------
// Pagination — real total/page-count from the filtered result above.
// ---------------------------------------------------------------------
$announcementsTotal = count($filteredAnnouncements);
$announcementsPerPage = 5;
$announcementsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$announcementsTotalPages = max(1, (int) ceil($announcementsTotal / $announcementsPerPage));
$announcementsCurrentPage = min($announcementsCurrentPage, $announcementsTotalPages);

$announcements = array_slice($filteredAnnouncements, ($announcementsCurrentPage - 1) * $announcementsPerPage, $announcementsPerPage);
