<?php
/**
 * =====================================================================
 * Dashboard Range Dropdown (partial)
 * ---------------------------------------------------------------------
 * Renders one dashboard card's filter dropdown: preset options (real
 * GET links — a working no-JS fallback) plus "Last Year" and a "Custom
 * Date" row (styled as a regular .dropdown-item so it reads as part of
 * the same list, with a rotating chevron for state) that reveals a
 * card-like From/To form, from an includes/shared/query_helpers.php
 * hc_resolve_dashboard_range() result. Shared by every role's dashboard
 * (medical-staff, doctor, subadmin, medical technician) so Last Year/
 * Custom Date behave identically everywhere. The From/To form starts
 * collapsed (.hc-custom-range-wrap, shown only once "Custom Date" is
 * clicked, or on load when it's already the active range — see
 * assets/css/admin-dashboard.css for the max-height transition) —
 * assets/js/dashboard-range-filters.js toggles its .hc-expanded class
 * via [data-custom-toggle]/[data-custom-range-wrap] (stopping the click
 * from bubbling to Bootstrap's own dropdown-auto-close handler, which
 * would otherwise treat clicking this button like clicking any other
 * dropdown-item and close the whole menu) and re-positions the dropdown
 * (Bootstrap/Popper) afterward so the panel growing taller doesn't
 * overflow past where the menu was originally placed. It separately
 * intercepts both the preset links and the custom form's submit on
 * pages that opt in (wrap the card in a [data-dashboard-card][data-
 * range-param] element) to refresh just that card instead of reloading
 * the page — without JS, the panel stays visible/hidden per its initial
 * state and every control here is still a real, working link/form.
 *
 * Include with these set beforehand:
 *   $range      — result of hc_resolve_dashboard_range()
 *   $drBtnClass — the page's own range-button CSS class
 *                 ("sad-range-btn" on subadmin, "mst-range-btn" on
 *                 medical-staff, "doc-range-btn" on doctor,
 *                 "mt-range-btn" on medical technician, "aud-filter-btn"
 *                 on every role's Audit Logs)
 *   $drBtnIcon  — optional Bootstrap Icons class (e.g. "bi-calendar3")
 *                 shown before the label; omit/leave unset for none
 *
 * Every link/the form here also resets ?page=1 when present on the
 * current URL — harmless on the dashboard cards (which have no
 * pagination of their own), but keeps Audit Logs' table from landing on
 * a now out-of-range page after narrowing the date range, same as its
 * other filter dropdowns already do.
 * =====================================================================
 */
$drBtnIcon = $drBtnIcon ?? '';
?>
<div class="dropdown">
    <button class="<?= htmlspecialchars($drBtnClass) ?> dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-range-label>
        <?php if ($drBtnIcon !== ''): ?><i class="bi <?= htmlspecialchars($drBtnIcon) ?>"></i> <?php endif; ?><?= htmlspecialchars($range['label']) ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($range['options'] as $optKey => $optLabel):
            if ($optKey === 'custom') {
                continue; // rendered as its own inline form below
            } ?>
        <li><a class="dropdown-item <?= $range['key'] === $optKey ? 'active' : '' ?>" data-range-value="<?= htmlspecialchars($optKey) ?>" href="<?= htmlspecialchars(hc_url_with_params([$range['param'] => $optKey, 'page' => 1])) ?>"><?= htmlspecialchars($optLabel) ?></a></li>
        <?php endforeach; ?>
        <li><hr class="dropdown-divider"></li>
        <li>
            <button type="button" class="dropdown-item hc-custom-toggle d-flex align-items-center justify-content-between <?= $range['key'] === 'custom' ? 'active hc-expanded' : '' ?>" data-custom-toggle>
                <span data-custom-toggle-label>Custom Date<?= $range['key'] === 'custom' ? ' (active)' : '' ?></span>
                <i class="bi bi-chevron-down hc-custom-chevron"></i>
            </button>
        </li>
        <li class="hc-custom-range-wrap <?= $range['key'] === 'custom' ? 'hc-expanded' : '' ?>" data-custom-range-wrap>
            <form method="get" class="hc-custom-range-form" data-custom-range-form data-range-param="<?= htmlspecialchars($range['param']) ?>">
                <?php foreach ($_GET as $k => $v):
                    if (in_array($k, [$range['param'], $range['param'] . 'From', $range['param'] . 'To', 'page'], true)) {
                        continue;
                    } ?>
                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="<?= htmlspecialchars($range['param']) ?>" value="custom">
                <?php if (array_key_exists('page', $_GET)): ?><input type="hidden" name="page" value="1"><?php endif; ?>
                <div class="hc-custom-range-fields">
                    <label class="hc-custom-range-field">
                        <span>From</span>
                        <input type="date" class="form-control form-control-sm" name="<?= htmlspecialchars($range['param']) ?>From" value="<?= htmlspecialchars($range['customFrom']) ?>" required>
                    </label>
                    <label class="hc-custom-range-field">
                        <span>To</span>
                        <input type="date" class="form-control form-control-sm" name="<?= htmlspecialchars($range['param']) ?>To" value="<?= htmlspecialchars($range['customTo']) ?>" required>
                    </label>
                </div>
                <button type="submit" class="btn btn-sm btn-danger w-100">Apply</button>
            </form>
        </li>
    </ul>
</div>
