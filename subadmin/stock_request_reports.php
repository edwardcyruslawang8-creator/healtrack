<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Stock Request Reports (subadmin/stock_request_reports.php)
 * ---------------------------------------------------------------------
 * The "Stock Request Reports" tab of the Analytics & Reports section —
 * a sibling page to subadmin/analytics_reports.php ("Overview"),
 * subadmin/patient_reports.php ("Patient Reports"), and
 * subadmin/inventory_reports.php ("Inventory Reports"), reached via
 * those pages' report tabs (and vice versa). Reuses the exact same
 * shell as the rest of subadmin/*.php (280px dark-red gradient
 * sidebar, "adm-" prefixed atoms, assets/css/admin-dashboard.css), the
 * same chart-card/legend/canvas-box patterns as subadmin/dashboard.php
 * ("sad-" prefixed, assets/css/subadmin_dashboard.css), and the same
 * breadcrumb/tabs/section-intro/filter-toolbar/KPI-card/footer-notice
 * styling as analytics_reports.php ("arp-" prefixed,
 * assets/css/analytics_reports.css). Like inventory_reports.php, this
 * page adds no page-specific external stylesheet of its own — the
 * handful of things unique to it (the bottom row's ~55/45 split and
 * its two dense tables) are a small inline <style> block in <head>
 * instead, for the reliability reasons noted in
 * subadmin/patient_reports.php's header comment.
 *
 * CONNECTED to real Firestore data, scoped to this sub-admin's own
 * facility — see includes/subadmin/stock_request_reports_firestore.php's
 * header comment for exactly which stat cards/charts/tables are real
 * vs. still placeholder (Request Fulfillment Summary only). The filter
 * toolbar (date range / status / category / requested by) is real now.
 * Recent Stock Requests has real pagination
 * (includes/shared/pagination_helpers.php) and both tables on this page
 * have real click-to-sort columns (assets/js/sortable-table.js). The
 * Requests by Status / Requests by Category charts
 * are drawn with Chart.js from data JSON-encoded into the page (see
 * assets/js/stock_request_reports.js) — same approach as
 * assets/js/analytics_reports.js.
 *
 * The "Recent Stock Requests" card (includes/subadmin/_srr_recent_requests_card.php)
 * is its own AJAX island for paging: assets/js/srr-recent-pagination.js
 * intercepts that card's own pagination links and fetches
 * api/stock-request-reports-recent-table.php, swapping in just that
 * card's markup — registered ahead of assets/js/ajax-report-nav.js
 * below so paging through 5 rows at a time doesn't re-swap this whole
 * page's <main> (every stat card, both doughnuts, and the Request
 * Fulfillment Summary table too) the way every OTHER filter/pagination
 * control on this page still correctly does.
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

require_once __DIR__ . '/../includes/subadmin/stock_request_reports_firestore.php';
// $adminProfile, $analyticsDateRangeLabel, $facilityOptions, $analyticsTabs,
// $statusFilterOptions, $categoryFilterOptions, $requestedByFilterOptions,
// $stockRequestStatCards, $requestsTotal,
// $requestsByStatus, $requestsByCategory, $requestsByCategoryTotal,
// $recentRequests, $requestStatusMeta,
// $recentRequestsTotal, $recentRequestsPerPage, $recentRequestsCurrentPage,
// $recentRequestsTotalPages, $fulfillmentSummary, $fulfillmentTotal

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
<title>Stock Request Reports — HEALTrack</title>

<!-- Poppins (self-hosted — see assets/css/fonts-poppins.css header comment) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/fonts-poppins.css') ?>">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (used for the Total Requests stat card's fa-file-medical icon) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, donuts, table, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the chart-card/legend/canvas-box patterns -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Analytics & Reports styles, reused here for the breadcrumb/tabs/section-intro/filter-toolbar/KPI-card/footer notice -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
<style>
/* Bottom row (Recent Stock Requests / Request Fulfillment Summary) —
   a ~55/45 split (closer to the reference design's near-even columns
   than .adm-grid-70-30's 1.7fr/1fr) with min-width:0 on each column so
   neither table's own content can force it wider than its share (see
   assets/css/admin-dashboard.css's .adm-grid-70-30 rule for the same
   fix, and subadmin/patient_reports.php's header comment for why
   page-specific rules like this live inline rather than in a per-page
   stylesheet).
*/
.srr-grid-split{ display: grid; grid-template-columns: 1.15fr 1fr; gap: 20px; align-items: start; }
.srr-grid-split > *{ min-width: 0; }
@media (max-width: 1199.98px){ .srr-grid-split{ grid-template-columns: 1fr; } }

/* Requests by Status / Requests by Category — 2 equal donut-chart
   cards (the Requests by Barangay chart that used to make this a
   3-column .sad-row-3 was removed). */
.srr-row-2{ display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 22px; align-items: stretch; }
.srr-row-2 > *{ min-width: 0; }
@media (max-width: 767.98px){ .srr-row-2{ grid-template-columns: 1fr; } }

/* Both bottom tables are dense (7 and 6 columns) — tighter padding/font
   than .adm-table's defaults keeps them inside their column without a
   horizontal scrollbar (same reasoning as inventory_reports.php's
   .ir-compact-table). */
.srr-compact-table th,
.srr-compact-table td{ padding: 10px 8px; font-size: 12px; }
.srr-compact-table .arp-badge{ padding: 3px 8px; font-size: 10px; }

/* Dimmed while assets/js/srr-recent-pagination.js is fetching a new
   page of the Recent Stock Requests card — same convention as
   assets/css/history.css's #hisRecordTableWrap.his-table-loading. */
#srrRecentRequestsCard.srr-card-loading{ opacity: .5; pointer-events: none; transition: opacity .1s ease; }
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
     patient_reports.php / inventory_reports.php / dashboard.php, no
     structural changes.
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
                <span class="arp-breadcrumb-current arp-breadcrumb-current-red">Stock Request Reports</span>
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
        <h2>Stock Request Reports</h2>
        <p>View summaries and details of stock requests made by staff and their fulfillment status.</p>
    </div>

    <!-- ============ FILTER TOOLBAR ============
         All real now — see includes/subadmin/stock_request_reports_firestore.php's
         header comment for exactly what each one filters. -->
    <div class="arp-filter-bar">
        <?php $drfState = $srDateFilter; $drfBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/date_range_filter_dropdown.php'; ?>

        <?php $sfdOptions = $statusFilterOptions; $sfdParam = 'status'; $sfdCurrent = $srStatusFilter; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <?php $sfdOptions = $categoryFilterOptions; $sfdParam = 'category'; $sfdCurrent = $srCategoryFilter; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <?php $sfdOptions = $requestedByFilterOptions; $sfdParam = 'requestedBy'; $sfdCurrent = $srRequestedByFilter; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <span class="arp-filter-spacer"></span>
    </div>

    <!-- ============ SUMMARY STAT CARDS ============
         Reuses analytics_reports.php's Overview-tab KPI card classes
         verbatim (.adm-card.arp-kpi-card / .arp-kpi-icon / .arp-kpi-text
         / .arp-kpi-label / .arp-kpi-value / .arp-kpi-trend) — 5 cards
         fits .arp-kpi-row's native 5-up grid with no override needed. -->
    <div class="arp-kpi-row">
        <?php foreach ($stockRequestStatCards as $stat): ?>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <?php if ($stat['iconLib'] === 'fa'): ?>
                <i class="<?= htmlspecialchars($stat['icon']) ?>"></i>
                <?php else: ?>
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
                <?php endif; ?>
            </div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label"><?= htmlspecialchars($stat['label']) ?></span>
                <span class="arp-kpi-value"><?= number_format((int) $stat['value']) ?></span>
                <span class="arp-kpi-trend"><?= htmlspecialchars($stat['sub']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ BY STATUS + BY CATEGORY ============ -->
    <div class="srr-row-2">

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Requests by Status</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="requestsByStatusChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($requestsTotal) ?></div>
                        <div class="adm-donut-total-label">Total Requests</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($requestsByStatus as $s): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($s['value']) ?> (<?= rtrim(rtrim(number_format($s['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Requests by Category</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="requestsByCategoryChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($requestsByCategoryTotal) ?></div>
                        <div class="adm-donut-total-label">Total Requests</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($requestsByCategory as $c): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($c['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($c['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($c['value']) ?> (<?= rtrim(rtrim(number_format($c['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ============ RECENT STOCK REQUESTS + REQUEST FULFILLMENT SUMMARY ============ -->
    <div class="srr-grid-split">

        <?php require __DIR__ . '/../includes/subadmin/_srr_recent_requests_card.php'; ?>

        <div class="adm-card">
            <div class="adm-card-header"><h2>Request Fulfillment Summary</h2></div>
            <div class="adm-table-wrap">
                <table class="adm-table srr-compact-table" style="min-width:0;" data-sortable="true">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total</th>
                            <th>Fulfilled</th>
                            <th>Partial</th>
                            <th>Unfulfilled</th>
                            <th data-no-sort>Fulfillment Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fulfillmentSummary as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= number_format($row['total']) ?></td>
                            <td><?= number_format($row['fulfilled']) ?></td>
                            <td><?= number_format($row['partial']) ?></td>
                            <td><?= number_format($row['unfulfilled']) ?></td>
                            <td>
                                <div class="small fw-semibold mb-1"><?= rtrim(rtrim(number_format($row['rate'], 1), '0'), '.') ?>%</div>
                                <div class="progress" style="height:6px; background:var(--adm-bg);">
                                    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width:<?= round($row['rate']) ?>%;" aria-valuenow="<?= round($row['rate']) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold" data-no-sort>
                            <td>Total</td>
                            <td><?= number_format($fulfillmentTotal['total']) ?></td>
                            <td><?= number_format($fulfillmentTotal['fulfilled']) ?></td>
                            <td><?= number_format($fulfillmentTotal['partial']) ?></td>
                            <td><?= number_format($fulfillmentTotal['unfulfilled']) ?></td>
                            <td>
                                <div class="small fw-semibold mb-1"><?= rtrim(rtrim(number_format($fulfillmentTotal['rate'], 1), '0'), '.') ?>%</div>
                                <div class="progress" style="height:6px; background:var(--adm-bg);">
                                    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width:<?= round($fulfillmentTotal['rate']) ?>%;" aria-valuenow="<?= round($fulfillmentTotal['rate']) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Stock request data is based on requests submitted within the selected date range and health center.</span>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= asset_url('/assets/js/chart-center-label.js') ?>"></script>

<script id="reportDataScript">
    // Hands all chart numbers to assets/js/stock_request_reports.js
    // without it needing to parse PHP-rendered HTML — same pattern as
    // assets/js/analytics_reports.js. The id is what
    // assets/js/ajax-report-nav.js re-executes after a smooth content
    // swap (mutating a <script>'s text never re-runs it, so it creates a
    // fresh element instead).
    window.STOCK_REQUEST_REPORTS_DATA = <?= json_encode([
        'requestsByStatus' => [
            'labels' => array_column($requestsByStatus, 'label'),
            'values' => array_column($requestsByStatus, 'value'),
            'colors' => array_column($requestsByStatus, 'color'),
        ],
        'requestsByCategory' => [
            'labels' => array_column($requestsByCategory, 'label'),
            'values' => array_column($requestsByCategory, 'value'),
            'colors' => array_column($requestsByCategory, 'color'),
        ],
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/sortable-table.js') ?>"></script>
<script src="<?= asset_url('/assets/js/stock_request_reports.js') ?>"></script>
<!-- Registered before ajax-report-nav.js below so its own click
     listener (which checks e.defaultPrevented) sees this one's
     preventDefault() first — see srr-recent-pagination.js's header
     comment for why "Recent Stock Requests" paging needs to win over
     that page-wide handler instead of triggering a full <main> swap. -->
<script src="<?= asset_url('/assets/js/srr-recent-pagination.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-report-nav.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>
