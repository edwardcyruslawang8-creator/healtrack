<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Administrator Dashboard (admin/admin_dashboard.php)
 * ---------------------------------------------------------------------
 * This is where an admin lands after signing in on login.php (see the
 * role-based redirect note there). It's a deliberately separate visual
 * system from the patient-facing pages under patient/ — different
 * branding ("HEALTrack" the platform vs "North Super Health Center"
 * the facility) — so it doesn't reuse includes/header.php, sidebar.php,
 * etc. It's self-contained, the same way login.php is.
 *
 * $dashboardStats/$facilities/$subAdminChart/$recentActivity/
 * $accountStatuses all read live from Firestore — see
 * includes/admin/admin_dashboard_firestore.php's header comment for the collections behind
 * each.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php';   // BASE_URL
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php';          // t()

// Only a signed-in admin (see api/session-login.php) may view this page.
if (($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/admin/admin_dashboard_firestore.php'; // $adminProfile, $dashboardStats, $facilities, $subAdminChart, $recentActivity, $accountStatuses

// Sidebar nav — Dashboard is active on this page.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php', 'active' => true],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php'],    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php'],
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
<title>Administrator Dashboard — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Admin dashboard stylesheet (self-contained, "adm-" prefixed) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Leaflet (Add Facility modal's Lipa City map picker — same library/tiles admin/gis_mapping.php uses) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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
            <h1><?= t('page.dashboard.title') ?></h1>
            <p class="adm-page-subtitle"><?= t('page.dashboard.subtitle') ?></p>
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

    <!-- ============ STATISTIC CARDS ============ -->
    <div class="adm-stats-row">
        <?php foreach ($dashboardStats as $stat): ?>
        <div class="adm-stat-card">
            <div class="adm-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div>
                <div class="adm-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
                <div class="adm-stat-value"><?= htmlspecialchars($stat['value']) ?></div>
                <div class="adm-stat-meta">
                    <?php foreach ($stat['meta'] as $m): ?>
                    <span><span class="meta-label"><?= htmlspecialchars($m['label']) ?></span><span style="color:<?= htmlspecialchars($m['color']) ?>; font-weight:600;"><?= htmlspecialchars($m['value']) ?></span></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ PREDICTIVE & PRESCRIPTIVE ANALYTICS (PREVIEW) ============
         System-wide (every facility combined) — see
         includes/admin/predictive_overview_firestore.php's header comment.
         The full per-facility breakdown is one click away via "View
         Analytics" on any facility card in Facility Management. -->
    <?php
    $predictiveOverviewLinkHref = 'facility_management.php';
    $predictiveOverviewLinkLabel = 'View Facility Analytics';
    require __DIR__ . '/../includes/admin/predictive_overview_card.php';
    ?>

    <!-- ============ FACILITY OVERVIEW + SUB-ADMIN OVERVIEW ============ -->
    <div class="adm-grid-70-30">

        <!-- Facility Overview -->
        <div class="adm-card">
            <div class="adm-card-header">
                <h2>Facility Overview</h2>
                <button type="button" class="btn-add-facility"><i class="bi bi-plus-lg"></i> Add Facility</button>
            </div>

            <div class="adm-table-wrap">
                <table class="adm-table" id="facilityOverviewTable">
                    <thead>
                        <tr>
                            <th>Facility Name</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Sub-Admins Assigned</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($facilities)): ?>
                        <tr>
                            <td colspan="5" class="adm-table-empty">No facilities yet.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($facilities as $f): ?>
                        <tr>
                            <td><?= htmlspecialchars($f['name']) ?></td>
                            <td><?= htmlspecialchars($f['location']) ?></td>
                            <td><span class="adm-status-badge <?= htmlspecialchars($f['status']) ?>"><?= ucfirst(htmlspecialchars($f['status'])) ?></span></td>
                            <td><?= (int) $f['subAdmins'] ?></td>
                            <td><?= htmlspecialchars($f['dateAdded']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="adm-table-footer">
                <span class="adm-showing-text"><?= $facilitiesTotal > 0 ? 'Showing ' . (($facilitiesCurrentPage - 1) * $facilitiesPerPage + 1) . ' to ' . min($facilitiesCurrentPage * $facilitiesPerPage, $facilitiesTotal) . ' of ' . $facilitiesTotal . ' facilities' : 'No facilities to show' ?></span>
                <nav class="adm-pagination" aria-label="Facility list pages">
                    <?php if ($facilitiesCurrentPage > 1): ?>
                    <a href="?<?= hc_dash_fac_query($facilitiesCurrentPage - 1) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                    <?php else: ?>
                    <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i></span>
                    <?php endif; ?>
                    <?php for ($p = 1; $p <= $facilitiesTotalPages; $p++): ?>
                    <a href="?<?= hc_dash_fac_query($p) ?>" class="page-btn <?= $p === $facilitiesCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($facilitiesCurrentPage < $facilitiesTotalPages): ?>
                    <a href="?<?= hc_dash_fac_query($facilitiesCurrentPage + 1) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                    <?php else: ?>
                    <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
                    <?php endif; ?>
                </nav>
            </div>
        </div>

        <!-- Sub-Administrator Overview -->
        <div class="adm-card">
            <div class="adm-card-header">
                <h2>Sub-Administrator Overview</h2>
            </div>

            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="subAdminDonut"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= (int) $subAdminChart['total'] ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
                <div class="adm-donut-legend">
                    <div class="legend-row"><span class="dot" style="background:#1E8E3E;"></span> Active (<?= (int) $subAdminChart['active'] ?>)</div>
                    <div class="legend-row"><span class="dot" style="background:#E8710A;"></span> Inactive (<?= (int) $subAdminChart['inactive'] ?>)</div>
                </div>
            </div>

            <a href="subadmin_management.php" class="adm-card-footer-link">View all sub-administrators <i class="bi bi-arrow-right"></i></a>
        </div>

    </div>

    <!-- ============ RECENT ACTIVITY + ACCOUNT STATUS ============ -->
    <div class="adm-grid-70-30">

        <!-- Recent Sub-Administrator Activity -->
        <div class="adm-card">
            <div class="adm-card-header">
                <h2>Recent Sub-Administrator Activity</h2>
            </div>

            <div class="adm-table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Sub-Administrator</th>
                            <th>Module</th>
                            <th>Activity</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentActivity)): ?>
                        <tr>
                            <td colspan="4" class="adm-table-empty">No recent activity yet.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($recentActivity as $a): ?>
                        <tr>
                            <td>
                                <div class="adm-activity-name-cell">
                                    <span class="adm-avatar-sm <?= htmlspecialchars($a['avatarColor']) ?>"><i class="bi bi-person-fill"></i></span>
                                    <span><?= htmlspecialchars($a['name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($a['module']) ?></td>
                            <td><?= htmlspecialchars($a['activity']) ?></td>
                            <td><?= htmlspecialchars($a['datetime']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <a href="audit_logs.php" class="adm-card-footer-link">View all activity logs <i class="bi bi-arrow-right"></i></a>
        </div>

        <!-- Account Status Overview -->
        <div class="adm-card">
            <div class="adm-card-header">
                <h2>Account Status Overview</h2>
            </div>

            <div class="adm-status-list">
                <?php foreach ($accountStatuses as $s): ?>
                <div class="adm-status-row">
                    <span class="adm-status-icon" style="background:<?= htmlspecialchars($s['iconBg']) ?>; color:<?= htmlspecialchars($s['iconColor']) ?>;">
                        <i class="bi <?= htmlspecialchars($s['icon']) ?>"></i>
                    </span>
                    <span class="adm-status-body">
                        <span class="adm-status-title"><?= htmlspecialchars($s['label']) ?></span>
                        <span class="adm-status-desc"><?= htmlspecialchars($s['desc']) ?></span>
                    </span>
                    <span class="adm-status-number" style="color:<?= htmlspecialchars($s['valueColor']) ?>;"><?= (int) $s['value'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <a href="subadmin_management.php" class="adm-card-footer-link">Manage sub-administrators <i class="bi bi-arrow-right"></i></a>
        </div>

    </div>

</main>

<?php require __DIR__ . '/../includes/admin/add_facility_modal.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Chart.js (for the Sub-Administrator Overview doughnut chart) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= asset_url('/assets/js/chart-center-label.js') ?>"></script>

<script>
    // Hands the doughnut chart's numbers to assets/js/admin-dashboard.js
    // without it needing to parse PHP-rendered HTML.
    window.SUBADMIN_CHART_DATA = <?= json_encode($subAdminChart) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-dashboard.js') ?>"></script>
<script src="<?= asset_url('/assets/js/add-facility.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/live-clock.js') ?>"></script>
</body>
</html>
