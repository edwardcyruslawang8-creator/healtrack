<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Reports (subadmin/patient_reports.php)
 * ---------------------------------------------------------------------
 * The "Patient Reports" tab of the Analytics & Reports section — a
 * sibling page to subadmin/analytics_reports.php ("Overview"), reached
 * via that page's report tabs (and vice versa). Reuses the exact same
 * shell as the rest of subadmin/*.php (280px dark-red gradient sidebar,
 * "adm-" prefixed atoms, assets/css/admin-dashboard.css), the same
 * chart-card/legend/canvas-box patterns as subadmin/dashboard.php
 * ("sad-" prefixed, assets/css/subadmin_dashboard.css), and the same
 * breadcrumb/tabs/footer-notice styling as analytics_reports.php
 * ("arp-" prefixed, assets/css/analytics_reports.css) — plus its own
 * filter toolbar / 6-card stat row / diagnosis ranking list
 * ("prp-" prefixed, assets/css/patient_reports.css). Analytics &
 * Reports stays active in the sidebar (this is a sub-page of it), and
 * Patient Reports stays active in the report tabs strip (this IS that
 * page).
 *
 * CONNECTED to real Firestore data, scoped to this sub-admin's own
 * facility — see includes/subadmin/patient_reports_firestore.php's
 * header comment (only Top 5 Diagnoses stays placeholder — no
 * diagnosis data model exists). The filter toolbar (date range / age
 * group / gender / barangay) is real now — real ?param=value links
 * (includes/shared/date_range_filter_dropdown.php /
 * simple_filter_dropdown.php), filtering every stat/chart/list on this
 * page server-side. New Patient Registrations has real pagination
 * (includes/shared/pagination_helpers.php) and every table on this page
 * has real click-to-sort columns (assets/js/sortable-table.js). The
 * Patients by Age Group / Patients by Gender /
 * Patients by Barangay charts are drawn with Chart.js from data
 * JSON-encoded into the page (see assets/js/patient_reports.js) — same
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

require_once __DIR__ . '/../includes/subadmin/patient_reports_firestore.php';
// $adminProfile, $analyticsDateRangeLabel, $facilityOptions, $analyticsTabs,
// $ageGroupFilterOptions, $genderFilterOptions, $barangayFilterOptions,
// $patientStatCards, $patientsByAgeGroup, $patientsByAgeGroupTotal,
// $patientsByGenderReport, $patientsByGenderReportTotal,
// $patientsByBarangay, $newPatientRegistrations, $registrationsTotal,
// $registrationsPerPage, $registrationsCurrentPage, $registrationsTotalPages,
// $topDiagnosesAll, $topDiagnosesAllTotal

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
<title>Patient Reports — HEALTrack</title>

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
<!-- Analytics & Reports styles, reused here for the breadcrumb/tabs/footer notice -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
<!-- Patient Reports page styles (filter toolbar, 6-card stat row, diagnosis ranking) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_reports.css') ?>">
<!--
    The external patient_reports.css link above has repeatedly failed to
    take effect in testing (its rules — e.g. the 6-column stat-card grid
    below — silently don't apply, while every OTHER stylesheet on this
    page loads fine), for reasons that couldn't be pinned down from the
    server side (the file itself serves correctly with the right
    content, MIME type, and no cache-busting issue). Duplicating its
    (now very small) rule set inline here guarantees it applies — an
    inline <style> block is part of this same HTML response, not a
    separate request that can fail/be blocked/be stale independently.
    Keep this in sync with assets/css/patient_reports.css if it's ever
    edited; once the external file is confirmed reliable again, this
    block can be deleted and the <link> above left to do the job alone.
-->
<style>
.prp-breadcrumb-current-red{ color: var(--adm-red-bright) !important; }

.prp-section-intro{ margin-bottom: 18px; }
.prp-section-intro h2{ font-size: 19px; font-weight: 700; color: var(--adm-text); margin: 0 0 4px; }
.prp-section-intro p{ font-size: 13px; color: var(--adm-text-muted); margin: 0; }

.prp-filter-bar{ display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
.prp-filter-bar .dropdown{ flex: 0 0 auto; }
.prp-filter-select{
  display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 40px;
  background: var(--adm-card); border: 1px solid var(--adm-border); border-radius: 10px; padding: 9px 14px;
  box-shadow: var(--adm-shadow); font-size: 12.5px; font-weight: 600; color: var(--adm-text);
  white-space: nowrap; transition: background .15s ease, border-color .15s ease;
}
.prp-filter-select i:first-child{ color: var(--adm-text-soft); font-size: 13.5px; }
.prp-filter-select:hover{ background: var(--adm-hover-bg); }
.prp-filter-select:focus, .prp-filter-select.show{ border-color: var(--adm-red-bright); }
.prp-filter-spacer{ flex: 1 1 auto; }

/* 6-up override of analytics_reports.css's 5-up .arp-kpi-row grid. */
.prp-stats-row-6{ grid-template-columns: repeat(6, 1fr); }
@media (max-width: 1399.98px){ .prp-stats-row-6{ grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 991.98px){ .prp-stats-row-6{ grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575.98px){ .prp-stats-row-6{ grid-template-columns: 1fr; } }

@media (max-width: 991.98px){
  .prp-filter-bar{ align-items: stretch; }
  .prp-filter-spacer{ display: none; }
}
@media (max-width: 767.98px){
  .prp-filter-bar .dropdown{ flex: 1 1 45%; }
  .prp-filter-select{ width: 100%; justify-content: center; }
}
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
                <span class="arp-breadcrumb-current prp-breadcrumb-current-red">Patient Reports</span>
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
    <div class="prp-section-intro">
        <h2>Patient Reports</h2>
        <p>View summaries and statistics about patient demographics, new registrations, and health conditions.</p>
    </div>

    <!-- ============ FILTER TOOLBAR ============
         None of these are wired up yet — no patient aggregation model
         exists (see this file's header comment), so every option below
         is a static placeholder rather than a real filter.

         Every control below carries Bootstrap utility/component classes
         (btn/btn-light/border, d-flex/gap-2, etc.) IN ADDITION TO the
         custom "prp-" classes, so the toolbar still reads as a proper
         set of pill buttons purely off Bootstrap's own CSS even if
         assets/css/patient_reports.css fails to load for any reason —
         the custom classes then layer the finished look on top. -->
    <div class="prp-filter-bar d-flex flex-wrap align-items-center gap-2 mb-4">
        <?php $drfState = $prDateFilter; $drfBtnClass = 'prp-filter-select'; include __DIR__ . '/../includes/shared/date_range_filter_dropdown.php'; ?>

        <?php $sfdOptions = $ageGroupFilterOptions; $sfdParam = 'ageGroup'; $sfdCurrent = $prAgeGroupFilter; $sfdBtnClass = 'prp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <?php $sfdOptions = $genderFilterOptions; $sfdParam = 'gender'; $sfdCurrent = $prGenderFilter; $sfdBtnClass = 'prp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <?php $sfdOptions = $barangayFilterOptions; $sfdParam = 'barangay'; $sfdCurrent = $prBarangayFilter; $sfdBtnClass = 'prp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <span class="prp-filter-spacer d-none d-lg-block"></span>
    </div>

    <!-- ============ SUMMARY STAT CARDS ============
         Reuses analytics_reports.php's Overview-tab KPI card classes
         verbatim (.adm-card.arp-kpi-card / .arp-kpi-icon / .arp-kpi-text
         / .arp-kpi-label / .arp-kpi-value / .arp-kpi-trend, from
         assets/css/analytics_reports.css, already loaded on this page)
         instead of a separate look, so the icon shape (rounded-square,
         not circle), font sizes, and spacing are pixel-identical to the
         Overview tab. Only the 6-up grid (vs. Overview's 5-up
         .arp-kpi-row) is page-specific — see .prp-stats-row-6 below. -->
    <div class="arp-kpi-row prp-stats-row-6">
        <?php foreach ($patientStatCards as $stat): ?>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label"><?= htmlspecialchars($stat['label']) ?></span>
                <span class="arp-kpi-value"><?= number_format((int) $stat['value']) ?></span>
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

    <!-- ============ AGE GROUP + GENDER + BARANGAY ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Patients by Age Group</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="patientsByAgeGroupChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($patientsByAgeGroupTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($patientsByAgeGroup as $ag): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($ag['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($ag['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($ag['value']) ?> (<?= rtrim(rtrim(number_format($ag['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Patients by Gender</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="patientsByGenderChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($patientsByGenderReportTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($patientsByGenderReport as $g): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($g['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($g['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($g['value']) ?> (<?= rtrim(rtrim(number_format($g['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Patients by Barangay (Top 5)</h2></div>
            <div class="arp-ranked-axis-label">Patients</div>
            <div class="sad-condition-list arp-ranked-list arp-ranked-list-stacked">
                <?php foreach ($patientsByBarangay as $b): ?>
                <div class="sad-condition-row">
                    <span class="sad-condition-label"><?= htmlspecialchars($b['label']) ?></span>
                    <div class="sad-condition-bar-track">
                        <div class="sad-condition-bar-fill" style="width:<?= round($b['value'] / $patientsByBarangayMax * 100) ?>%;"></div>
                    </div>
                    <span class="sad-condition-value"><?= number_format($b['value']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="sad-condition-axis">
                <span>0</span><span>100</span><span>200</span><span>300</span>
            </div>
        </div>

    </div>

    <!-- ============ NEW PATIENT REGISTRATIONS + TOP 5 DIAGNOSES ============
         Reuses .adm-grid-70-30 (admin-dashboard.css) — the exact same
         layout class Overview's own bottom row (Monthly Consultation
         Comparison + Recent Stock Requests) uses — instead of a 50/50
         split, so the 6-column registrations table gets enough width to
         lay out without forcing .adm-table's horizontal scrollbar. -->
    <div class="adm-grid-70-30">

        <div class="adm-card">
            <div class="adm-card-header">
                <h2>New Patient Registrations</h2>
                <a href="patient_management.php" class="adm-card-footer-link" style="margin-top:0;">View All</a>
            </div>
            <!-- min-width overridden inline so this table can never force
                 a horizontal scrollbar, even on a narrow viewport where
                 the 70% column shrinks below .adm-table's default 640px
                 min-width — cells wrap instead. -->
            <div class="adm-table-wrap">
                <table class="adm-table" style="min-width:0;" data-sortable="true">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Barangay</th>
                            <th>Date Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($newPatientRegistrations)): ?>
                        <tr data-no-sort>
                            <td colspan="5" class="adm-table-empty">No new registrations for this period.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($newPatientRegistrations as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= (int) $p['age'] ?></td>
                            <td><?= htmlspecialchars($p['gender']) ?></td>
                            <td><?= htmlspecialchars($p['barangay']) ?></td>
                            <td><?= htmlspecialchars($p['dateRegistered']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="adm-table-footer">
                <span class="adm-showing-text">
                    <?php if ($registrationsTotal > 0): $shownFrom = ($registrationsCurrentPage - 1) * 5 + 1; $shownTo = $shownFrom + count($newPatientRegistrations) - 1; ?>
                    Showing <?= $shownFrom ?> to <?= $shownTo ?> of <?= number_format($registrationsTotal) ?> entries
                    <?php else: ?>
                    No entries to show
                    <?php endif; ?>
                </span>
                <?= hc_render_pagination_nav('New patient registrations pages', 'regPage', $registrationsCurrentPage, $registrationsTotalPages) ?>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="adm-card-header">
                <h2>Top 5 Diagnoses (All Patients)</h2>
                <a href="#" class="adm-card-footer-link" style="margin-top:0;">View All</a>
            </div>
            <!-- Same ranked-bar-list widget as Overview's Top 5 Diagnoses
                 (.sad-condition-list, assets/css/subadmin_dashboard.css)
                 and the Patients by Barangay list above, with the value
                 slot widened (inline style) to fit "count (pct%)" instead
                 of .sad-condition-value's default narrow just-a-number
                 width. -->
            <div class="arp-ranked-axis-label">Patients (% of all diagnoses)</div>
            <div class="sad-condition-list arp-ranked-list arp-ranked-list-stacked">
                <?php foreach ($topDiagnosesAll as $d): ?>
                <div class="sad-condition-row">
                    <span class="sad-condition-label"><?= htmlspecialchars($d['label']) ?></span>
                    <div class="sad-condition-bar-track">
                        <div class="sad-condition-bar-fill" style="width:<?= round($d['value'] / $topDiagnosesAllMax * 100) ?>%;"></div>
                    </div>
                    <span class="sad-condition-value" style="width:auto; white-space:nowrap;"><?= number_format($d['value']) ?> (<?= rtrim(rtrim(number_format($d['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Reports are based on the selected date range and health center.</span>
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
    // Hands all chart numbers to assets/js/patient_reports.js without it
    // needing to parse PHP-rendered HTML — same pattern as
    // assets/js/analytics_reports.js. The id is what
    // assets/js/ajax-report-nav.js re-executes after a smooth content
    // swap (mutating a <script>'s text never re-runs it, so it creates a
    // fresh element instead).
    window.PATIENT_REPORTS_DATA = <?= json_encode([
        'patientsByAgeGroup' => [
            'labels' => array_column($patientsByAgeGroup, 'label'),
            'values' => array_column($patientsByAgeGroup, 'value'),
            'colors' => array_column($patientsByAgeGroup, 'color'),
        ],
        'patientsByGender' => [
            'labels' => array_column($patientsByGenderReport, 'label'),
            'values' => array_column($patientsByGenderReport, 'value'),
            'colors' => array_column($patientsByGenderReport, 'color'),
        ],
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/sortable-table.js') ?>"></script>
<script src="<?= asset_url('/assets/js/patient_reports.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-report-nav.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>
