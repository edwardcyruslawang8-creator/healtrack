<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Announcements (subadmin/announcements.php)
 * ---------------------------------------------------------------------
 * New top-level Sub-Admin section (its own sidebar nav item) — same
 * shape as subadmin/user_management.php and its siblings. Reuses the
 * exact same sidebar/topbar shell as the rest of subadmin/*.php
 * (280px dark-red gradient sidebar, "adm-" prefixed atoms,
 * assets/css/admin-dashboard.css). Announcements stays active in the
 * sidebar, since this IS that page.
 *
 * CONNECTED: $announcements reads LIVE from the real "announcements"
 * Firestore collection (see includes/subadmin/announcements_firestore.php's
 * header comment) — the SAME collection patient/announcements.php (the
 * public homepage's Announcements page) and index.php's "latest 3"
 * widget already read. "+ New Announcement" (includes/subadmin/add_announcement_modal.php
 * -> assets/js/announcements_subadmin.js -> api/create-announcement.php)
 * writes a real doc there, so it shows up on the public page
 * immediately — no approval step, no changes needed on the patient
 * side. The search box and category filter really filter the list.
 * There's deliberately no Edit/Delete action yet — this pass connects
 * posting, not full lifecycle management.
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

require_once __DIR__ . '/../includes/subadmin/announcements_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $categoryMeta,
// $categoryOptions, $announcementSummary, $searchTerm, $categoryFilter,
// $announcements, $announcementsTotal, $announcementsPerPage,
// $announcementsCurrentPage, $announcementsTotalPages, $adminNotes

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Announcements stays active (this IS that page).
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
    ['icon' => 'bi-megaphone-fill',         'label' => t('nav.announcements'),                'href' => 'announcements.php', 'active' => true],
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
<title>Announcements — HEALTrack</title>

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
<!-- User Management page styles, reused here for the summary cards/toolbar/table/badges — this page is visually identical (same "usm-" prefixed atoms), no new CSS needed -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/user_management.css') ?>">
<!-- "+ New Announcement" modal styles, reused from Medicine & Vaccine Inventory's Add modal -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/add_medicine_modal.css') ?>">
<!-- "Notes from Admin" list styling, reused from admin/announcements.php's own note list (.ann-adm-note-*) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin_announcements.css') ?>">
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
            <h1><?= htmlspecialchars(t('nav.announcements')) ?></h1>
            <p class="adm-page-subtitle"><?= htmlspecialchars(t('page.announcements.subtitle')) ?></p>
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

    <!-- ============ SUMMARY CARDS ============ -->
    <div class="usm-summary-row">
        <div class="adm-card usm-summary-card">
            <div class="usm-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-megaphone-fill"></i></div>
            <div class="usm-summary-text">
                <span class="usm-summary-label">Total Submitted</span>
                <span class="usm-summary-value"><?= number_format($announcementSummary['total']) ?></span>
                <span class="usm-summary-sub">All your submissions</span>
            </div>
        </div>

        <div class="adm-card usm-summary-card">
            <div class="usm-summary-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-hourglass-split"></i></div>
            <div class="usm-summary-text">
                <span class="usm-summary-label">Pending Approval</span>
                <span class="usm-summary-value"><?= number_format($announcementSummary['pending']) ?></span>
                <span class="usm-summary-sub">Awaiting admin review</span>
            </div>
        </div>

        <div class="adm-card usm-summary-card">
            <div class="usm-summary-icon" style="background:#FCE4E4; color:#D93025;"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="usm-summary-text">
                <span class="usm-summary-label">Marked Important</span>
                <span class="usm-summary-value"><?= number_format($announcementSummary['important']) ?></span>
                <span class="usm-summary-sub">Flagged for extra visibility</span>
            </div>
        </div>
    </div>

    <!-- ============ NOTES FROM ADMIN ============ -->
    <h2 class="ann-adm-section-title">Notes from Admin</h2>
    <div class="adm-card" style="margin-bottom: 24px;">
        <div class="ann-adm-note-list">
            <?php if (empty($adminNotes)): ?>
            <div class="adm-table-empty">No notes from the admin yet.</div>
            <?php endif; ?>
            <?php foreach ($adminNotes as $n): ?>
            <div class="ann-adm-note-row">
                <span class="ann-adm-note-icon"><i class="bi bi-sticky-fill"></i></span>
                <div class="ann-adm-note-body">
                    <div class="ann-adm-note-title-row">
                        <span class="ann-adm-note-title"><?= htmlspecialchars($n['title']) ?></span>
                        <span class="ann-adm-note-meta">by <?= htmlspecialchars($n['postedBy']) ?> — <?= htmlspecialchars($n['date']) ?></span>
                    </div>
                    <p class="ann-adm-note-message"><?= htmlspecialchars($n['message']) ?></p>
                    <span class="ann-adm-note-facility"><i class="bi bi-building"></i> <?= htmlspecialchars($n['facility']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ============ FILTER & SEARCH TOOLBAR ============ -->
    <form class="usm-toolbar" method="get" action="announcements.php">
        <div class="usm-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by title or description...">
        </div>
        <select class="form-select usm-filter-select" name="category" aria-label="Filter by category" onchange="this.form.submit()">
            <?php foreach ($categoryOptions as $opt): ?>
            <option <?= $opt === $categoryFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="usm-btn-add" id="btnAddAnnouncement"><i class="bi bi-plus-lg"></i> New Announcement</button>
    </form>

    <!-- ============ ANNOUNCEMENT LIST ============ -->
    <div class="usm-list-header"><h3>Posted Announcements</h3></div>

    <div class="adm-card usm-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table usm-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Posted</th>
                        <th>Important</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($announcements)): ?>
                    <tr>
                        <td colspan="5" class="adm-table-empty">No announcements posted yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php
                    $statusMeta = [
                        'pending'  => ['label' => 'Pending Approval', 'bg' => '#FDECDD', 'color' => '#E8710A'],
                        'approved' => ['label' => 'Approved',         'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
                        'rejected' => ['label' => 'Rejected',         'bg' => '#FCE4E4', 'color' => '#D93025'],
                    ];
                    ?>
                    <?php foreach ($announcements as $a): $cat = $categoryMeta[$a['category']] ?? $categoryMeta['general']; $st = $statusMeta[$a['status']] ?? $statusMeta['approved']; ?>
                    <tr>
                        <td>
                            <div class="usm-name-cell">
                                <span class="usm-avatar" style="background:#EEF0F3; color:#6B7280;">
                                    <i class="bi <?= htmlspecialchars($a['icon']) ?>"></i>
                                </span>
                                <span class="usm-strong"><?= htmlspecialchars($a['title']) ?></span>
                            </div>
                        </td>
                        <td><span class="usm-badge" style="background:<?= htmlspecialchars($cat['bg']) ?>; color:<?= htmlspecialchars($cat['color']) ?>;"><?= htmlspecialchars($cat['label']) ?></span></td>
                        <td class="usm-datetime"><?= htmlspecialchars($a['date']) ?></td>
                        <td><?= $a['important'] ? '<span class="usm-badge" style="background:#FCE4E4; color:#D93025;">Important</span>' : '—' ?></td>
                        <td><span class="usm-badge" style="background:<?= htmlspecialchars($st['bg']) ?>; color:<?= htmlspecialchars($st['color']) ?>;"><?= htmlspecialchars($st['label']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $announcementsTotal > 0 ? 'Showing ' . (($announcementsCurrentPage - 1) * $announcementsPerPage + 1) . ' to ' . (($announcementsCurrentPage - 1) * $announcementsPerPage + count($announcements)) . ' of ' . $announcementsTotal . ' entries' : 'No entries to show' ?></span>
            <?= hc_render_pagination_nav('Announcements pages', 'page', $announcementsCurrentPage, $announcementsTotalPages) ?>
        </div>
    </div>

</main>

<?php require __DIR__ . '/../includes/subadmin/add_announcement_modal.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/announcements_subadmin.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
