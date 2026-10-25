<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Inventory Reports (subadmin/inventory_reports.php)
 * ---------------------------------------------------------------------
 * The "Inventory Reports" tab of the Analytics & Reports section — a
 * sibling page to subadmin/analytics_reports.php ("Overview") and
 * subadmin/patient_reports.php ("Patient Reports"), reached via those
 * pages' report tabs (and vice versa). Reuses the exact same shell as
 * the rest of subadmin/*.php (280px dark-red gradient sidebar, "adm-"
 * prefixed atoms, assets/css/admin-dashboard.css), the same chart-card/
 * legend/ranked-bar-list patterns as subadmin/dashboard.php ("sad-"
 * prefixed, assets/css/subadmin_dashboard.css), and the same
 * breadcrumb/tabs/section-intro/filter-toolbar/footer-notice styling as
 * analytics_reports.php ("arp-" prefixed, assets/css/analytics_reports.css
 * — the section-intro/filter-toolbar classes were promoted there from
 * subadmin/patient_reports.php's page-specific stylesheet so every
 * report sub-tab can share them). This page adds NO page-specific
 * stylesheet of its own — everything here is built from those three
 * already-shared files plus a couple of inline style overrides (noted
 * where used) — see subadmin/patient_reports.php's header comment for
 * why a separate per-page stylesheet was avoided this time.
 *
 * PARTIALLY connected to real Firestore data — see
 * includes/subadmin/inventory_reports_firestore.php's header comment for exactly
 * which stat cards/charts/tables are real vs. still placeholder (Stock
 * Movement Summary only). The filter toolbar (date range / category /
 * form / status) is real now (see that file's header comment for what
 * each one filters). Every table on this page has real click-to-sort
 * columns (assets/js/sortable-table.js). The
 * Inventory Status Overview / Inventory by Category charts are drawn
 * with Chart.js from data JSON-encoded into the page (see
 * assets/js/inventory_reports.js) — same approach as
 * assets/js/analytics_reports.js.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php'; // t()

// Only a signed-in sub-admin may view this page.
if (($_SESSION['role'] ?? null) !== 'subadmin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/subadmin/inventory_reports_firestore.php';
// $adminProfile, $analyticsDateRangeLabel, $facilityOptions, $analyticsTabs,
// $categoryFilterOptions, $formFilterOptions, $statusFilterOptions,
// $inventoryStatCards, $inventoryStatusOverview, $inventoryStatusOverviewTotal,
// $inventoryByCategory, $inventoryByCategoryTotal, $lowestStockItems,
// $stockStatusMeta, $stockMovementSummary, $stockMovementTotal,
// $itemsExpiringSoon

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Analytics & Reports stays active — this page is one of its tabs.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',                'label' => t('nav.medicine_vaccine_inventory'), 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-box-seam-fill',          'label' => t('nav.stock_requests'),               'href' => 'stock_requests.php'],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => t('nav.analytics_reports'),
        'id' => 'admNavAnalyticsReports',
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => t('nav.analytics_reports_facility'), 'href' => 'analytics_reports.php', 'active' => true],
            ['label' => t('nav.analytics_reports_chatbot'),  'href' => 'chatbot_reports.php'],
        ],
    ],
    ['icon' => 'bi-people-fill',            'label' => t('nav.user_management'),              'href' => 'user_management.php'],
    ['icon' => 'bi-bell-fill',              'label' => t('nav.notifications'),                'href' => 'notifications.php', 'badge' => subadmin_unread_notification_count()],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => t('nav.audit_logs_subadmin'),           'href' => 'audit_logs.php?role=subadmin'],
            ['label' => t('nav.audit_logs_doctor'),             'href' => 'audit_logs.php?role=doctor'],
            ['label' => t('nav.audit_logs_medstaff'),      'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => t('nav.audit_logs_medtech'), 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => t('nav.audit_logs_radiotech'), 'href' => 'audit_logs.php?role=radio_technologist'],
            ['label' => t('nav.audit_logs_pharmacist'),         'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-file-earmark-lock-fill', 'label' => t('nav.consent_management'),           'href' => 'consent_management.php'],
    ['icon' => 'bi-megaphone-fill',         'label' => t('nav.announcements'),                'href' => 'announcements.php'],
    [
        'icon' => 'bi-gear-fill',
        'label' => t('nav.profile_settings'),
        'id' => 'admNavProfileSettings',
        'children' => [
            ['label' => t('nav.profile_settings'), 'href' => 'profile_settings.php'],
            ['label' => t('nav.homepage_settings'), 'href' => 'homepage_settings.php'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory Reports — HEALTrack</title>

<!-- Poppins (self-hosted — see assets/css/fonts-poppins.css header comment) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/fonts-poppins.css') ?>">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, donuts, table, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the chart-card/legend/ranked-list patterns -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Analytics & Reports styles, reused here for the breadcrumb/tabs/section-intro/filter-toolbar/footer notice -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
<style>
/* Top 5 Items with Lowest Stock lives in the narrowest (3rd-of-3)
   .sad-row-3 column (~370-400px in practice). .adm-table's normal
   13px/13-14px-padding cells + a nowrap .arp-badge don't fit 4 columns
   (Item Name/Category/Stock/Status) in that width even after removing
   the table's default 640px min-width — the columns' own unbreakable
   minimums (a badge like "Out of Stock", a long medicine name) still
   summed past it and forced a scrollbar. Tightening padding/font-size
   here (inline, so it can't be blocked the way an external per-page
   stylesheet was for a previous page) brings the minimum back under
   what the column actually has.
*/
.ir-compact-table th,
.ir-compact-table td{ padding: 8px 8px; font-size: 12px; }
.ir-compact-table .arp-badge{ padding: 3px 8px; font-size: 10px; }
</style>
</head>
<body class="adm-body">

<!-- Mobile sidebar trigger -->
<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<!-- =============================================================
     SIDEBAR — reused verbatim from analytics_reports.php /
     patient_reports.php / dashboard.php, no structural changes.
     ============================================================= -->
<aside class="adm-sidebar" id="admSidebar">

    <!-- Brand -->
    <div class="adm-brand">
        <div class="adm-logo-badge"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p><?= htmlspecialchars(t('app.tagline')) ?></p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Sub-admin profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($adminProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($adminProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($adminNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
                <ul class="adm-nav-submenu">
                    <?php foreach ($item['children'] as $child): ?>
                    <li><a href="<?= htmlspecialchars($child['href']) ?>" class="adm-nav-sublink <?= !empty($child['active']) ? 'active' : '' ?>"><?= htmlspecialchars($child['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </li>
        <?php else: ?>
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="sad-nav-badge"><?= (int) $item['badge'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <div class="adm-nav-bottom">
        <hr class="adm-sidebar-divider" style="margin-bottom:12px;">
        <a href="<?= BASE_URL ?>/api/logout.php" class="adm-nav-link">
            <i class="bi bi-box-arrow-right"></i>
            <span><?= htmlspecialchars(t('nav.logout')) ?></span>
        </a>
    </div>
</aside>

<!-- =============================================================
     MAIN CONTENT
     ============================================================= -->
<main class="adm-main">

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= htmlspecialchars(t('nav.analytics_reports')) ?></h1>
            <nav class="arp-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="arp-breadcrumb-sep">&gt;</span>
                <a href="analytics_reports.php">Analytics &amp; Reports</a>
                <span class="arp-breadcrumb-sep">&gt;</span>
                <span class="arp-breadcrumb-current arp-breadcrumb-current-red">Inventory Reports</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($analyticsDateRangeLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this sub-admin is actually
                     assigned to (their real users/{uid} doc's "facility" field);
                     not a switcher, since a sub-admin can't reassign themselves. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($adminProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (subadmin_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= subadmin_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars($adminProfile['name']) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($adminProfile['role']) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i>Profile / Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= htmlspecialchars(t('nav.logout')) ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ REPORT TABS ============ -->
    <ul class="nav arp-tabs" role="tablist">
        <?php foreach ($analyticsTabs as $tab): ?>
        <li class="nav-item" role="presentation">
            <a href="<?= htmlspecialchars($tab['href']) ?>" class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>" data-ajax-tab>
                <?= htmlspecialchars($tab['label']) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- ============ SECTION INTRO ============ -->
    <div class="arp-section-intro">
        <h2>Inventory Reports</h2>
        <p>Inventory medicine and vaccine inventory status, stock levels, and movement summaries.</p>
    </div>

    <!-- ============ FILTER TOOLBAR ============
         All real now — see includes/subadmin/inventory_reports_firestore.php's
         header comment for exactly what each one filters. -->
    <div class="arp-filter-bar">
        <?php $drfState = $irDateFilter; $drfBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/date_range_filter_dropdown.php'; ?>

        <?php $sfdOptions = $categoryFilterOptions; $sfdParam = 'category'; $sfdCurrent = $irCategoryFilter; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <?php $sfdOptions = $formFilterOptions; $sfdParam = 'form'; $sfdCurrent = $irFormFilter; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <?php $sfdOptions = $statusFilterOptions; $sfdParam = 'status'; $sfdCurrent = $irStatusFilter; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <span class="arp-filter-spacer"></span>
    </div>

    <!-- ============ SUMMARY STAT CARDS ============
         Reuses analytics_reports.php's Overview-tab KPI card classes
         verbatim (.adm-card.arp-kpi-card / .arp-kpi-icon / .arp-kpi-text
         / .arp-kpi-label / .arp-kpi-value / .arp-kpi-trend) — 5 cards
         fits .arp-kpi-row's native 5-up grid with no override needed. -->
    <div class="arp-kpi-row">
        <?php foreach ($inventoryStatCards as $stat): ?>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label"><?= htmlspecialchars($stat['label']) ?></span>
                <span class="arp-kpi-value"><?= number_format((int) $stat['value']) ?></span>
                <span class="arp-kpi-trend"><?= htmlspecialchars($stat['sub']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ STATUS OVERVIEW + BY CATEGORY + LOWEST STOCK ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Inventory Status Overview</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="inventoryStatusOverviewChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($inventoryStatusOverviewTotal) ?></div>
                        <div class="adm-donut-total-label">Total Items</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($inventoryStatusOverview as $s): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($s['value']) ?> (<?= rtrim(rtrim(number_format($s['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Inventory by Category</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="inventoryByCategoryChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($inventoryByCategoryTotal) ?></div>
                        <div class="adm-donut-total-label">Total Items</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($inventoryByCategory as $c): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($c['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($c['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($c['value']) ?> (<?= rtrim(rtrim(number_format($c['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Top 5 Items with Lowest Stock</h2></div>
            <div class="adm-table-wrap">
                <!-- min-width overridden inline, and "ir-compact-table"
                     (see the inline <style> in <head>) tightens
                     padding/font-size — together these keep this 4-column
                     table inside its narrow 3rd-of-3 chart-card column
                     without a horizontal scrollbar. -->
                <table class="adm-table ir-compact-table" style="min-width:0;" data-sortable="true">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowestStockItems as $item): $status = $stockStatusMeta[$item['status']] ?? ['label' => ucfirst($item['status']), 'bg' => '#EEF0F3', 'color' => '#6B7280']; ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['category']) ?></td>
                            <td><?= (int) $item['currentStock'] ?></td>
                            <td><span class="arp-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ============ STOCK MOVEMENT SUMMARY + ITEMS EXPIRING SOON ============
         .adm-grid-70-30 gives these two tables balanced-looking columns
         (rather than a strict 50/50 split) — the same layout class
         Overview's own bottom row uses. -->
    <div class="adm-grid-70-30">

        <div class="adm-card">
            <div class="adm-card-header"><h2>Stock Movement Summary</h2></div>
            <!-- min-width overridden inline (same reasoning as the Top 5
                 Lowest Stock table above): .adm-table's default 640px
                 min-width doesn't fit the 30% column here without forcing
                 a horizontal scrollbar, even though 6 short columns fit
                 fine narrower than that once cells can wrap. -->
            <div class="adm-table-wrap">
                <table class="adm-table" style="min-width:0;" data-sortable="true">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Items</th>
                            <th>Opening Stock</th>
                            <th>Received</th>
                            <th>Issued</th>
                            <th>Closing Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockMovementSummary as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= number_format($row['items']) ?></td>
                            <td><?= number_format($row['opening']) ?></td>
                            <td><?= number_format($row['received']) ?></td>
                            <td><?= number_format($row['issued']) ?></td>
                            <td><?= number_format($row['closing']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold" data-no-sort>
                            <td>Total</td>
                            <td><?= number_format($stockMovementTotal['items']) ?></td>
                            <td><?= number_format($stockMovementTotal['opening']) ?></td>
                            <td><?= number_format($stockMovementTotal['received']) ?></td>
                            <td><?= number_format($stockMovementTotal['issued']) ?></td>
                            <td><?= number_format($stockMovementTotal['closing']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="adm-card">
            <div class="adm-card-header"><h2>Items Expiring Soon (Next 30 Days)</h2></div>
            <!-- min-width overridden inline — same reasoning as the two
                 tables above. -->
            <div class="adm-table-wrap">
                <table class="adm-table" style="min-width:0;" data-sortable="true">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Batch No.</th>
                            <th>Expiry Date</th>
                            <th>Days Left</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($itemsExpiringSoon)): ?>
                        <tr data-no-sort>
                            <td colspan="6" class="adm-table-empty">No items expiring in the next 30 days.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($itemsExpiringSoon as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['category']) ?></td>
                            <td><?= htmlspecialchars($item['batchNo']) ?></td>
                            <td><?= htmlspecialchars($item['expiryDate']) ?></td>
                            <td><?= (int) $item['daysLeft'] ?></td>
                            <td><?= (int) $item['currentStock'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Inventory data is based on transactions recorded within the selected date range and health center.</span>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script id="reportDataScript">
    // Hands all chart numbers to assets/js/inventory_reports.js without it
    // needing to parse PHP-rendered HTML — same pattern as
    // assets/js/analytics_reports.js. The id is what
    // assets/js/ajax-report-nav.js re-executes after a smooth content
    // swap (mutating a <script>'s text never re-runs it, so it creates a
    // fresh element instead).
    window.INVENTORY_REPORTS_DATA = <?= json_encode([
        'inventoryStatusOverview' => [
            'labels' => array_column($inventoryStatusOverview, 'label'),
            'values' => array_column($inventoryStatusOverview, 'value'),
            'colors' => array_column($inventoryStatusOverview, 'color'),
        ],
        'inventoryByCategory' => [
            'labels' => array_column($inventoryByCategory, 'label'),
            'values' => array_column($inventoryByCategory, 'value'),
            'colors' => array_column($inventoryByCategory, 'color'),
        ],
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/sortable-table.js') ?>"></script>
<script src="<?= asset_url('/assets/js/inventory_reports.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-report-nav.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>
