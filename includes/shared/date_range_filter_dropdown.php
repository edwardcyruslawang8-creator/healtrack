<?php
/**
 * =====================================================================
 * Date Range Filter Dropdown (partial)
 * ---------------------------------------------------------------------
 * Renders the "Today / This Week / This Month / Custom Range" dropdown
 * shared by all 5 subadmin report pages, backed by
 * includes/shared/date_range_filter.php's hc_resolve_date_range_filter().
 * "Custom Range" doesn't navigate directly — it reveals a small inline
 * two-date form (still inside the dropdown-menu, so no extra JS needed;
 * Bootstrap only auto-closes a dropdown on a .dropdown-item click or an
 * outside click, not on clicks inside its own <input>s) that GETs back
 * to this same page with dateFilter=custom&dateFrom=...&dateTo=...,
 * preserving every other active filter/page param via hidden inputs
 * (same "never silently reset another control" rule
 * includes/shared/query_helpers.php's hc_url_with_params() follows for
 * every other filter on these pages).
 *
 * Include with these set beforehand:
 *   $drfState    — result of hc_resolve_date_range_filter()
 *   $drfBtnClass — the page's own filter-button CSS class
 *                  ("prp-filter-select" on Patient Reports,
 *                  "arp-filter-select" on the other 4)
 * =====================================================================
 */
?>
<div class="dropdown">
    <button class="btn btn-light border <?= htmlspecialchars($drfBtnClass) ?> dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-calendar3"></i> <?= htmlspecialchars($drfState['label']) ?>
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item <?= $drfState['option'] === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars(hc_url_with_params([$drfState['optionParam'] => 'all'])) ?>">All Time</a></li>
        <li><a class="dropdown-item <?= $drfState['option'] === 'today' ? 'active' : '' ?>" href="<?= htmlspecialchars(hc_url_with_params([$drfState['optionParam'] => 'today'])) ?>">Today</a></li>
        <li><a class="dropdown-item <?= $drfState['option'] === 'week' ? 'active' : '' ?>" href="<?= htmlspecialchars(hc_url_with_params([$drfState['optionParam'] => 'week'])) ?>">This Week</a></li>
        <li><a class="dropdown-item <?= $drfState['option'] === 'month' ? 'active' : '' ?>" href="<?= htmlspecialchars(hc_url_with_params([$drfState['optionParam'] => 'month'])) ?>">This Month</a></li>
        <li><hr class="dropdown-divider"></li>
        <li class="px-3 py-2" style="min-width:210px;">
            <div class="small text-muted mb-2 fw-semibold <?= $drfState['option'] === 'custom' ? 'text-danger' : '' ?>">Custom Range<?= $drfState['option'] === 'custom' ? ' (active)' : '' ?></div>
            <form method="get" class="d-flex flex-column gap-2">
                <?php foreach ($_GET as $k => $v):
                    if (in_array($k, [$drfState['optionParam'], $drfState['fromParam'], $drfState['toParam']], true)) {
                        continue;
                    } ?>
                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="<?= htmlspecialchars($drfState['optionParam']) ?>" value="custom">
                <input type="date" class="form-control form-control-sm" name="<?= htmlspecialchars($drfState['fromParam']) ?>" value="<?= htmlspecialchars($drfState['fromRaw']) ?>" required>
                <input type="date" class="form-control form-control-sm" name="<?= htmlspecialchars($drfState['toParam']) ?>" value="<?= htmlspecialchars($drfState['toRaw']) ?>" required>
                <button type="submit" class="btn btn-sm btn-danger">Apply</button>
            </form>
        </li>
    </ul>
</div>
