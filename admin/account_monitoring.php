<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Account Monitoring (admin/account_monitoring.php)
 * ---------------------------------------------------------------------
 * Reuses the same sidebar/topbar shell as the other admin pages
 * (assets/css/admin-dashboard.css, "adm-" prefixed) plus its own styles
 * for the stat cards, analytics charts, activity table, and attention
 * panel (assets/css/account_monitoring.css, "acm-" prefixed).
 *
 * $accountStats/$accountStatusOverview/$accountsByRole all read live
 * from Firestore — see includes/admin/account_monitoring_firestore.php's
 * header comment for the collections behind each. The "Accounts Over
 * Time" range dropdown and the "View" links on the attention panel are
 * both real now too — the range dropdown re-fetches and re-draws the
 * chart via assets/js/account-monitoring-ajax.js + api/get-accounts-
 * over-time.php, and each "View" link jumps straight to "All User
 * Accounts" below, pre-filtered to that real status (?acct_status=...,
 * the same filter that table's own Status dropdown applies) — except
 * "Password Expiring Soon", which has no View link since no
 * password-expiry policy exists yet to filter by (see its own desc).
 * The "All User Accounts" table further down IS real — a System
 * Administrator can search/filter/page through every account in the
 * system there, not just the aggregate counts/charts above it. (A
 * "Recent Account Activities" table used to sit above "Accounts Needing
 * Attention" — removed by request; admin/audit_logs.php already covers
 * that same real audit-log feed in full, with real search/filters.)
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php';          // t()

// Only a signed-in admin (see api/session-login.php) may view this page.
if (($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/admin/account_monitoring_firestore.php';
// $adminProfile, $accountStats, $accountStatusTotal, $accountStatusOverview,
// $accountsOverTimeRange, $accountsOverTime, $accountsOverTimeSeries,
// $accountsByRoleTotal, $accountsByRole, $attentionItems, $allAccountRows,
// $accountRowsPage, ...accounts-table filters/pagination, hc_acct_query()

// Sidebar nav — Account Monitoring is active on this page.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php'],    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php'],
    ['icon' => 'bi-geo-alt-fill',        'label' => t('nav.gis_mapping'),         'href' => 'gis_mapping.php'],
    ['icon' => 'bi-shield-lock-fill',    'label' => t('nav.account_monitoring'),  'href' => 'account_monitoring.php', 'active' => true],
    ['icon' => 'bi-bell-fill',           'label' => t('nav.notifications'),       'href' => 'notifications.php', 'badge' => hc_admin_unread_notification_count()],
    ['icon' => 'bi-megaphone-fill',      'label' => 'Announcements',              'href' => 'announcements.php'],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => 'Admin', 'href' => 'audit_logs.php?role=admin'],
            ['label' => 'Sub Admin', 'href' => 'audit_logs.php?role=subadmin'],
            ['label' => 'Doctor', 'href' => 'audit_logs.php?role=doctor'],
            ['label' => 'Medical Staff', 'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => 'Medical Technologist', 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => 'Radiologic Technologist', 'href' => 'audit_logs.php?role=radio_technologist'],
            ['label' => 'Pharmacist', 'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-gear-fill',           'label' => t('nav.profile_settings'),    'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Monitoring — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, donuts, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Account Monitoring page styles (stat cards, analytics row, activity table, attention panel) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/account_monitoring.css') ?>">
<!-- Search/filter toolbar styling (.pmg-search/.pmg-filter-select/.pmg-btn-search), reused here for the All User Accounts table below the same way patient_management.php's own toolbar already looks -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_management.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
</head>
<body class="adm-body">

<!-- Mobile sidebar trigger -->
<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<!-- =============================================================
     SIDEBAR
     ============================================================= -->
<aside class="adm-sidebar" id="admSidebar">

    <!-- Brand -->
    <div class="adm-brand">
        <div class="adm-logo-badge"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p><?= t('app.tagline') ?></p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Admin profile -->
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
            <a href="#admNavAuditLogs" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="admNavAuditLogs">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="admNavAuditLogs">
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
                <span class="adm-nav-badge" id="ntfSidebarBadge"><?= (int) $item['badge'] ?></span>
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
            <span><?= t('nav.logout') ?></span>
        </a>
    </div>
</aside>

<!-- =============================================================
     MAIN CONTENT
     ============================================================= -->
<main class="adm-main">

    <!-- ============ BACK LINK ============ -->
    <a href="admin_dashboard.php" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> <?= t('back.dashboard') ?>
    </a>

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= t('page.account_monitoring.title') ?></h1>
            <p class="adm-page-subtitle"><?= t('page.account_monitoring.subtitle') ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Horizontal date + time, same .adm-datetime-widget every
                     other admin page uses — this page used to show a
                     separate, differently-laid-out (and non-functional)
                     date-only dropdown here instead. -->
                <div class="adm-datetime-widget">
                    <span><i class="bi bi-calendar3"></i><span class="adm-dt-date"><?= date('F j, Y') ?></span></span>
                    <span class="adm-dt-sep">|</span>
                    <span><i class="bi bi-clock"></i><span class="adm-dt-time"><?= date('g:i A') ?></span></span>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_admin_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_admin_unread_notification_count() ?></span><?php endif; ?>
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
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i><?= t('nav.profile_settings') ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= t('nav.logout') ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="acm-stats-row">
        <?php foreach ($accountStats as $stat): ?>
        <div class="acm-stat-card">
            <div class="acm-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div>
                <div class="acm-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
                <div class="acm-stat-value"><?= number_format((int) $stat['value']) ?></div>
                <div class="acm-stat-sub">
                    <?= htmlspecialchars($stat['sub']) ?>
                    <?php if (isset($stat['growth'])): ?>
                    <span class="acm-stat-growth <?= $stat['trend'] === 'down' ? 'down' : 'up' ?>">
                        <i class="bi <?= $stat['trend'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i><?= (int) $stat['growth'] ?>%
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ ANALYTICS: STATUS OVERVIEW + OVER TIME + BY ROLE ============ -->
    <div class="acm-analytics-row">

        <!-- Account Status Overview -->
        <div class="adm-card">
            <div class="adm-card-header"><h2>Account Status Overview</h2></div>

            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="acmStatusDonut"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($accountStatusTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="acm-donut-legend">
                <?php foreach ($accountStatusOverview as $s): ?>
                <div class="acm-legend-row">
                    <span class="acm-legend-dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                    <span class="acm-legend-label"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="acm-legend-value"><?= (int) $s['value'] ?> (<?= rtrim(rtrim(number_format($s['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Accounts Over Time -->
        <div class="adm-card acm-trend-card">
            <div class="adm-card-header">
                <h2>Accounts Over Time</h2>
                <div class="dropdown">
                    <button class="acm-range-btn dropdown-toggle" type="button" id="acmTrendRangeBtn" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= htmlspecialchars($accountsOverTimeRange) ?>
                    </button>
                    <!-- Real — picking a range re-fetches just $accountsOverTime and
                         re-draws the chart below via assets/js/account-monitoring-ajax.js
                         + api/get-accounts-over-time.php (no page reload); the real
                         ?days=... href on each link is still a correct fallback if JS
                         fails for any reason. -->
                    <ul class="dropdown-menu dropdown-menu-end" id="acmTrendRangeMenu">
                        <?php foreach (['7 Days', '30 Days', '90 Days'] as $rangeOpt): ?>
                        <li><a class="dropdown-item <?= $rangeOpt === $accountsOverTimeRange ? 'active' : '' ?>" data-range="<?= htmlspecialchars($rangeOpt) ?>" href="?<?= hc_acm_trend_query($rangeOpt) ?>"><?= htmlspecialchars($rangeOpt) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="acm-trend-legend">
                <?php foreach ($accountsOverTimeSeries as $series): ?>
                <span><span class="dot" style="background:<?= htmlspecialchars($series['color']) ?>;"></span> <?= htmlspecialchars($series['label']) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="acm-trend-canvas-box">
                <canvas id="acmAccountsOverTimeChart"></canvas>
            </div>
        </div>

        <!-- Account Distribution by Role -->
        <div class="adm-card">
            <div class="adm-card-header"><h2>Account Distribution by Role</h2></div>

            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="acmRoleDonut"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($accountsByRoleTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="acm-donut-legend">
                <?php foreach ($accountsByRole as $r): ?>
                <div class="acm-legend-row">
                    <span class="acm-legend-dot" style="background:<?= htmlspecialchars($r['color']) ?>;"></span>
                    <span class="acm-legend-label"><?= htmlspecialchars($r['label']) ?></span>
                    <span class="acm-legend-value"><?= (int) $r['value'] ?> (<?= rtrim(rtrim(number_format($r['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ============ ALL USER ACCOUNTS (real — search/filter/pagination) ============ -->
    <div class="adm-card acm-accounts-card">
        <div class="adm-card-header">
            <h2>All User Accounts</h2>
        </div>

        <!-- assets/js/account-monitoring-ajax.js intercepts this form's
             submit + both selects' change (real form.submit() calls never
             fire the 'submit' event at all, so the selects must be driven
             by their own 'change' listener rather than onchange="this.form.submit()") -->
        <form class="pmg-toolbar" id="acmAccountsForm" method="get" action="account_monitoring.php">
            <div class="pmg-search">
                <i class="bi bi-search"></i>
                <input type="text" name="acct_q" value="<?= htmlspecialchars($accountsSearchTerm) ?>" placeholder="Search by name, email, or facility..." autocomplete="off">
            </div>

            <select class="form-select pmg-filter-select" name="acct_role" aria-label="Filter by role">
                <?php foreach ($accountRoleOptions as $r): ?>
                <option <?= $r === $accountsRoleFilter ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
            </select>

            <select class="form-select pmg-filter-select" name="acct_status" aria-label="Filter by status">
                <?php foreach ($accountStatusOptions as $s): ?>
                <option <?= $s === $accountsStatusFilter ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="pmg-btn-search"><i class="bi bi-search"></i> Search</button>
        </form>

        <!-- AJAX-refreshed zone (assets/js/account-monitoring-ajax.js +
             api/get-account-rows-table.php, sharing this exact markup via
             includes/admin/_account_rows_table.php) — searching, changing
             Role/Status, or paging only ever swaps what's inside this div,
             never reloads the whole page. -->
        <div id="acmAccountsResults">
            <?php require __DIR__ . '/../includes/admin/_account_rows_table.php'; ?>
        </div>
    </div>

    <!-- ============ ACCOUNTS NEEDING ATTENTION ============ -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h2>Accounts Needing Attention</h2>
        </div>

        <div class="acm-attention-list">
            <?php foreach ($attentionItems as $item): ?>
            <div class="acm-attention-row">
                <span class="acm-attention-icon" style="background:<?= htmlspecialchars($item['iconBg']) ?>; color:<?= htmlspecialchars($item['iconColor']) ?>;">
                    <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                </span>
                <span class="acm-attention-body">
                    <span class="acm-attention-title"><?= htmlspecialchars($item['label']) ?></span>
                    <span class="acm-attention-desc"><?= htmlspecialchars($item['desc']) ?></span>
                </span>
                <span class="acm-attention-count"><?= (int) $item['count'] ?></span>
                <?php if ($item['href'] !== null): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>" class="acm-attention-view">View</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <p class="acm-footnote">Note: Percentage changes compare current month data to the previous month.</p>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
    // Hands all chart numbers to assets/js/account_monitoring.js without it
    // needing to parse PHP-rendered HTML — same pattern as admin_dashboard.php
    // and facility_analytics.php.
    window.ACCOUNT_MONITORING_DATA = <?= json_encode([
        'statusOverview' => [
            'labels' => array_column($accountStatusOverview, 'label'),
            'values' => array_column($accountStatusOverview, 'value'),
            'colors' => array_column($accountStatusOverview, 'color'),
        ],
        'overTime' => [
            'labels' => $accountsOverTime['labels'],
            'series' => array_map(fn ($s) => [
                'label' => $s['label'],
                'color' => $s['color'],
                'data' => $accountsOverTime[$s['key']],
            ], $accountsOverTimeSeries),
        ],
        'byRole' => [
            'labels' => array_column($accountsByRole, 'label'),
            'values' => array_column($accountsByRole, 'value'),
            'colors' => array_column($accountsByRole, 'color'),
        ],
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/account_monitoring.js') ?>"></script>
<script src="<?= asset_url('/assets/js/account-monitoring-ajax.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
