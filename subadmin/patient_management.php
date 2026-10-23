<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Management (subadmin/patient_management.php)
 * ---------------------------------------------------------------------
 * Reuses the exact same sidebar/topbar shell — and the same Sub-Admin
 * nav list — as subadmin/dashboard.php (280px dark-red gradient
 * sidebar, "adm-" prefixed atoms, assets/css/admin-dashboard.css), so
 * the sidebar doesn't change when navigating here from the dashboard.
 * Only the page content (stat cards, search/filter toolbar, patient
 * table) is unique to this page, in assets/css/patient_management.css.
 *
 * UNLIKE most subadmin/*.php pages built earlier this session, this one
 * is NOT front-end only: $patients reads LIVE from the real "patients"
 * Firestore collection (see includes/subadmin/patient_management_firestore.php's
 * header comment). The search box and gender/age filters now really
 * filter the table. Each row's "View Profile" button links to
 * subadmin/patient_profile.php?id=<patient id> — that page family
 * itself remains its own separate, front-end-only section.
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

require_once __DIR__ . '/../includes/subadmin/patient_management_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $statCards,
// $genderOptions, $ageGroupOptions, $patients, $patientsTotal,
// $patientsPerPage, $patientsCurrentPage, $patientsTotalPages

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Patient Management is active here.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php', 'active' => true],
    ['icon' => 'bi-capsule',                'label' => t('nav.medicine_vaccine_inventory'), 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-box-seam-fill',          'label' => t('nav.stock_requests'),               'href' => 'stock_requests.php'],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => t('nav.analytics_reports'),
        'id' => 'admNavAnalyticsReports',
        'children' => [
            ['label' => t('nav.analytics_reports_facility'), 'href' => 'analytics_reports.php'],
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
<title>Patient Management — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin/subadmin shell (sidebar, topbar, cards, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Patient Management page styles (stat cards, filter toolbar, table) — shared verbatim with medical-staff/doctor's Patient Management -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_management.css') ?>">
<!-- Quick Search dropdown under the search box (live typeahead, shared with doctor/medical-staff's Patient Management) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/quick-search.css') ?>">
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
            <h1><?= htmlspecialchars(t('nav.patient_management')) ?></h1>
            <p class="adm-page-subtitle"><?= htmlspecialchars(t('page.sa_patient_management.subtitle')) ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
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

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="pmg-stats-row">
        <?php foreach ($statCards as $stat): ?>
        <div class="pmg-stat-card">
            <div class="pmg-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div>
                <div class="pmg-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
                <div class="pmg-stat-value"><?= $stat['value'] === null ? '—' : number_format((int) $stat['value']) ?></div>
                <?php if (isset($stat['sub'])): ?>
                <div class="pmg-stat-sub"><i class="bi bi-arrow-up-short"></i> <?= htmlspecialchars($stat['sub']) ?></div>
                <?php elseif (isset($stat['staticSub'])): ?>
                <div class="pmg-stat-sub muted"><?= htmlspecialchars($stat['staticSub']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ SEARCH + FILTER TOOLBAR ============ -->
    <form class="pmg-toolbar" method="get" action="patient_management.php">
        <div class="pmg-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" id="patientSearchInput" placeholder="Search by name or contact number..." autocomplete="off" data-quick-search="<?= BASE_URL ?>/api/quick-search-patients.php" data-quick-search-view="patient_profile.php?id={id}">
        </div>
        <button type="submit" class="pmg-btn-search"><i class="bi bi-search"></i> Search</button>

        <select class="form-select pmg-filter-select" name="gender" aria-label="Filter by gender" onchange="this.form.submit()">
            <?php foreach ($genderOptions as $g): ?>
            <option <?= $g === $genderFilter ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="form-select pmg-filter-select" name="ageGroup" aria-label="Filter by age group" onchange="this.form.submit()">
            <?php foreach ($ageGroupOptions as $a): ?>
            <option <?= $a === $ageGroupFilter ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- ============ PATIENT TABLE ============ -->
    <div class="adm-card pmg-table-card">
        <h2 class="pmg-table-title">Patient List</h2>

        <?php if ($patientsLoadFailed): ?>
        <div class="alert alert-danger py-2 px-3 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Couldn't load patient data right now — Firestore didn't respond (this is usually a temporary
            outage or the daily quota being reached, not missing data). Please try again in a moment.
        </div>
        <?php endif; ?>

        <div class="adm-table-wrap">
            <table class="adm-table pmg-table">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Contact Number</th>
                        <th>Date Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty"><?= $patientsLoadFailed ? 'Could not load patient data.' : 'No patients registered yet.' ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= (int) $p['age'] ?></td>
                        <td><?= htmlspecialchars($p['gender']) ?></td>
                        <td><?= htmlspecialchars($p['contact']) ?></td>
                        <td><?= htmlspecialchars($p['dateRegistered']) ?></td>
                        <td>
                            <div class="adm-action-btns pmg-action-btns">
                                <a href="patient_profile.php?id=<?= urlencode($p['id']) ?>" class="pmg-btn-view-profile"><i class="bi bi-eye"></i> View Profile</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <?php if ($patientsUsingRealQuery): ?>
            <!-- Real Firestore query path — Previous/Next only, no numbered
                 jump-to-page (a cursor only exists once you've actually
                 paged there — see includes/subadmin/patient_management_firestore.php's
                 header comment). "of N" still shown since the total patient
                 count is itself a cheap real aggregation, not a full read. -->
            <span class="adm-showing-text"><?= count($patients) > 0 ? 'Showing ' . count($patients) . ' of ' . number_format($totalPatientsCount) . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Patient list pages">
                <?php if ($patientsHasPrev): ?>
                <a href="?<?= $patientsPrevQuery ?>" class="page-btn page-btn-labeled" aria-label="Previous page"><i class="bi bi-chevron-left"></i> Previous</a>
                <?php else: ?>
                <span class="page-btn page-btn-labeled disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i> Previous</span>
                <?php endif; ?>
                <?php if ($patientsHasNext): ?>
                <a href="?<?= $patientsNextQuery ?>" class="page-btn page-btn-labeled" aria-label="Next page">Next <i class="bi bi-chevron-right"></i></a>
                <?php else: ?>
                <span class="page-btn page-btn-labeled disabled" aria-disabled="true">Next <i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
            </nav>
            <?php else: ?>
            <span class="adm-showing-text"><?= $patientsTotal > 0 ? 'Showing ' . (($patientsCurrentPage - 1) * $patientsPerPage + 1) . ' to ' . min($patientsCurrentPage * $patientsPerPage, $patientsTotal) . ' of ' . number_format($patientsTotal) . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Patient list pages">
                <a href="?<?= hc_pm_query(['page' => 1]) ?>" class="page-btn" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
                <a href="?<?= hc_pm_query(['page' => max(1, $patientsCurrentPage - 1)]) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php foreach ($patientsPaginationWindow as $p): ?>
                    <?php if ($p === '...'): ?>
                    <span class="page-ellipsis">&hellip;</span>
                    <?php else: ?>
                    <a href="?<?= hc_pm_query(['page' => $p]) ?>" class="page-btn <?= $p === $patientsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="?<?= hc_pm_query(['page' => min($patientsTotalPages, $patientsCurrentPage + 1)]) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <a href="?<?= hc_pm_query(['page' => $patientsTotalPages]) ?>" class="page-btn" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
            </nav>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/quick-search.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
