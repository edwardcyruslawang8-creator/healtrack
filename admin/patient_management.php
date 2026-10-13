<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Patient Management (admin/patient_management.php)
 * ---------------------------------------------------------------------
 * Reuses the same sidebar/topbar shell as the rest of admin/*.php
 * (assets/css/admin-dashboard.css), plus the exact same stat-card/
 * table/pagination markup and CSS (assets/css/patient_management.css)
 * subadmin/patient_management.php already uses — see
 * includes/admin/patient_management_firestore.php's header comment for
 * how the underlying data is guaranteed to be the same real
 * computation, just parameterized on whichever facility the topbar
 * dropdown below has selected instead of always the signed-in
 * sub-admin's own.
 *
 * UNLIKE subadmin's version, this page has no "View Profile" action —
 * there is no admin-facing patient profile page yet (subadmin/doctor/
 * medical-staff's patient_profile.php family is role-gated to its own
 * section), so this stays a read-only per-facility roster for now.
 * Quick-search (api/quick-search-patients.php, now widened to allow
 * 'admin' too) runs in the same fill-and-submit mode as the Medicines/
 * Vaccines search boxes elsewhere (no data-quick-search-view, since
 * there's nowhere to navigate to here) — clicking a result fills the
 * exact name into the box and submits this same full-page search,
 * applying it instead of just suggesting it. Scoped to whichever
 * facility the topbar dropdown currently has selected (via its own
 * ?facility= param) so a result can never point at a patient the
 * facility-scoped full search would then show zero matches for.
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

require_once __DIR__ . '/../includes/admin/patient_management_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $selectedFacility,
// $statCards, $genderOptions, $ageGroupOptions, $searchTerm, $genderFilter,
// $ageGroupFilter, $patients, $patientsUsingRealQuery, $patientsLoadFailed,
// $totalPatientsCount, ...pagination vars, hc_pm_query()

// Sidebar nav — Patient Management is active on this page.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php', 'active' => true],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php'],    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php'],
    ['icon' => 'bi-geo-alt-fill',        'label' => t('nav.gis_mapping'),         'href' => 'gis_mapping.php'],
    ['icon' => 'bi-shield-lock-fill',    'label' => t('nav.account_monitoring'),  'href' => 'account_monitoring.php'],
    ['icon' => 'bi-megaphone-fill',      'label' => 'Announcements',              'href' => 'announcements.php'],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => 'Sub Admin', 'href' => 'audit_logs.php?role=subadmin'],
            ['label' => 'User',      'href' => 'audit_logs.php?role=user'],
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

<!-- Shared admin shell (sidebar, topbar, cards, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Topbar select-button styling, reused here for the Date/Facility pills (same borrow subadmin/patient_management.php already does) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Patient Management page styles (stat cards, filter toolbar, table) — shared verbatim with subadmin/doctor/medical-staff's Patient Management -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/patient_management.css') ?>">
<!-- Quick Search dropdown under the search box (live typeahead, shared with subadmin/doctor/medical-staff's Patient Management) -->
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

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1>Patient Management</h1>
            <p class="adm-page-subtitle">View patients registered at each facility.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, matching every other admin page's
                     topbar date. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Real facility switcher — a System Administrator oversees
                     every facility, so unlike subadmin's own fixed facility
                     chip, this one actually picks which facility's patients
                     the page below shows. -->
                <div class="dropdown">
                    <button class="sad-topbar-select-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-building"></i> <?= htmlspecialchars($selectedFacility) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($facilityOptions as $fac): ?>
                        <li><a class="dropdown-item <?= $fac === $selectedFacility ? 'active' : '' ?>" href="?<?= hc_pm_query(['facility' => $fac, 'page' => 1]) ?>"><?= htmlspecialchars($fac) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

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
    <div class="pmg-stats-row">
        <?php foreach ($statCards as $stat): ?>
        <div class="pmg-stat-card">
            <div class="pmg-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div>
                <div class="pmg-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
                <div class="pmg-stat-value"><?= number_format((int) $stat['value']) ?></div>
                <?php if (isset($stat['staticSub'])): ?>
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
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by name or contact number..." autocomplete="off" data-quick-search="<?= BASE_URL ?>/api/quick-search-patients.php?facility=<?= urlencode($selectedFacility) ?>">
        </div>

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

        <input type="hidden" name="facility" value="<?= htmlspecialchars($selectedFacility) ?>">
        <button type="submit" class="pmg-btn-search"><i class="bi bi-search"></i> Search</button>
    </form>

    <!-- ============ PATIENT TABLE ============ -->
    <div class="adm-card pmg-table-card">
        <h2 class="pmg-table-title"><?= htmlspecialchars($selectedFacility) ?> — Patient List</h2>

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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="5" class="adm-table-empty"><?= $patientsLoadFailed ? 'Could not load patient data.' : 'No patients registered at this facility yet.' ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= (int) $p['age'] ?></td>
                        <td><?= htmlspecialchars($p['gender']) ?></td>
                        <td><?= htmlspecialchars($p['contact']) ?></td>
                        <td><?= htmlspecialchars($p['dateRegistered']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <?php if ($patientsUsingRealQuery): ?>
            <span class="adm-showing-text"><?= count($patients) > 0 ? 'Showing ' . count($patients) . ' of ' . number_format($totalPatientsCount) . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Patient list pages">
                <?php if ($patientsHasPrev): ?>
                <a href="?<?= hc_pm_query(['page' => $patientsCurrentPage - 1]) ?>" class="page-btn page-btn-labeled" aria-label="Previous page"><i class="bi bi-chevron-left"></i> Previous</a>
                <?php else: ?>
                <span class="page-btn page-btn-labeled disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i> Previous</span>
                <?php endif; ?>
                <?php if ($patientsHasNext): ?>
                <a href="?<?= hc_pm_query(['page' => $patientsCurrentPage + 1]) ?>" class="page-btn page-btn-labeled" aria-label="Next page">Next <i class="bi bi-chevron-right"></i></a>
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
