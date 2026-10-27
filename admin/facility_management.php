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
<!-- Leaflet (Add Facility modal's Lipa City map picker — same library/tiles admin/gis_mapping.php uses) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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

    <?php
    // Both groups render the exact same card markup — just two
    // different arrays, split above in includes/admin/facility_management_firestore.php
    // by the real isIntegrated flag. Factored into one closure instead
    // of writing the card HTML out twice, so the two stay identical.
    $renderFacilityCard = function (array $f) {
        ?>
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

            <div class="fac-integrated-row">
                <span class="fac-integrated-label">Active <span class="text-muted fw-normal">(shown on GIS Mapping)</span></span>
                <div class="form-check form-switch fac-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="facActive-<?= htmlspecialchars($f['id']) ?>" data-fac-status-toggle data-facility-id="<?= htmlspecialchars($f['id']) ?>" <?= $f['status'] === 'active' ? 'checked' : '' ?>>
                </div>
            </div>

            <div class="fac-integrated-row">
                <span class="fac-integrated-label">Integrated with HEALTrack</span>
                <div class="form-check form-switch fac-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="facIntegrated-<?= htmlspecialchars($f['id']) ?>" data-fac-integrated-toggle data-facility-id="<?= htmlspecialchars($f['id']) ?>" <?= $f['isIntegrated'] ? 'checked' : '' ?>>
                </div>
            </div>

            <?php if ($f['isIntegrated']): ?>
            <a href="facility_analytics.php?facility_id=<?= urlencode($f['id']) ?>" class="btn-view-analytics">
                View Analytics
            </a>
            <?php else: ?>
            <span class="btn-view-analytics disabled" tabindex="0" title="Not available yet &mdash; this facility isn't integrated with HEALTrack.">
                View Analytics
            </span>
            <?php endif; ?>
        </div>
        <?php
    };
    ?>

    <!-- ============ FACILITIES WITH ANALYTICS ============ -->
    <div class="fac-section">
        <div class="fac-section-header">
            <h2><i class="bi bi-graph-up-arrow"></i> Facilities with Analytics</h2>
            <span class="fac-section-count"><?= count($integratedFacilities) ?></span>
        </div>
        <?php if (empty($integratedFacilities)): ?>
        <div class="adm-card" style="padding:24px; text-align:center;">
            <p class="adm-table-empty">No facility has HEALTrack running yet.</p>
        </div>
        <?php else: ?>
        <div class="fac-grid">
            <?php foreach ($integratedFacilities as $f) {
                $renderFacilityCard($f);
            } ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============ OTHER FACILITIES (NOT YET INTEGRATED) ============ -->
    <div class="fac-section">
        <div class="fac-section-header">
            <h2><i class="bi bi-geo-alt"></i> Other Facilities <span class="fac-section-subtitle">(not yet integrated with HEALTrack)</span></h2>
            <span class="fac-section-count"><?= count($notIntegratedFacilities) ?></span>
        </div>
        <?php if (empty($notIntegratedFacilities)): ?>
        <div class="adm-card" style="padding:24px; text-align:center;">
            <p class="adm-table-empty">Every facility is integrated with HEALTrack.</p>
        </div>
        <?php else: ?>
        <div class="fac-grid">
            <?php foreach ($notIntegratedFacilities as $f) {
                $renderFacilityCard($f);
            } ?>
        </div>
        <?php endif; ?>
    </div>

</main>

<?php require __DIR__ . '/../includes/admin/add_facility_modal.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/add-facility.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/live-clock.js') ?>"></script>
</body>
</html>
