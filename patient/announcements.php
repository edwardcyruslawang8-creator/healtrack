<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Announcements Page (patient/announcements.php)
 * ---------------------------------------------------------------------
 * Reuses the exact same shell as the rest of patient/*.php
 * (includes/header.php + includes/sidebar.php + includes/footer.php,
 * assets/css/base.css) — same pattern as patient/medicine.php. The
 * category filter pills and announcement icons reuse
 * assets/css/medicine.css's .tab-btn / .med-row-icon classes verbatim
 * rather than inventing parallel ones.
 *
 * Reads live from the same "announcements" Firestore collection
 * index.php's widget uses — see includes/public/announcements-data.php's
 * header comment for the full picture (getAllAnnouncements() in
 * firebase/firestore.php, real category counts, real pagination).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php';       // BASE_URL
require_once __DIR__ . '/../includes/public/data.php';           // $navItems (shared sidebar)
require_once __DIR__ . '/../includes/public/announcements-data.php';
// $categoryMeta, $announcementCategoryCounts, $allAnnouncements,
// $activeCategory, $searchTerm, $announcements, $announcementsTotal,
// $announcementsPerPage, $announcementsCurrentPage, $announcementsTotalPages

$pageTitle   = 'Announcements';
$pageCss     = ['announcements.css', 'quick-search.css'];
$pageScripts = ['quick-search.js'];
require_once __DIR__ . '/../includes/shared/header.php';
require_once __DIR__ . '/../includes/shared/sidebar.php';
?>

<div class="main-wrapper">
<main class="main-content">

    <!-- PAGE HEADER + BELL/PROFILE -->
    <div class="page-header">
        <h1>Announcements</h1>
        <p>Stay updated with the latest news, alerts, and important information.</p>
    </div>

    <div class="ann-layout">

        <!-- ============ MAIN COLUMN: SEARCH + FILTERS + LIST ============ -->
        <div class="ann-main-col">

            <!-- SEARCH + CATEGORY FILTER PILLS -->
            <form class="ann-toolbar" action="announcements.php" method="get">
                <input type="hidden" name="category" value="<?= htmlspecialchars($activeCategory) ?>">
                <label class="ann-search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search announcements..." autocomplete="off" data-quick-search="<?= BASE_URL ?>/api/quick-search-patient.php?scope=announcements">
                </label>
                <div class="ann-filter-pills">
                    <?php foreach ($announcementCategoryCounts as $cat): ?>
                    <a href="announcements.php?category=<?= urlencode($cat['key']) ?><?= $searchTerm !== '' ? '&q=' . urlencode($searchTerm) : '' ?>"
                       class="tab-btn <?= $activeCategory === $cat['key'] ? 'active' : '' ?>">
                        <?= $cat['key'] === 'all' ? 'All' : htmlspecialchars($cat['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </form>

            <!-- ANNOUNCEMENT LIST -->
            <?php if (empty($announcements)): ?>
            <div class="ann-empty">
                <i class="bi bi-inbox" style="font-size:28px; display:block; margin-bottom:10px; color:var(--text-soft);"></i>
                No announcements match your search or filter.
            </div>
            <?php endif; ?>

            <?php foreach ($announcements as $a): ?>
            <div class="ann-card">
                <div class="med-row-icon <?= htmlspecialchars($a['iconColor']) ?>">
                    <i class="bi <?= htmlspecialchars($a['icon']) ?>"></i>
                </div>
                <div class="ann-card-body">
                    <div class="ann-card-top">
                        <h3><?= htmlspecialchars($a['title']) ?></h3>
                        <span class="ann-date"><?= htmlspecialchars($a['date']) ?></span>
                    </div>
                    <p><?= htmlspecialchars($a['desc']) ?></p>
                    <div class="ann-card-tags">
                        <span class="ann-tag <?= htmlspecialchars($a['category']) ?>"><?= htmlspecialchars($categoryMeta[$a['category']]['label']) ?></span>
                        <?php if (!empty($a['important'])): ?>
                        <span class="ann-tag important">Important</span>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="ann-menu-btn" aria-label="More options">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
            </div>
            <?php endforeach; ?>

            <!-- LIST FOOTER: SHOWING TEXT + PAGINATION -->
            <div class="ann-list-footer">
                <span class="ann-showing-text">
                    <?= $announcementsTotal > 0
                        ? 'Showing 1 to ' . count($announcements) . ' of ' . number_format($announcementsTotal) . ' announcements'
                        : 'No announcements to show' ?>
                </span>
                <nav class="pagination-custom" aria-label="Announcements pages">
                    <a href="#" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                    <?php for ($p = 1; $p <= $announcementsTotalPages; $p++): ?>
                    <a href="#" class="page-btn <?= $p === $announcementsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="#" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                </nav>
            </div>
        </div>

        <!-- ============ RIGHT RAIL ============ -->
        <aside class="ann-sidebar-col">

            <div class="ann-categories-card">
                <h3>Announcement Categories</h3>
                <?php foreach ($announcementCategoryCounts as $cat): ?>
                <a href="announcements.php?category=<?= urlencode($cat['key']) ?>" class="ann-category-row <?= $activeCategory === $cat['key'] ? 'active' : '' ?>">
                    <span class="dot <?= htmlspecialchars($cat['color']) ?>"></span>
                    <span class="label"><?= htmlspecialchars($cat['label']) ?></span>
                    <span class="count"><?= (int) $cat['count'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="ann-reminder-card">
                <i class="bi bi-shield-fill-check icon"></i>
                <h4>Important Reminder</h4>
                <p>Announcements are official communications from North Super Health Center. Please read them regularly to stay informed about important updates and schedule changes.</p>
            </div>

            <div class="ann-report-card">
                <h4>Need to Report an Issue?</h4>
                <p>Notify us about any concerns or information that requires attention.</p>
                <button type="button" class="ann-report-btn" data-open-chatbot>
                    <span><i class="bi bi-flag-fill"></i> Report a Concern</span>
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>

        </aside>

    </div>

</main>
</div>

<?php require_once __DIR__ . '/../includes/shared/footer.php'; ?>
