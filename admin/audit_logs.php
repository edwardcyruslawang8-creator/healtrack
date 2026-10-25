<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Audit Logs (admin/audit_logs.php)
 * ---------------------------------------------------------------------
 * Reuses the exact same sidebar/topbar shell — and the same System
 * Administrator nav list — as admin_dashboard.php and the rest of the
 * admin section (280px dark-red sidebar, .adm-card/.adm-table/
 * .adm-pagination atoms, all "adm-" prefixed, assets/css/admin-dashboard.css),
 * so the sidebar doesn't change when navigating here from any other
 * admin page. Only the page content (search bar, filter toolbar, log
 * table, pagination) is unique to this page, in assets/css/audit_logs.css.
 *
 * $auditLogs reads live from Firestore's "audit_logs" collection — see
 * includes/admin/audit_logs_firestore.php's header comment. The search bar, filter
 * dropdowns, date range/facility selectors, and pagination controls
 * aren't wired up yet.
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

require_once __DIR__ . '/../includes/admin/audit_logs_firestore.php';
// $adminProfile, $facilityOptions, $filterGroups,
// $actionTypeMeta, $auditLogs, $auditLogsTotal, $auditLogsPerPage,
// $auditLogsCurrentPage, $auditLogsTotalPages, resolveActionMeta()

// Sidebar nav — same System Administrator nav list as every other admin
// page (admin_dashboard.php, facility_management.php, ...). Audit Logs
// is active here.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
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
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => 'Admin',                  'href' => 'audit_logs.php?role=admin',              'active' => $auditRoleFilter === 'admin'],
            ['label' => 'Sub Admin',             'href' => 'audit_logs.php?role=subadmin',           'active' => $auditRoleFilter === 'subadmin'],
            ['label' => 'Doctor',                'href' => 'audit_logs.php?role=doctor',             'active' => $auditRoleFilter === 'doctor'],
            ['label' => 'Medical Staff',          'href' => 'audit_logs.php?role=healthcare_staff',   'active' => $auditRoleFilter === 'healthcare_staff'],
            ['label' => 'Medical Technologist',   'href' => 'audit_logs.php?role=medical_technician', 'active' => $auditRoleFilter === 'medical_technician'],
            ['label' => 'Radiologic Technologist','href' => 'audit_logs.php?role=radio_technologist',  'active' => $auditRoleFilter === 'radio_technologist'],
            ['label' => 'Pharmacist',            'href' => 'audit_logs.php?role=pharmacist',          'active' => $auditRoleFilter === 'pharmacist'],
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
<title>Audit Logs — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, table, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Audit Logs page styles (sidebar facility chip, search bar, filter toolbar, action/status badges) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/audit_logs.css') ?>">
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
            <h1><?= t('page.audit_logs.title') ?><?= $auditRoleFilterLabel !== '' ? ' — ' . htmlspecialchars($auditRoleFilterLabel) . ' Activity' : '' ?></h1>
            <p class="adm-page-subtitle"><?= t('page.audit_logs.subtitle') ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Plain date + time, same .adm-datetime-widget every other
                     admin page uses — this used to be a SECOND, functionally
                     duplicate "Date Range" dropdown here, the exact same
                     param/options as the filter toolbar's own date range
                     below (now a real From/To range), which just
                     needlessly let the same filter be set from two
                     different places. -->
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

    <!-- ============ SEARCH BAR ============ -->
    <form class="aud-search-bar" method="get" action="audit_logs.php">
        <i class="bi bi-search"></i>
        <!-- Real — fuzzy-matches user/action/module/details (hc_filter_audit_logs()),
             same algorithm every other real search box in this app uses. Submits
             on Enter; every active filter/page is preserved via the hidden inputs
             below so searching never resets them. -->
        <input type="text" id="auditSearchInput" name="q" value="<?= htmlspecialchars($auditSearchTerm) ?>" placeholder="Search by user, action, module, or details..." autocomplete="off">
        <input type="hidden" name="user" value="<?= htmlspecialchars($auditUserFilter) ?>">
        <input type="hidden" name="module" value="<?= htmlspecialchars($auditModuleFilter) ?>">
        <input type="hidden" name="action" value="<?= htmlspecialchars($auditActionFilter) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($auditStatusFilter) ?>">
        <input type="hidden" name="dateRangeFrom" value="<?= htmlspecialchars($auditDateFrom) ?>">
        <input type="hidden" name="dateRangeTo" value="<?= htmlspecialchars($auditDateTo) ?>">
        <!-- Real user-name suggestions as you type (assets/js/audit-logs-search.js)
             — clicking one filters the table to that person's own log,
             same real name list the User filter dropdown below uses. -->
        <div class="aud-search-suggest" id="auditSearchSuggest" hidden></div>
    </form>
    <script id="auditUserListScript">window.AUDIT_USER_LIST = <?= json_encode(array_keys($seenUsers)) ?>;</script>

    <!-- ============ FILTER TOOLBAR ============ -->
    <div class="aud-filter-toolbar">
        <!-- Real From/To date range — any span, not a preset Today/This
             Week/This Month bucket. Always visible, not a dropdown.
             Auto-submits on change, carrying every other active filter
             via hidden inputs so picking a date never drops them. -->
        <form class="aud-daterange" method="get" action="audit_logs.php">
            <i class="bi bi-calendar3"></i>
            <label for="auditDateFrom">From</label>
            <input type="date" id="auditDateFrom" name="dateRangeFrom" value="<?= htmlspecialchars($auditDateFrom) ?>" onchange="this.form.submit()">
            <span class="aud-dot"></span>
            <label for="auditDateTo">To</label>
            <input type="date" id="auditDateTo" name="dateRangeTo" value="<?= htmlspecialchars($auditDateTo) ?>" onchange="this.form.submit()">
            <input type="hidden" name="q" value="<?= htmlspecialchars($auditSearchTerm) ?>">
            <input type="hidden" name="user" value="<?= htmlspecialchars($auditUserFilter) ?>">
            <input type="hidden" name="module" value="<?= htmlspecialchars($auditModuleFilter) ?>">
            <input type="hidden" name="action" value="<?= htmlspecialchars($auditActionFilter) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($auditStatusFilter) ?>">
            <?php if ($auditRoleFilter !== ''): ?><input type="hidden" name="role" value="<?= htmlspecialchars($auditRoleFilter) ?>"><?php endif; ?>
        </form>

        <?php foreach ($filterGroups as $fg): ?>
        <div class="dropdown">
            <button class="aud-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi <?= htmlspecialchars($fg['icon']) ?>"></i> <?= htmlspecialchars($fg['label']) ?>
            </button>
            <ul class="dropdown-menu">
                <?php foreach ($fg['options'] as $opt): ?>
                <li><a class="dropdown-item <?= $opt === $fg['label'] ? 'active' : '' ?>" href="?<?= hc_audit_query([$fg['key'] => $opt, 'page' => 1]) ?>"><?= htmlspecialchars($opt) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>

        <a href="audit_logs.php" class="aud-clear-filters-btn">
            Clear Filters
        </a>
    </div>

    <!-- ============ AUDIT LOGS TABLE ============ -->
    <div class="adm-card aud-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table aud-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time <i class="bi bi-arrow-down"></i></th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty">No audit log entries yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($auditLogs as $log): $meta = resolveActionMeta($actionTypeMeta, $log['action']); ?>
                    <tr>
                        <td class="aud-datetime"><?= htmlspecialchars($log['datetime']) ?></td>
                        <td>
                            <div class="adm-activity-name-cell">
                                <span class="adm-avatar-sm initials <?= htmlspecialchars($log['avatarColor']) ?>"><?= htmlspecialchars($log['initials']) ?></span>
                                <span class="aud-user-cell">
                                    <span class="aud-user-name"><?= htmlspecialchars($log['user']) ?></span>
                                    <span class="aud-user-role"><?= htmlspecialchars($log['role']) ?></span>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="aud-action-badge" style="background:<?= htmlspecialchars($meta['bg']) ?>; color:<?= htmlspecialchars($meta['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i> <?= htmlspecialchars($meta['label']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['module']) ?></td>
                        <td class="aud-details"><?= htmlspecialchars($log['details']) ?></td>
                        <td><span class="aud-status-badge <?= htmlspecialchars($log['status']) ?>"><?= ucfirst(htmlspecialchars($log['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $auditLogsTotal > 0 ? 'Showing ' . (($auditLogsCurrentPage - 1) * $auditLogsPerPage + 1) . ' to ' . min($auditLogsCurrentPage * $auditLogsPerPage, $auditLogsTotal) . ' of ' . number_format($auditLogsTotal) . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Audit log pages">
                <a href="?<?= hc_audit_query(['page' => 1]) ?>" class="page-btn <?= $auditLogsCurrentPage <= 1 ? 'disabled' : '' ?>" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
                <a href="?<?= hc_audit_query(['page' => max(1, $auditLogsCurrentPage - 1)]) ?>" class="page-btn <?= $auditLogsCurrentPage <= 1 ? 'disabled' : '' ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php foreach ($auditPaginationWindow as $p): ?>
                    <?php if ($p === '...'): ?>
                    <span class="page-ellipsis">&hellip;</span>
                    <?php else: ?>
                    <a href="?<?= hc_audit_query(['page' => $p]) ?>" class="page-btn <?= $p === $auditLogsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="?<?= hc_audit_query(['page' => min($auditLogsTotalPages, $auditLogsCurrentPage + 1)]) ?>" class="page-btn <?= $auditLogsCurrentPage >= $auditLogsTotalPages ? 'disabled' : '' ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <a href="?<?= hc_audit_query(['page' => $auditLogsTotalPages]) ?>" class="page-btn <?= $auditLogsCurrentPage >= $auditLogsTotalPages ? 'disabled' : '' ?>" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
            </nav>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/audit-logs-search.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
<!-- Smooth, no-reload swap for filter/pagination/role-switch clicks
     (assets/js/ajax-report-nav.js) — same mechanism the Analytics &
     Reports pages already use. -->
<script src="<?= asset_url('/assets/js/ajax-report-nav.js') ?>"></script>
</body>
</html>
