<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * User Management (subadmin/user_management.php)
 * ---------------------------------------------------------------------
 * Top-level Sub-Admin section (its own sidebar nav item) — same shape
 * as subadmin/medicine_vaccine_inventory.php,
 * subadmin/stock_requests.php, and subadmin/analytics_reports.php.
 * Reuses the exact same sidebar/topbar shell as the rest of
 * subadmin/*.php (280px dark-red gradient sidebar, "adm-" prefixed
 * atoms, assets/css/admin-dashboard.css) — including its existing
 * .adm-action-btn (view/edit/delete) and .adm-pagination/.page-btn/
 * .page-ellipsis atoms verbatim, so this page needed almost no new
 * shared-looking CSS. User Management stays active in the sidebar,
 * since this IS that page. Unlike its sibling top-level pages, this
 * one has no breadcrumb — just a title + subtitle, matching the
 * reference design.
 *
 * Reads live from the real "users" Firestore collection — see
 * includes/subadmin/user_management_firestore.php's header comment for the full
 * picture (real search/role/status filters, real summary counts, real
 * Last Login via Firebase Auth). "+ Add User" is real now
 * (api/create-user.php) — creates a Firebase Auth account + Firestore
 * users/{uid} profile for this sub-admin's own facility (never a
 * client-supplied one). Sub-admins intentionally have no Edit action
 * here — editing a user account isn't a sub-admin capability.
 *
 * Each row's single Actions-column button is "Manage User" — one
 * modal (#usmManageUserModal) that both shows the read-only detail
 * list (populated purely from that row's own data-user-* attributes,
 * no network request, same pattern as
 * includes/pharmacist/medicine_action_modals.php's View Details modal)
 * AND holds that account's real access actions. There's no DELETE
 * action any more (api/delete-user.php is gone) — only a reversible
 * hold: Suspend (api/suspend-user.php, status="inactive", refused at
 * sign-in by api/session-login.php) and Lock (api/lock-user.php,
 * status="locked", the exact same status includes/shared/login_lockout.php's
 * automatic 7-failed-attempts lockout already produces, so
 * api/session-login.php's existing gate covers it with no changes) are
 * offered on an active account; Reactivate (api/reactivate-user.php)
 * or Unlock (api/unlock-user.php) is offered instead once suspended/
 * locked — exactly one of these three groups is ever shown, decided
 * client-side from that row's data-user-status-key, though every
 * endpoint re-checks the real current status server-side regardless.
 * None of the four act on a peer sub-admin's own account — that stays
 * an admin-only capability (admin/subadmin_management.php) — the modal
 * shows a plain note instead for that row (data-user-role-key).
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

require_once __DIR__ . '/../includes/subadmin/user_management_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $userManagementSummary,
// $roleOptions, $userStatusOptions, $roleMeta, $userStatusMeta, $users,
// $usersTotal, $usersPerPage, $usersCurrentPage, $usersTotalPages,
// $pendingAccounts

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// User Management stays active (this IS that page).
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php'],
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
    ['icon' => 'bi-people-fill',            'label' => t('nav.user_management'),              'href' => 'user_management.php', 'active' => true],
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
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management — HEALTrack</title>

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
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- User Management page styles (summary cards, toolbar, avatars, role badges) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/user_management.css') ?>">
<!-- "am-" (Add Modal) shared modal styling, reused here for the Add User
     form and the read-only View Details modal — same reuse doctor/
     medications.php, medical-staff/medications.php, and
     subadmin/medications.php already do for their own View Details modals. -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/add_medicine_modal.css') ?>">
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
            <h1><?= htmlspecialchars(t('nav.user_management')) ?></h1>
            <p class="adm-page-subtitle"><?= htmlspecialchars(t('page.user_management.subtitle')) ?></p>
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

    <!-- ============ SUMMARY CARDS ============ -->
    <div class="usm-summary-row">
        <div class="adm-card usm-summary-card">
            <div class="usm-summary-icon" style="background:#FCE4E4; color:#D93025;"><i class="bi bi-people-fill"></i></div>
            <div class="usm-summary-text">
                <span class="usm-summary-label">Total Users</span>
                <span class="usm-summary-value"><?= number_format($userManagementSummary['totalUsers']) ?></span>
                <span class="usm-summary-sub">All system users</span>
            </div>
        </div>

        <div class="adm-card usm-summary-card">
            <div class="usm-summary-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-person-check-fill"></i></div>
            <div class="usm-summary-text">
                <span class="usm-summary-label">Active Users</span>
                <span class="usm-summary-value"><?= number_format($userManagementSummary['activeUsers']) ?></span>
                <span class="usm-summary-sub usm-trend-up"><i class="bi bi-arrow-up-short"></i> <?= rtrim(rtrim(number_format($userManagementSummary['activePct'], 1), '0'), '.') ?>% of total users</span>
            </div>
        </div>

        <div class="adm-card usm-summary-card">
            <div class="usm-summary-icon" style="background:#F1E8FD; color:#8B5CF6;"><i class="bi bi-person-dash-fill"></i></div>
            <div class="usm-summary-text">
                <span class="usm-summary-label">Inactive Users</span>
                <span class="usm-summary-value"><?= number_format($userManagementSummary['inactiveUsers']) ?></span>
                <span class="usm-summary-sub usm-trend-down"><i class="bi bi-arrow-down-short"></i> <?= rtrim(rtrim(number_format($userManagementSummary['inactivePct'], 1), '0'), '.') ?>% of total users</span>
            </div>
        </div>

        <div class="adm-card usm-summary-card">
            <div class="usm-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-shield-fill-check"></i></div>
            <div class="usm-summary-text">
                <span class="usm-summary-label">Roles</span>
                <span class="usm-summary-value"><?= number_format($userManagementSummary['roles']) ?></span>
                <span class="usm-summary-sub">System roles</span>
            </div>
        </div>
    </div>

    <!-- ============ PENDING APPROVALS ============
         Self-registered accounts (register.php) awaiting a decision —
         see includes/subadmin/user_management_firestore.php's header
         comment. Approve grants real access (status -> active);
         Reject deletes the request entirely (both the Firestore doc
         and the Firebase Auth account), since there's no "rejected"
         state to leave it in. -->
    <div class="adm-card" id="pendingApprovals" style="margin-bottom:24px;">
        <div class="adm-card-header">
            <h2>Pending Approvals</h2>
        </div>
        <div id="pendingApprovalsError" class="alert alert-danger py-2 px-3 small d-none mx-3" role="alert"></div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Requested Role</th>
                        <th>Requested On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pendingApprovalsBody">
                    <?php if (empty($pendingAccounts)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty">No accounts are awaiting approval.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($pendingAccounts as $p): ?>
                    <tr data-uid="<?= htmlspecialchars($p['uid']) ?>">
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['email']) ?></td>
                        <td><?= htmlspecialchars($p['phone']) ?></td>
                        <td><?= htmlspecialchars($p['jobTitle']) ?></td>
                        <td><?= htmlspecialchars($p['requestedAt']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="adm-action-btn continue acm-approve-btn" aria-label="Approve" data-bs-toggle="modal" data-bs-target="#usmApproveModal" data-user-id="<?= htmlspecialchars((string) $p['uid']) ?>" data-user-name="<?= htmlspecialchars($p['name']) ?>"><i class="bi bi-check-lg"></i></button>
                                <button type="button" class="adm-action-btn delete acm-reject-btn" aria-label="Reject"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============ FILTER & SEARCH TOOLBAR ============
         Search + Role + Status all really filter $users now (see
         includes/user_management_firestore.php) — previously this toolbar
         had no <form> around it at all, so nothing here could even be
         submitted. The two selects auto-submit on change; the search
         box submits on Enter (native form behavior, no button needed
         for a single text field). -->
    <form class="usm-toolbar" method="get" action="user_management.php">
        <div class="usm-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by name, email or role...">
        </div>
        <select class="form-select usm-filter-select" name="role" aria-label="Filter by role" onchange="this.form.submit()">
            <?php foreach ($roleOptions as $opt): ?>
            <option <?= $opt === $roleFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select usm-filter-select" name="status" aria-label="Filter by status" onchange="this.form.submit()">
            <?php foreach ($userStatusOptions as $opt): ?>
            <option <?= $opt === $statusFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="usm-btn-add" id="btnAddUser" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-lg"></i> Add User</button>
    </form>

    <!-- ============ USER LIST ============ -->
    <div class="usm-list-header"><h3>User List</h3></div>

    <div class="adm-card usm-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table usm-table">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department / Unit</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="adm-table-empty">No users found.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): $role = $roleMeta[$u['role']] ?? ['label' => ucfirst($u['role']), 'bg' => '#EEF0F3', 'color' => '#6B7280']; $status = $userStatusMeta[$u['status']] ?? $userStatusMeta['active']; ?>
                    <tr>
                        <td>
                            <div class="usm-name-cell">
                                <span class="usm-avatar" style="background:<?= htmlspecialchars($u['avatar']['bg']) ?>; color:<?= htmlspecialchars($u['avatar']['color']) ?>;">
                                    <i class="bi bi-person-fill"></i>
                                </span>
                                <span class="usm-strong"><?= htmlspecialchars($u['name']) ?></span>
                            </div>
                        </td>
                        <td class="usm-email-cell"><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="usm-badge" style="background:<?= htmlspecialchars($role['bg']) ?>; color:<?= htmlspecialchars($role['color']) ?>;"><?= htmlspecialchars($role['label']) ?></span></td>
                        <td><?= htmlspecialchars($u['department']) ?></td>
                        <td><span class="usm-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        <td class="usm-datetime"><?= htmlspecialchars($u['lastLogin']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="usm-manage-btn" data-bs-toggle="modal" data-bs-target="#usmManageUserModal"
                                        data-user-id="<?= htmlspecialchars((string) $u['id']) ?>"
                                        data-user-name="<?= htmlspecialchars($u['name']) ?>"
                                        data-user-email="<?= htmlspecialchars($u['email']) ?>"
                                        data-user-role="<?= htmlspecialchars($role['label']) ?>"
                                        data-user-role-key="<?= htmlspecialchars($u['role']) ?>"
                                        data-user-role-bg="<?= htmlspecialchars($role['bg']) ?>"
                                        data-user-role-color="<?= htmlspecialchars($role['color']) ?>"
                                        data-user-department="<?= htmlspecialchars($u['department']) ?>"
                                        data-user-status="<?= htmlspecialchars($status['label']) ?>"
                                        data-user-status-key="<?= htmlspecialchars($u['status']) ?>"
                                        data-user-status-bg="<?= htmlspecialchars($status['bg']) ?>"
                                        data-user-status-color="<?= htmlspecialchars($status['color']) ?>"
                                        data-user-last-login="<?= htmlspecialchars($u['lastLogin']) ?>"
                                        data-user-avatar-bg="<?= htmlspecialchars($u['avatar']['bg']) ?>"
                                        data-user-avatar-color="<?= htmlspecialchars($u['avatar']['color']) ?>">Manage User</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $usersTotal > 0 ? 'Showing ' . (($usersCurrentPage - 1) * $usersPerPage + 1) . ' to ' . (($usersCurrentPage - 1) * $usersPerPage + count($users)) . ' of ' . $usersTotal . ' entries' : 'No entries to show' ?></span>
            <?= hc_render_pagination_nav('Users pages', 'page', $usersCurrentPage, $usersTotalPages) ?>
        </div>
    </div>

</main>

<!-- ============ ADD USER MODAL ============
     Triggered by the toolbar's "+ Add User" button (#btnAddUser).
     Submitting this form POSTs to api/create-user.php, which creates a
     real Firebase Auth account (server-side via the Admin SDK, so it
     doesn't touch this sub-admin's own session) plus a matching
     Firestore users/{uid} profile doc — same shape as
     includes/admin/add_subadmin_modal.php / api/create-subadmin.php,
     except there's no Facility field here: api/create-user.php always
     assigns THIS sub-admin's own real facility server-side (resolved
     from their own users/{uid} doc, never a client-supplied value) —
     a sub-admin only manages their own facility's staff directory, so
     offering a picker would just be a choice the backend overrides.
     Role options are the exact same roles this page's table already
     manages (see $roleMeta in includes/subadmin/user_management_firestore.php). -->
<div class="modal fade am-modal" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addUserForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="addUserError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

                    <div class="mb-3">
                        <label class="am-form-label" for="auName">Full Name<span class="text-danger">*</span></label>
                        <input type="text" class="form-control am-form-control" id="auName" placeholder="e.g. Maria Santos" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="am-form-label" for="auEmail">Email<span class="text-danger">*</span></label>
                            <input type="email" class="form-control am-form-control" id="auEmail" placeholder="e.g. maria.santos@email.com" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="am-form-label" for="auRole">Role<span class="text-danger">*</span></label>
                            <select class="form-select am-form-control" id="auRole" required>
                                <?php foreach ($roleMeta as $roleKey => $meta): ?>
                                <option value="<?= htmlspecialchars($roleKey) ?>"><?= htmlspecialchars($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="am-form-label" for="auPassword">Password<span class="text-danger">*</span></label>
                            <input type="password" class="form-control am-form-control" id="auPassword" placeholder="At least 6 characters" minlength="6" required>
                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="am-form-label" for="auConfirmPassword">Confirm Password<span class="text-danger">*</span></label>
                            <input type="password" class="form-control am-form-control" id="auConfirmPassword" placeholder="Re-enter password" minlength="6" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="am-btn-submit" id="auSubmitBtn"><i class="bi bi-plus-lg"></i> <span id="auSubmitLabel">Add User</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ MANAGE USER MODAL (shared) ============
     Triggered by each row's "Manage User" button — replaces the old
     separate "View Details" modal and the row's own Suspend/Lock/
     Reactivate/Unlock icon buttons, folding all of it into one place.
     The detail list is populated purely from the clicked row's
     data-user-* attributes — no network request, since the page
     already rendered every field server-side (same pattern
     includes/pharmacist/medicine_action_modals.php's own View Details
     modal uses). Which of the four action buttons below is shown
     depends on that same row data (data-user-status-key/
     data-user-role-key) — see assets/js (inline below) for exactly how.
     Clicking one hides this modal and hands off to that action's own
     confirmation modal (#usmSuspendModal etc.) with the uid/name
     carried along, rather than acting immediately — same "confirm
     before a real status change" pattern those modals already had. -->
<div class="modal fade am-modal" id="usmManageUserModal" tabindex="-1" aria-labelledby="usmManageUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="usmManageUserModalLabel">Manage User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Profile header — same avatar-circle + role/status
                     badge language as the table row itself, so this
                     reads as that user's actual profile rather than a
                     flat label/value list. -->
                <div class="usm-profile-header">
                    <span class="usm-profile-avatar" id="udvAvatar"><i class="bi bi-person-fill"></i></span>
                    <div class="usm-profile-identity">
                        <h5 class="usm-profile-name" id="udvName"></h5>
                        <div class="usm-profile-badges">
                            <span class="usm-badge" id="udvRoleBadge"></span>
                            <span class="usm-badge" id="udvStatusBadge"></span>
                        </div>
                    </div>
                </div>

                <dl class="am-detail-list">
                    <div class="am-detail-row"><dt>Email</dt><dd id="udvEmail"></dd></div>
                    <div class="am-detail-row"><dt>Department / Unit</dt><dd id="udvDepartment"></dd></div>
                    <div class="am-detail-row"><dt>Last Login</dt><dd id="udvLastLogin"></dd></div>
                </dl>

                <div class="usm-manage-actions">
                    <p class="usm-manage-actions-label">Account Access</p>
                    <p class="usm-manage-no-actions d-none">This account's access settings are managed by the System Administrator.</p>
                    <div class="usm-manage-actions-row">
                        <button type="button" class="usm-manage-action-btn hold d-none" id="umSuspendTrigger"><i class="bi bi-person-dash-fill"></i> Suspend Account</button>
                        <button type="button" class="usm-manage-action-btn hold d-none" id="umLockTrigger"><i class="bi bi-lock-fill"></i> Lock Account</button>
                        <button type="button" class="usm-manage-action-btn restore d-none" id="umReactivateTrigger"><i class="bi bi-person-check-fill"></i> Reactivate Account</button>
                        <button type="button" class="usm-manage-action-btn restore d-none" id="umUnlockTrigger"><i class="bi bi-unlock-fill"></i> Unlock Account</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ SUSPEND ACCOUNT MODAL ============
     Replaces the old Delete action — a reversible hold instead of a
     permanent removal. Sets status="inactive", which api/session-login.php
     refuses to start a session for; api/reactivate-user.php (the
     Reactivate modal below) is the only way back. -->
<div class="modal fade" id="usmSuspendModal" tabindex="-1" aria-labelledby="usmSuspendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content usm-delete-modal">
            <div class="modal-body">
                <div class="usm-delete-icon"><i class="bi bi-person-dash-fill"></i></div>
                <h5 id="usmSuspendModalLabel">Suspend Account</h5>
                <p>Suspend <strong id="usmSuspendModalUserName">this account</strong>? They won't be able to sign in until a sub-admin reactivates it.</p>
                <div id="usmSuspendModalError" class="alert alert-danger py-2 px-3 small d-none text-start" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="usm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="usm-modal-btn-delete" id="usmSuspendModalConfirmBtn">Suspend Account</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ LOCK ACCOUNT MODAL ============
     A manual counterpart to includes/shared/login_lockout.php's
     automatic permanent lockout (7 failed sign-in attempts) — sets the
     exact same status="locked", so it's reversed the exact same way:
     the Unlock Account modal below. -->
<div class="modal fade" id="usmLockModal" tabindex="-1" aria-labelledby="usmLockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content usm-delete-modal">
            <div class="modal-body">
                <div class="usm-delete-icon"><i class="bi bi-lock-fill"></i></div>
                <h5 id="usmLockModalLabel">Lock Account</h5>
                <p>Lock <strong id="usmLockModalUserName">this account</strong>? They won't be able to sign in until a sub-admin unlocks it.</p>
                <div id="usmLockModalError" class="alert alert-danger py-2 px-3 small d-none text-start" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="usm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="usm-modal-btn-delete" id="usmLockModalConfirmBtn">Lock Account</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ REACTIVATE ACCOUNT MODAL ============
     Only ever offered on a row whose status is "inactive" (suspended
     via the Suspend Account modal above) — the only way back to
     "active" for a suspended account. -->
<div class="modal fade" id="usmReactivateModal" tabindex="-1" aria-labelledby="usmReactivateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content usm-delete-modal">
            <div class="modal-body">
                <div class="usm-delete-icon usm-approve-icon"><i class="bi bi-person-check-fill"></i></div>
                <h5 id="usmReactivateModalLabel">Reactivate Account</h5>
                <p>Reactivate <strong id="usmReactivateModalUserName">this account</strong>? They'll be able to sign in again with their existing password.</p>
                <div id="usmReactivateModalError" class="alert alert-danger py-2 px-3 small d-none text-start" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="usm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="usm-modal-btn-approve" id="usmReactivateModalConfirmBtn">Reactivate Account</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ APPROVE (ASSIGN FACILITY) MODAL ============
     Self-registered accounts (register.php) never have a Facility on
     file — nothing on that public form asks for one — so this is the
     first real point sub-admin can set it, required before access is
     granted. Facility drives real downstream filtering (e.g.
     medical-staff/consultations.php's "Assigned Physician" list only
     shows doctors at the same facility as the staff member setting the
     consultation, includes/firestore/consultations.php's
     hc_get_physician_options()) — an approved account with no facility
     would just be silently invisible wherever that matters. -->
<div class="modal fade" id="usmApproveModal" tabindex="-1" aria-labelledby="usmApproveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content usm-delete-modal">
            <div class="modal-body">
                <div class="usm-delete-icon usm-approve-icon"><i class="bi bi-check-lg"></i></div>
                <h5 id="usmApproveModalLabel">Approve Account</h5>
                <p>Approve <strong id="usmApproveModalUserName">this account</strong> to grant access at <strong><?= htmlspecialchars($adminProfile['facility']) ?></strong>.</p>
                <!-- Not a picker — a sub-admin can only approve staff into
                     their OWN facility (api/approve-account.php forces
                     this server-side regardless of what's submitted), so
                     offering a dropdown of all 3 facilities here would
                     just be a choice the backend silently overrides. -->
                <div id="usmApproveModalError" class="alert alert-danger py-2 px-3 small d-none text-start" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="usm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="usm-modal-btn-approve" id="usmApproveModalConfirmBtn">Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ UNLOCK ACCOUNT MODAL ============
     Only ever offered on a row whose status is "locked" — set for real
     by includes/shared/login_lockout.php after 7 failed sign-in
     attempts (see login.php). A correct password alone no longer
     re-opens the account once locked; this is the only way back in. -->
<div class="modal fade" id="usmUnlockModal" tabindex="-1" aria-labelledby="usmUnlockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content usm-delete-modal">
            <div class="modal-body">
                <div class="usm-delete-icon usm-approve-icon"><i class="bi bi-unlock-fill"></i></div>
                <h5 id="usmUnlockModalLabel">Unlock Account</h5>
                <p>Unlock <strong id="usmUnlockModalUserName">this account</strong>? They'll be able to sign in again with their existing password.</p>
                <div id="usmUnlockModalError" class="alert alert-danger py-2 px-3 small d-none text-start" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="usm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="usm-modal-btn-approve" id="usmUnlockModalConfirmBtn">Unlock Account</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/account-approvals.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/add-user-subadmin.js') ?>"></script>
<script>
// Generic confirm-modal controller shared by Suspend/Lock/Reactivate/
// Unlock below — each is opened programmatically (via .open(uid, name))
// from the Manage User modal's own action buttons rather than a
// data-bs-target click, since those buttons live INSIDE another modal,
// not in the table row any more. Each one fills in the name, then on
// confirm POSTs {uid} to its own endpoint and reloads on success.
function hcCreateUserStatusModal(opts) {
    var modalEl = document.getElementById(opts.modalId);
    var nameEl = document.getElementById(opts.nameId);
    var errorEl = document.getElementById(opts.errorId);
    var confirmBtn = document.getElementById(opts.confirmBtnId);
    if (!modalEl || !nameEl || !errorEl || !confirmBtn || typeof bootstrap === 'undefined') return null;
    var modal = new bootstrap.Modal(modalEl);
    var confirmBtnDefaultHtml = confirmBtn.innerHTML;
    var pendingUid = null;

    confirmBtn.addEventListener('click', function () {
        if (!pendingUid) return;

        errorEl.classList.add('d-none');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + opts.busyLabel;

        fetch(opts.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ uid: pendingUid }),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                errorEl.textContent = err.message;
                errorEl.classList.remove('d-none');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = confirmBtnDefaultHtml;
            });
    });

    return {
        open: function (uid, name) {
            pendingUid = uid;
            nameEl.textContent = name || 'this account';
            errorEl.classList.add('d-none');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = confirmBtnDefaultHtml;
            modal.show();
        },
    };
}

var hcSuspendModal = hcCreateUserStatusModal({
    modalId: 'usmSuspendModal',
    nameId: 'usmSuspendModalUserName',
    errorId: 'usmSuspendModalError',
    confirmBtnId: 'usmSuspendModalConfirmBtn',
    endpoint: '../api/suspend-user.php',
    busyLabel: 'Suspending…',
});

var hcLockModal = hcCreateUserStatusModal({
    modalId: 'usmLockModal',
    nameId: 'usmLockModalUserName',
    errorId: 'usmLockModalError',
    confirmBtnId: 'usmLockModalConfirmBtn',
    endpoint: '../api/lock-user.php',
    busyLabel: 'Locking…',
});

var hcReactivateModal = hcCreateUserStatusModal({
    modalId: 'usmReactivateModal',
    nameId: 'usmReactivateModalUserName',
    errorId: 'usmReactivateModalError',
    confirmBtnId: 'usmReactivateModalConfirmBtn',
    endpoint: '../api/reactivate-user.php',
    busyLabel: 'Reactivating…',
});

var hcUnlockModal = hcCreateUserStatusModal({
    modalId: 'usmUnlockModal',
    nameId: 'usmUnlockModalUserName',
    errorId: 'usmUnlockModalError',
    confirmBtnId: 'usmUnlockModalConfirmBtn',
    endpoint: '../api/unlock-user.php',
    busyLabel: 'Unlocking…',
});

// Manage User modal — detail list populated purely from the clicked
// row's own data-user-* attributes (no network request, same pattern
// as includes/pharmacist/medicine_action_modals.php's View Details
// modal), PLUS which of the four action buttons is shown depends on
// that same row data: exactly one of Suspend+Lock (active) /
// Reactivate (inactive) / Unlock (locked) ever applies, and none do
// for a peer sub-admin row (api/suspend-user.php etc. all refuse that
// server-side too — see their own header comments).
(function () {
    var modalEl = document.getElementById('usmManageUserModal');
    if (!modalEl) return;

    var fields = {
        avatar: document.getElementById('udvAvatar'),
        name: document.getElementById('udvName'),
        roleBadge: document.getElementById('udvRoleBadge'),
        statusBadge: document.getElementById('udvStatusBadge'),
        email: document.getElementById('udvEmail'),
        department: document.getElementById('udvDepartment'),
        lastLogin: document.getElementById('udvLastLogin'),
    };

    var noActionsNote = modalEl.querySelector('.usm-manage-no-actions');
    var suspendTrigger = document.getElementById('umSuspendTrigger');
    var lockTrigger = document.getElementById('umLockTrigger');
    var reactivateTrigger = document.getElementById('umReactivateTrigger');
    var unlockTrigger = document.getElementById('umUnlockTrigger');
    var allTriggers = [suspendTrigger, lockTrigger, reactivateTrigger, unlockTrigger];

    var currentUid = null;
    var currentName = null;

    modalEl.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return;

        currentUid = trigger.getAttribute('data-user-id');
        currentName = trigger.getAttribute('data-user-name');

        fields.name.textContent = currentName || '—';
        fields.email.textContent = trigger.getAttribute('data-user-email') || '—';
        fields.department.textContent = trigger.getAttribute('data-user-department') || '—';
        fields.lastLogin.textContent = trigger.getAttribute('data-user-last-login') || '—';

        fields.avatar.style.background = trigger.getAttribute('data-user-avatar-bg') || '';
        fields.avatar.style.color = trigger.getAttribute('data-user-avatar-color') || '';

        fields.roleBadge.textContent = trigger.getAttribute('data-user-role') || '—';
        fields.roleBadge.style.background = trigger.getAttribute('data-user-role-bg') || '';
        fields.roleBadge.style.color = trigger.getAttribute('data-user-role-color') || '';

        fields.statusBadge.textContent = trigger.getAttribute('data-user-status') || '—';
        fields.statusBadge.style.background = trigger.getAttribute('data-user-status-bg') || '';
        fields.statusBadge.style.color = trigger.getAttribute('data-user-status-color') || '';

        var statusKey = trigger.getAttribute('data-user-status-key');
        var roleKey = trigger.getAttribute('data-user-role-key');

        allTriggers.forEach(function (btn) { if (btn) btn.classList.add('d-none'); });
        noActionsNote.classList.add('d-none');

        if (roleKey === 'subadmin') {
            noActionsNote.classList.remove('d-none');
        } else if (statusKey === 'active') {
            if (suspendTrigger) suspendTrigger.classList.remove('d-none');
            if (lockTrigger) lockTrigger.classList.remove('d-none');
        } else if (statusKey === 'inactive') {
            if (reactivateTrigger) reactivateTrigger.classList.remove('d-none');
        } else if (statusKey === 'locked') {
            if (unlockTrigger) unlockTrigger.classList.remove('d-none');
        }
    });

    // Hides this modal, then hands off to the target action's own
    // confirmation modal with the uid/name carried along — Bootstrap
    // stacks a second modal.show() fine, but waiting for this one to
    // finish hiding first avoids a double backdrop.
    function handOffTo(targetModal) {
        if (!targetModal || !currentUid) return;
        modalEl.addEventListener('hidden.bs.modal', function once() {
            modalEl.removeEventListener('hidden.bs.modal', once);
            targetModal.open(currentUid, currentName);
        });
        bootstrap.Modal.getInstance(modalEl).hide();
    }

    if (suspendTrigger) suspendTrigger.addEventListener('click', function () { handOffTo(hcSuspendModal); });
    if (lockTrigger) lockTrigger.addEventListener('click', function () { handOffTo(hcLockModal); });
    if (reactivateTrigger) reactivateTrigger.addEventListener('click', function () { handOffTo(hcReactivateModal); });
    if (unlockTrigger) unlockTrigger.addEventListener('click', function () { handOffTo(hcUnlockModal); });
})();

// Approve modal — fills in the clicked row's user id/name, then
// "Approve" POSTs {uid} to api/approve-account.php, which assigns this
// sub-admin's own real facility server-side (never a client-supplied
// one — see that file's header comment), so there's nothing to pick
// here any more.
(function () {
    var modalEl = document.getElementById('usmApproveModal');
    var nameEl = document.getElementById('usmApproveModalUserName');
    var errorEl = document.getElementById('usmApproveModalError');
    var confirmBtn = document.getElementById('usmApproveModalConfirmBtn');
    var confirmBtnDefaultHtml = confirmBtn.innerHTML;
    var pendingUid = null;

    modalEl.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        pendingUid = trigger ? trigger.getAttribute('data-user-id') : null;
        nameEl.textContent = (trigger && trigger.getAttribute('data-user-name')) || 'this account';
        errorEl.classList.add('d-none');
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = confirmBtnDefaultHtml;
    });

    confirmBtn.addEventListener('click', function () {
        if (!pendingUid) return;

        errorEl.classList.add('d-none');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Approving…';

        fetch('../api/approve-account.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ uid: pendingUid }),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                errorEl.textContent = err.message;
                errorEl.classList.remove('d-none');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = confirmBtnDefaultHtml;
            });
    });
})();

</script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
