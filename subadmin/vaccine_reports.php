<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Vaccine Reports (subadmin/vaccine_reports.php)
 * ---------------------------------------------------------------------
 * The "Vaccine Reports" tab of the Analytics & Reports section — the
 * last of five sibling report pages (subadmin/analytics_reports.php
 * "Overview", subadmin/patient_reports.php, subadmin/inventory_reports.php,
 * subadmin/stock_request_reports.php, subadmin/consultation_reports.php),
 * reached via those pages' report tabs (and vice versa). Reuses the
 * exact same shell as the rest of subadmin/*.php (280px dark-red
 * gradient sidebar, "adm-" prefixed atoms, assets/css/admin-dashboard.css),
 * the same chart-card/legend/canvas-box patterns as subadmin/dashboard.php
 * ("sad-" prefixed, assets/css/subadmin_dashboard.css), and the same
 * breadcrumb/tabs/section-intro/filter-toolbar/KPI-card/footer-notice
 * styling as analytics_reports.php ("arp-" prefixed,
 * assets/css/analytics_reports.css). Like the other report tabs, this
 * page adds no page-specific external stylesheet — the handful of
 * things unique to it (the bottom row's split, its two dense tables,
 * and the coverage badge/progress-bar helpers) are a small inline
 * <style> block in <head> instead, for the reliability reasons noted
 * in subadmin/patient_reports.php's header comment.
 *
 * CONNECTED to real Firestore data now, scoped to this sub-admin's own
 * facility — see includes/subadmin/vaccine_reports_firestore.php's
 * header comment (including why "Fully/Partially Vaccinated" and
 * "Coverage (%)" were replaced with real Completed/Pending counts and a
 * real Completion Rate — the original shape assumed an immunization-
 * completeness model and a target population this data doesn't have).
 * Every filter is real: date range / Vaccine Type / Status filter the
 * WHOLE page; Barangay stays a "table-only" filter. Both tables have
 * real click-to-sort columns (assets/js/sortable-table.js) and real
 * pagination (includes/shared/pagination_helpers.php). The
 * Doses by Vaccine Type / Doses Administered by Age Group /
 * Doses Administered Trend charts are drawn with Chart.js from data
 * JSON-encoded into the page (see assets/js/vaccine_reports.js) — same
 * approach as assets/js/analytics_reports.js.
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

require_once __DIR__ . '/../includes/subadmin/vaccine_reports_firestore.php';
// $adminProfile, $analyticsDateRangeLabel, $facilityOptions, $analyticsTabs,
// $vaccineTypeFilterOptions, $statusFilterOptions, $ageGroupFilterOptions,
// $barangayFilterOptions, $vaccineStatCards, $dosesByVaccineType,
// $dosesByVaccineTypeTotal, $coverageByAgeGroup, $dosesAdministeredTrend,
// $vaccineAdministrationSummary, $vaccineAdministrationTotal,
// $vaccineAdministrationEntries, $vaccineAdministrationPerPage,
// $vaccineAdministrationCurrentPage, $vaccineAdministrationTotalPages,
// $vaccinationByBarangay, $vaccinationByBarangayTotal,
// $vaccinationByBarangayPerPage, $vaccinationByBarangayCurrentPage,
// $vaccinationByBarangayTotalPages

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
            ['label' => t('nav.audit_logs_doctor'),             'href' => 'audit_logs.php?role=doctor'],
            ['label' => t('nav.audit_logs_medstaff'),      'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => t('nav.audit_logs_medtech'), 'href' => 'audit_logs.php?role=medical_technician'],
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

// Coverage badge color tiers for the Vaccine Administration Summary
// table — green at/above 50%, amber below (matches the reference's
// "Others (HPV, MMR, etc.)" row at 34.8% reading as a warning, not a
// pass, versus every other row's green ≥60%+ badge).
function vaccine_coverage_badge(float $pct): array
{
    return $pct >= 50
        ? ['bg' => '#E4F7EA', 'color' => '#1E8E3E']
        : ['bg' => '#FDECDD', 'color' => '#E8710A'];
}
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vaccine Reports — HEALTrack</title>

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
<!-- Sub-Admin Dashboard styles, reused here for the chart-card/legend/canvas-box patterns -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Analytics & Reports styles, reused here for the breadcrumb/tabs/section-intro/filter-toolbar/KPI-card/footer notice -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
<style>
/* Bottom row (Vaccine Administration Summary / Vaccination by Barangay)
   — near-even columns, with min-width:0 on each column so neither
   table's own content can force it wider than its share (same fix as
   .adm-grid-70-30 in assets/css/admin-dashboard.css — see that rule's
   comment for the full explanation, and subadmin/patient_reports.php's
   header comment for why page-specific rules like this live inline
   rather than in a per-page stylesheet). */
.vr-grid-split{ display: grid; grid-template-columns: 1.1fr 1fr; gap: 20px; align-items: start; }
.vr-grid-split > *{ min-width: 0; }
@media (max-width: 1199.98px){ .vr-grid-split{ grid-template-columns: 1fr; } }

/* Both bottom tables are dense (6 and 4 columns, the latter carrying a
   progress bar) — tighter padding/font than .adm-table's defaults keeps
   them inside their column without a horizontal scrollbar (same
   reasoning as inventory_reports.php's .ir-compact-table). */
.vr-compact-table th,
.vr-compact-table td{ padding: 10px 8px; font-size: 12px; }
.vr-compact-table .arp-badge{ padding: 3px 8px; font-size: 10.5px; }

/* Vaccination by Barangay's Coverage (%) cell: a green bar beside the
   percentage figure, sized to the percentage itself. */
.vr-pct-bar-wrap{ display: flex; align-items: center; gap: 8px; }
.vr-pct-bar-track{ flex: 1; height: 6px; border-radius: 50px; background: var(--adm-bg); overflow: hidden; min-width: 40px; }
.vr-pct-bar-fill{ height: 100%; border-radius: 50px; background: var(--adm-green); }
.vr-pct-value{ flex-shrink: 0; font-weight: 600; color: var(--adm-text); white-space: nowrap; }
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
     patient_reports.php / inventory_reports.php /
     stock_request_reports.php / consultation_reports.php /
     dashboard.php, no structural changes.
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
        <div class="adm-avatar"><i class="bi bi-person-circle"></i></div>
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
                <span class="arp-breadcrumb-current arp-breadcrumb-current-red">Vaccine Reports</span>
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
                        <span class="adm-topbar-avatar"><i class="bi bi-person-fill"></i></span>
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
        <h2>Vaccine Reports</h2>
        <p>View vaccination coverage, administered vaccines, and status summaries.</p>
    </div>

    <!-- ============ FILTER TOOLBAR ============
         All real now — see includes/subadmin/vaccine_reports_firestore.php's
         header comment for exactly what each one filters (Status is
         Completed/Pending now, not the old "Fully/Partially Vaccinated"
         — that needs an immunization-completeness model this data
         doesn't have). Age Group isn't a toolbar filter here — it drives
         its own "Doses Administered by Age Group" chart below instead. -->
    <div class="arp-filter-bar">
        <?php $drfState = $vrDateFilter; $drfBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/date_range_filter_dropdown.php'; ?>

        <?php $sfdOptions = $vaccineTypeFilterOptions; $sfdParam = 'vaccineType'; $sfdCurrent = $vrTypeFilterLabel; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <?php $sfdOptions = $statusFilterOptions; $sfdParam = 'status'; $sfdCurrent = $vrStatusFilterLabel; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <?php $sfdOptions = $barangayFilterOptions; $sfdParam = 'barangay'; $sfdCurrent = $vrBarangayFilter; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <span class="arp-filter-spacer"></span>
    </div>

    <!-- ============ SUMMARY STAT CARDS ============
         Reuses analytics_reports.php's Overview-tab KPI card classes
         verbatim (.adm-card.arp-kpi-card / .arp-kpi-icon / .arp-kpi-text
         / .arp-kpi-label / .arp-kpi-value / .arp-kpi-trend) — 5 cards
         fits .arp-kpi-row's native 5-up grid with no override needed. -->
    <div class="arp-kpi-row">
        <?php foreach ($vaccineStatCards as $stat): ?>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label"><?= htmlspecialchars($stat['label']) ?></span>
                <span class="arp-kpi-value"><?= $stat['valueSuffix'] === '%' ? rtrim(rtrim(number_format($stat['value'], 1), '0'), '.') . '%' : number_format((int) $stat['value']) ?></span>
                <?php if (isset($stat['trend'])): ?>
                <span class="arp-kpi-trend <?= $stat['trend'] === 'down' ? 'down' : 'up' ?>">
                    <i class="bi <?= $stat['trend'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i> <?= htmlspecialchars($stat['sub']) ?>
                </span>
                <?php else: ?>
                <span class="arp-kpi-trend"><?= htmlspecialchars($stat['sub']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ BY VACCINE TYPE + BY AGE GROUP + TREND ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Doses Administered by Vaccine Type</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="dosesByVaccineTypeChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($dosesByVaccineTypeTotal) ?></div>
                        <div class="adm-donut-total-label">Total Doses</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($dosesByVaccineType as $v): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($v['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($v['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($v['value']) ?> (<?= rtrim(rtrim(number_format($v['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Doses Administered by Age Group</h2></div>
            <div class="sad-canvas-box">
                <canvas id="coverageByAgeGroupChart"></canvas>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Doses Administered Trend</h2></div>
            <div class="sad-canvas-box">
                <canvas id="dosesAdministeredTrendChart"></canvas>
            </div>
        </div>

    </div>

    <!-- ============ VACCINE ADMINISTRATION SUMMARY + BY BARANGAY ============ -->
    <div class="vr-grid-split">

        <div class="adm-card">
            <div class="adm-card-header"><h2>Vaccine Administration Summary</h2></div>
            <div class="adm-table-wrap">
                <table class="adm-table vr-compact-table" style="min-width:0;" data-sortable="true">
                    <thead>
                        <tr>
                            <th>Vaccine Type</th>
                            <th>Total Doses</th>
                            <th>Completed</th>
                            <th>Pending</th>
                            <th>Boosters Given</th>
                            <th data-no-sort>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vaccineAdministrationSummary as $row): $badge = vaccine_coverage_badge($row['coverage']); ?>
                        <tr>
                            <td><?= htmlspecialchars($row['type']) ?></td>
                            <td><?= number_format($row['doses']) ?></td>
                            <td><?= number_format($row['fully']) ?></td>
                            <td><?= number_format($row['partial']) ?></td>
                            <td><?= number_format($row['booster']) ?></td>
                            <td><span class="arp-badge" style="background:<?= htmlspecialchars($badge['bg']) ?>; color:<?= htmlspecialchars($badge['color']) ?>;"><?= rtrim(rtrim(number_format($row['coverage'], 1), '0'), '.') ?>%</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold" data-no-sort>
                            <td>Total</td>
                            <td><?= number_format($vaccineAdministrationTotal['doses']) ?></td>
                            <td><?= number_format($vaccineAdministrationTotal['fully']) ?></td>
                            <td><?= number_format($vaccineAdministrationTotal['partial']) ?></td>
                            <td><?= number_format($vaccineAdministrationTotal['booster']) ?></td>
                            <td><?php $totalBadge = vaccine_coverage_badge($vaccineAdministrationTotal['coverage']); ?>
                                <span class="arp-badge" style="background:<?= htmlspecialchars($totalBadge['bg']) ?>; color:<?= htmlspecialchars($totalBadge['color']) ?>;"><?= rtrim(rtrim(number_format($vaccineAdministrationTotal['coverage'], 1), '0'), '.') ?>%</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="adm-table-footer">
                <span class="adm-showing-text">
                    <?php if ($vaccineAdministrationEntries > 0): $shownFrom = ($vaccineAdministrationCurrentPage - 1) * 5 + 1; $shownTo = $shownFrom + count($vaccineAdministrationSummary) - 1; ?>
                    Showing <?= $shownFrom ?> to <?= $shownTo ?> of <?= number_format($vaccineAdministrationEntries) ?> entries
                    <?php else: ?>
                    No entries to show
                    <?php endif; ?>
                </span>
                <?= hc_render_pagination_nav('Vaccine administration summary pages', 'vasPage', $vaccineAdministrationCurrentPage, $vaccineAdministrationTotalPages) ?>
            </div>
        </div>

        <div class="adm-card">
            <div class="adm-card-header"><h2>Vaccination by Barangay (Top 5)</h2></div>
            <div class="adm-table-wrap">
                <table class="adm-table vr-compact-table" style="min-width:0;" data-sortable="true">
                    <thead>
                        <tr>
                            <th>Barangay</th>
                            <th>Doses</th>
                            <th>Unique Patients</th>
                            <th data-no-sort>Completion Rate (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vaccinationByBarangay as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['barangay']) ?></td>
                            <td><?= number_format($b['doses']) ?></td>
                            <td><?= number_format($b['fully']) ?></td>
                            <td>
                                <div class="vr-pct-bar-wrap">
                                    <div class="vr-pct-bar-track">
                                        <div class="vr-pct-bar-fill" style="width:<?= round($b['coverage']) ?>%;"></div>
                                    </div>
                                    <span class="vr-pct-value"><?= rtrim(rtrim(number_format($b['coverage'], 1), '0'), '.') ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="adm-table-footer">
                <span class="adm-showing-text">
                    <?php if ($vaccinationByBarangayTotal > 0): $shownFrom = ($vaccinationByBarangayCurrentPage - 1) * 5 + 1; $shownTo = $shownFrom + count($vaccinationByBarangay) - 1; ?>
                    Showing <?= $shownFrom ?> to <?= $shownTo ?> of <?= number_format($vaccinationByBarangayTotal) ?> entries
                    <?php else: ?>
                    No entries to show
                    <?php endif; ?>
                </span>
                <?= hc_render_pagination_nav('Vaccination by barangay pages', 'vbbPage', $vaccinationByBarangayCurrentPage, $vaccinationByBarangayTotalPages) ?>
            </div>
        </div>

    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Vaccine reports include all vaccinations recorded within the selected date range and health center.</span>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script id="reportDataScript">
    // Hands all chart numbers to assets/js/vaccine_reports.js without it
    // needing to parse PHP-rendered HTML — same pattern as
    // assets/js/analytics_reports.js. The id is what
    // assets/js/ajax-report-nav.js re-executes after a smooth content
    // swap (mutating a <script>'s text never re-runs it, so it creates a
    // fresh element instead).
    window.VACCINE_REPORTS_DATA = <?= json_encode([
        'dosesByVaccineType' => [
            'labels' => array_column($dosesByVaccineType, 'label'),
            'values' => array_column($dosesByVaccineType, 'value'),
            'colors' => array_column($dosesByVaccineType, 'color'),
        ],
        'coverageByAgeGroup' => [
            'labels' => array_column($coverageByAgeGroup, 'label'),
            'values' => array_column($coverageByAgeGroup, 'value'),
            'colors' => array_column($coverageByAgeGroup, 'color'),
        ],
        'dosesAdministeredTrend' => $dosesAdministeredTrend,
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/sortable-table.js') ?>"></script>
<script src="<?= asset_url('/assets/js/vaccine_reports.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-report-nav.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>
