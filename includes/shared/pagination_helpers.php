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

if (!function_exists('hc_page_window')) {
    /**
     * Page numbers to show around $current, at most 7 slots, '...' for
     * gaps — the window MOVES with the current page so it's always
     * visible and highlighted, with its neighbours one click away:
     *   1 2 3 4 5 … 11      (pages 1–4)
     *   1 … 5 6 7 … 11      (middle)
     *   1 … 7 8 9 10 11     (last 4)
     * Replaces a fixed "1 2 3 4 … N" that hid the current page from page
     * 5 onward.
     *
     * @return array<int, int|string>
     */
    function hc_page_window(int $current, int $total): array
    {
        if ($total <= 7) {
            return range(1, max(1, $total));
        }
        if ($current <= 4) {
            return [1, 2, 3, 4, 5, '...', $total];
        }
        if ($current >= $total - 3) {
            return [1, '...', $total - 4, $total - 3, $total - 2, $total - 1, $total];
        }

        return [1, '...', $current - 1, $current, $current + 1, '...', $total];
    }
}

if (!function_exists('hc_render_pagination_nav')) {
    function hc_render_pagination_nav(string $ariaLabel, string $pageParam, int $currentPage, int $totalPages): string
    {
        $atFirst = $currentPage <= 1;
        $atLast = $currentPage >= $totalPages;
        $windowStart = max(1, min($currentPage, max(1, $totalPages - 2)));
        $windowEnd = min($totalPages, $windowStart + 2);

        // An arrow that can't go anywhere (First/Previous on page 1,
        // Next/Last on the last page — all four when there's only one
        // page) is a non-link <span>, not a greyed-out <a> that still
        // navigates when clicked.
        $arrow = function (bool $enabled, int $page, string $label, string $icon) use ($pageParam): string {
            $iconHtml = '<i class="bi ' . $icon . '"></i>';
            return $enabled
                ? '<a href="' . htmlspecialchars(hc_url_with_params([$pageParam => $page])) . '" class="page-btn" aria-label="' . $label . '">' . $iconHtml . '</a>'
                : '<span class="page-btn disabled" aria-disabled="true" aria-label="' . $label . '">' . $iconHtml . '</span>';
        };

        ob_start(); ?>
        <nav class="adm-pagination" aria-label="<?= htmlspecialchars($ariaLabel) ?>">
            <?= $arrow(!$atFirst, 1, 'First page', 'bi-chevron-bar-left') ?>
            <?= $arrow(!$atFirst, $currentPage - 1, 'Previous page', 'bi-chevron-left') ?>
            <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
            <a href="<?= htmlspecialchars(hc_url_with_params([$pageParam => $p])) ?>" class="page-btn<?= $p === $currentPage ? ' active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($windowEnd < $totalPages): ?>
            <span class="page-ellipsis">&hellip;</span>
            <a href="<?= htmlspecialchars(hc_url_with_params([$pageParam => $totalPages])) ?>" class="page-btn"><?= $totalPages ?></a>
            <?php endif; ?>
            <?= $arrow(!$atLast, $currentPage + 1, 'Next page', 'bi-chevron-right') ?>
            <?= $arrow(!$atLast, $totalPages, 'Last page', 'bi-chevron-bar-right') ?>
        </nav>
        <?php
        return ob_get_clean();
    }
}
