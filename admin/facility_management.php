<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Facility Management (admin/facility_management.php)
 * ---------------------------------------------------------------------
 * Reuses the exact same sidebar/topbar shell as admin_dashboard.php
 * (assets/css/admin-dashboard.css, "adm-" prefixed classes) so the two
 * pages read as one system. This page's own toolbar + facility card
 * grid live in assets/css/facility_management.css ("fac-" prefixed).
 *
 * "Add New Facility" is connected to a real Firestore "facilities"
 * collection (see includes/admin/facility_management_firestore.php's header
 * comment); the search box isn't wired up yet.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php';          // t()

// Only a signed-in admin (see api/session-login.php) may view this page.
if (($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/admin/facility_management_firestore.php'; // $adminProfile, $facilities, $facilitiesTotal, ...

// Sidebar nav — Facility Management is active on this page.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php', 'active' => true],    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php'],
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
<title>Facility Management — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Facility Management page styles (toolbar + card grid) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/facility_management.css') ?>">
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
            <h1><?= t('page.facility_management.title') ?></h1>
            <p class="adm-page-subtitle"><?= t('page.facility_management.subtitle') ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="adm-datetime-widget">
                    <span><i class="bi bi-calendar3"></i><span class="adm-dt-date"><?= date('F j, Y') ?></span></span>
                    <span class="adm-dt-sep">|</span>
                    <span><i class="bi bi-clock"></i><span class="adm-dt-time"><?= date('g:i A') ?></span></span>
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

    <!-- ============ SEARCH + ADD FACILITY TOOLBAR ============ -->
    <div class="fac-toolbar">
        <div class="fac-search">
            <i class="bi bi-search"></i>
            <!-- Not wired up yet — once facility search is connected, this posts/fetches
                 against the same $facilities source that feeds the grid below. -->
            <input type="text" id="facilitySearchInput" placeholder="Search facility name, type, or location...">
        </div>
        <button type="button" class="btn-add-facility-lg" id="btnAddFacility">
            <i class="bi bi-plus-lg"></i> Add New Facility
        </button>
    </div>

    <!-- ============ FACILITY CARD GRID ============ -->
    <div class="fac-grid">
        <?php foreach ($facilities as $f): ?>
        <div class="fac-card" data-facility-id="<?= htmlspecialchars($f['id']) ?>">
            <div class="fac-card-top">
                <div class="fac-icon"><i class="bi <?= htmlspecialchars($f['icon']) ?>"></i></div>
                <span class="fac-badge"><?= htmlspecialchars($f['type']) ?></span>
            </div>

            <h3 class="fac-name"><?= htmlspecialchars($f['name']) ?></h3>
            <div class="fac-location">
                <i class="bi bi-geo-alt-fill"></i>
                <span><?= htmlspecialchars($f['location']) ?></span>
            </div>

            <div class="fac-stats">
                <div class="fac-stat-row">
                    <span class="fac-stat-label">Patients:</span>
                    <span class="fac-stat-value"><?= number_format((int) $f['patients']) ?></span>
                </div>
                <div class="fac-stat-row">
                    <span class="fac-stat-label">Records:</span>
                    <span class="fac-stat-value"><?= number_format((int) $f['records']) ?></span>
                </div>
                <div class="fac-stat-row">
                    <span class="fac-stat-label">Staff:</span>
                    <span class="fac-stat-value"><?= number_format((int) $f['staff']) ?></span>
                </div>
            </div>

            <a href="facility_analytics.php?facility_id=<?= urlencode($f['id']) ?>" class="btn-view-analytics">
                View Analytics
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ GRID FOOTER: COUNT + PAGINATION ============ -->
    <div class="fac-footer adm-table-footer">
        <span class="adm-showing-text"><?= $facilitiesTotal > 0 ? 'Showing ' . (($facilitiesCurrentPage - 1) * $facilitiesPerPage + 1) . ' to ' . min($facilitiesCurrentPage * $facilitiesPerPage, $facilitiesTotal) . ' of ' . $facilitiesTotal . ' facilities' : 'No facilities to show' ?></span>
        <nav class="adm-pagination" aria-label="Facility list pages">
            <?php if ($facilitiesCurrentPage > 1): ?>
            <a href="?<?= hc_fac_query($facilitiesCurrentPage - 1) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php else: ?>
            <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i></span>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $facilitiesTotalPages; $p++): ?>
            <a href="?<?= hc_fac_query($p) ?>" class="page-btn <?= $p === $facilitiesCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($facilitiesCurrentPage < $facilitiesTotalPages): ?>
            <a href="?<?= hc_fac_query($facilitiesCurrentPage + 1) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <?php else: ?>
            <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
            <?php endif; ?>
        </nav>
    </div>

</main>

<?php require __DIR__ . '/../includes/admin/add_facility_modal.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/add-facility.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/live-clock.js') ?>"></script>
</body>
</html>
