<?php
/**
 * =====================================================================
 * Pagination Helpers
 * ---------------------------------------------------------------------
 * Real, shared pagination for the subadmin Analytics & Reports report
 * tables (New Patient Registrations, Recent Stock Requests, Physician
 * Consultations, Consultations by Barangay, Vaccine Administration
 * Summary, Vaccination by Barangay, etc.) — before this, every one of
 * those pages hardcoded "$xCurrentPage = 1" and rendered only the first
 * slice of real data no matter which page-btn (all href="#") was
 * clicked. hc_paginate() does the real slicing; hc_render_pagination_nav()
 * renders the First/Prev/1..N/.../Last controls as real links (one per
 * table, addressed by its own $pageParam so multiple paginated tables
 * on the same page page independently), reusing
 * includes/shared/query_helpers.php so switching pages never resets any
 * other active filter or table's own page.
 * =====================================================================
 */

require_once __DIR__ . '/query_helpers.php';

if (!function_exists('hc_paginate')) {
    function hc_paginate(array $items, string $pageParam, int $perPage = 5): array
    {
        $total = count($items);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = (int) ($_GET[$pageParam] ?? 1);
        $page = max(1, min($page, $totalPages));

        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'totalPages' => $totalPages,
            'page' => $page,
        ];
    }
}

if (!function_exists('hc_render_pagination_nav')) {
    function hc_render_pagination_nav(string $ariaLabel, string $pageParam, int $currentPage, int $totalPages): string
    {
        $atFirst = $currentPage <= 1;
        $atLast = $currentPage >= $totalPages;
        $windowStart = max(1, min($currentPage, max(1, $totalPages - 2)));
        $windowEnd = min($totalPages, $windowStart + 2);

        ob_start(); ?>
        <nav class="adm-pagination" aria-label="<?= htmlspecialchars($ariaLabel) ?>">
            <a href="<?= htmlspecialchars(hc_url_with_params([$pageParam => 1])) ?>" class="page-btn<?= $atFirst ? ' disabled' : '' ?>" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
            <a href="<?= htmlspecialchars(hc_url_with_params([$pageParam => max(1, $currentPage - 1)])) ?>" class="page-btn<?= $atFirst ? ' disabled' : '' ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
            <a href="<?= htmlspecialchars(hc_url_with_params([$pageParam => $p])) ?>" class="page-btn<?= $p === $currentPage ? ' active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($windowEnd < $totalPages): ?>
            <span class="page-ellipsis">&hellip;</span>
            <a href="<?= htmlspecialchars(hc_url_with_params([$pageParam => $totalPages])) ?>" class="page-btn"><?= $totalPages ?></a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars(hc_url_with_params([$pageParam => min($totalPages, $currentPage + 1)])) ?>" class="page-btn<?= $atLast ? ' disabled' : '' ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <a href="<?= htmlspecialchars(hc_url_with_params([$pageParam => $totalPages])) ?>" class="page-btn<?= $atLast ? ' disabled' : '' ?>" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
        </nav>
        <?php
        return ob_get_clean();
    }
}
