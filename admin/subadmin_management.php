<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Sub-Administrator Management (admin/subadmin_management.php)
 * ---------------------------------------------------------------------
 * Reuses the same sidebar/topbar shell as the other admin pages
 * (assets/css/admin-dashboard.css, "adm-" prefixed) plus its own styles
 * for the stat cards, table, badges, and legend
 * (assets/css/subadmin_management.css, "sam-" prefixed).
 *
 * "Add Sub-Administrator" is connected: includes/add_subadmin_modal.php
 * collects a username/email/facility/password, and
 * assets/js/add-subadmin.js POSTs it to api/create-subadmin.php, which
 * creates a real Firebase Auth account + Firestore users/{uid} profile
 * doc. $subadmins itself now comes from a live Firestore query in
 * includes/subadmin_management_firestore.php. The search box, status tabs,
 * Filter/Sort controls, and table row actions (view/edit/delete) still
 * render without being wired up yet — the markup/loop below won't need
 * to change shape once they are.
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

require_once __DIR__ . '/../includes/admin/subadmin_management_firestore.php';
// $adminProfile, $facilities, $subadminStats, $subadminTabs, $roleColors,
// $roleOptions, $statusLegend, $statusOptions, $subadmins, $subadminsTotal,
// $subadminsPerPage, $subadminsCurrentPage, $subadminsTotalPages

// Sidebar nav — Sub-Administrator Management is active on this page.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php'],    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php', 'active' => true],
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
<title>Sub-Administrator Management — HEALTrack</title>

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
<!-- Sub-Administrator Management page styles (stat cards, table, badges) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_management.css') ?>">
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

    <!-- ============ BACK LINK ============ -->
    <a href="admin_dashboard.php" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> <?= t('back.dashboard') ?>
    </a>

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= t('page.subadmin_management.title') ?></h1>
            <p class="adm-page-subtitle"><?= t('page.subadmin_management.subtitle') ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><i class="bi bi-person-fill"></i></span>
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

            <div class="sam-toolbar-row">
                <form class="sam-search" method="get" action="subadmin_management.php">
                    <i class="bi bi-search"></i>
                    <!-- Real fuzzy search now (hc_fuzzy_match(), see
                         includes/admin/subadmin_management_firestore.php) — submits on
                         Enter, same as every other plain-text search box in this app. -->
                    <input type="text" id="subadminSearchInput" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by name, email or facility...">
                </form>
                <button type="button" class="btn-add-subadmin" id="btnAddSubadmin">
                    <i class="bi bi-plus-lg"></i> Add Sub-Administrator
                </button>
            </div>
        </div>
    </div>

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="sam-stats-row">
        <?php foreach ($subadminStats as $stat): ?>
        <div class="sam-stat-card">
            <div class="sam-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div>
                <div class="sam-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
                <div class="sam-stat-value"><?= number_format((int) $stat['value']) ?></div>
                <div class="sam-stat-sub">
                    <?= htmlspecialchars($stat['sub']) ?>
                    <?php if (isset($stat['growth'])): ?>
                    <span class="sam-stat-growth"><i class="bi bi-arrow-up-short"></i><?= (int) $stat['growth'] ?>%</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ TABS + FILTER/SORT ============ -->
    <div class="sam-tabs-row">
        <ul class="nav adm-tabs sam-tabs" role="tablist">
            <?php foreach ($subadminTabs as $tab): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>" type="button" role="tab">
                    <?= htmlspecialchars($tab['label']) ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="sam-tabs-actions">
            <!-- Not wired up yet — front-end only, same as the search box above. -->
            <button type="button" class="sam-filter-btn"><i class="bi bi-funnel"></i> Filter</button>
            <div class="dropdown">
                <button class="sam-sort-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Sort by: Newest
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#">Newest</a></li>
                    <li><a class="dropdown-item" href="#">Oldest</a></li>
                    <li><a class="dropdown-item" href="#">Name (A–Z)</a></li>
                    <li><a class="dropdown-item" href="#">Last Login</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ============ SUB-ADMINISTRATOR TABLE ============ -->
    <div class="adm-card sam-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table sam-table">
                <thead>
                    <tr>
                        <th>Sub-Administrator</th>
                        <th>Email</th>
                        <th>Assigned Facility</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredSubadmins)): ?>
                    <tr>
                        <td colspan="7" class="adm-table-empty"><?= empty($subadmins) ? 'No sub-administrator accounts yet.' : 'No sub-administrators match your search.' ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($filteredSubadmins as $s): $role = $roleColors[$s['role']] ?? ['bg' => '#EEF0F3', 'color' => '#6B7280']; ?>
                    <tr>
                        <td>
                            <div class="adm-activity-name-cell">
                                <span class="adm-avatar-sm initials <?= htmlspecialchars($s['avatarColor']) ?>"><?= htmlspecialchars($s['initials']) ?></span>
                                <span><?= htmlspecialchars($s['name']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($s['email']) ?></td>
                        <td>
                            <div class="sam-facility-cell">
                                <span class="sam-facility-name"><?= htmlspecialchars($s['facility']) ?></span>
                                <span class="sam-facility-location"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($s['location']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="sam-role-badge" style="background:<?= htmlspecialchars($role['bg']) ?>; color:<?= htmlspecialchars($role['color']) ?>;">
                                <?= htmlspecialchars($s['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="sam-status-badge <?= htmlspecialchars($s['status']) ?>">
                                <?= htmlspecialchars($statusLegend[$s['status']]['label']) ?>
                            </span>
                        </td>
                        <td><?= $s['lastLogin'] ? htmlspecialchars($s['lastLogin']) : '—' ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="adm-action-btn view" aria-label="View" data-id="<?= htmlspecialchars($s['id']) ?>"><i class="bi bi-eye"></i></button>
                                <?php if (in_array($s['status'], ['active', 'inactive'], true)): ?>
                                <button type="button" class="adm-action-btn edit" aria-label="Edit" data-id="<?= htmlspecialchars($s['id']) ?>"><i class="bi bi-pencil"></i></button>
                                <?php endif; ?>
                                <?php if ($s['status'] === 'locked'): ?>
                                <button type="button" class="adm-action-btn continue" aria-label="Unlock Account" data-id="<?= htmlspecialchars($s['id']) ?>"><i class="bi bi-unlock-fill"></i></button>
                                <?php endif; ?>
                                <button type="button" class="adm-action-btn delete" aria-label="Delete" data-id="<?= htmlspecialchars($s['id']) ?>"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $subadminsTotal > 0 ? 'Showing ' . (($subadminsCurrentPage - 1) * $subadminsPerPage + 1) . ' to ' . min($subadminsCurrentPage * $subadminsPerPage, $subadminsTotal) . ' of ' . $subadminsTotal . ' sub-administrators' : 'No sub-administrators to show' ?></span>
            <nav class="adm-pagination" aria-label="Sub-administrator list pages">
                <?php if ($subadminsCurrentPage > 1): ?>
                <a href="?<?= hc_sam_query(['page' => $subadminsCurrentPage - 1]) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i></span>
                <?php endif; ?>
                <?php for ($p = 1; $p <= min(4, $subadminsTotalPages); $p++): ?>
                <a href="?<?= hc_sam_query(['page' => $p]) ?>" class="page-btn <?= $p === $subadminsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($subadminsTotalPages > 4): ?>
                <span class="page-ellipsis">&hellip;</span>
                <a href="?<?= hc_sam_query(['page' => $subadminsTotalPages]) ?>" class="page-btn <?= $subadminsCurrentPage === $subadminsTotalPages ? 'active' : '' ?>"><?= $subadminsTotalPages ?></a>
                <?php endif; ?>
                <?php if ($subadminsCurrentPage < $subadminsTotalPages): ?>
                <a href="?<?= hc_sam_query(['page' => $subadminsCurrentPage + 1]) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <!-- ============ STATUS LEGEND ============ -->
    <div class="sam-legend">
        <?php foreach ($statusLegend as $key => $l): ?>
        <span class="sam-legend-item">
            <span class="sam-legend-dot <?= htmlspecialchars($key) ?>"></span>
            <strong><?= htmlspecialchars($l['label']) ?></strong> &ndash; <?= htmlspecialchars($l['desc']) ?>
        </span>
        <?php endforeach; ?>
    </div>

</main>

<?php require __DIR__ . '/../includes/admin/add_subadmin_modal.php'; ?>
<?php require __DIR__ . '/../includes/admin/subadmin_view_edit_modals.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>

<script>
    // Hands each row's full data to assets/js/subadmin-actions.js (keyed by
    // id) so View/Edit don't need to re-parse PHP-rendered table cells.
    window.SUBADMINS_DATA = <?= json_encode(array_combine(
        array_column($subadmins, 'id'),
        array_map(fn ($s) => [
            'name' => $s['name'],
            'email' => $s['email'],
            'facility' => $s['facility'],
            'facilityId' => $s['facilityId'],
            'role' => $s['role'],
            'status' => $s['status'],
            'statusLabel' => $statusLegend[$s['status']]['label'] ?? ucfirst($s['status']),
            'lastLogin' => $s['lastLogin'],
        ], $subadmins)
    )) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/add-subadmin.js') ?>"></script>
<script src="<?= asset_url('/assets/js/subadmin-actions.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
</body>
</html>
