<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Announcements (admin/announcements.php)
 * ---------------------------------------------------------------------
 * Reuses the same sidebar/topbar shell as the rest of admin/*.php.
 * Two real, independent things here — see
 * includes/admin/announcements_firestore.php's own header comment:
 *
 * 1. Pending Approval — every sub-admin-submitted announcement still
 *    awaiting review. Approve/Reject (assets/js/admin-announcements.js
 *    -> api/approve-announcement.php / api/reject-announcement.php)
 *    are the only thing that ever makes a sub-admin's post visible to
 *    patients now — api/create-announcement.php always stamps a new
 *    post 'pending', not live.
 * 2. Send a Note to Sub-Admins — a private, internal note (a separate
 *    "admin_notes" Firestore collection, never shown to patients) that
 *    every sub-admin (or just one facility's) sees on their own
 *    subadmin/announcements.php.
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

require_once __DIR__ . '/../includes/admin/announcements_firestore.php';
// $adminProfile, $facilityOptions, $noteFacilityOptions, $categoryMeta,
// $pendingAnnouncements, $announcementSummary, $allAdminNotes

// Sidebar nav — Announcements is active on this page.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php'],    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php'],
    ['icon' => 'bi-geo-alt-fill',        'label' => t('nav.gis_mapping'),         'href' => 'gis_mapping.php'],
    ['icon' => 'bi-shield-lock-fill',    'label' => t('nav.account_monitoring'),  'href' => 'account_monitoring.php'],
    ['icon' => 'bi-megaphone-fill',      'label' => 'Announcements',              'href' => 'announcements.php', 'active' => true],
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
<!-- Announcements (admin) page styles (stat cards, pending-queue badges/buttons, note composer/list) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin_announcements.css') ?>">
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
            <h1>Announcements</h1>
            <p class="adm-page-subtitle">Review sub-admin announcements and send notes to sub-admins.</p>
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

    <!-- ============ STAT CARDS ============ -->
    <div class="ann-adm-stats-row">
        <div class="adm-card ann-adm-stat-card">
            <div class="ann-adm-stat-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="ann-adm-stat-label">Pending Approval</div>
                <div class="ann-adm-stat-value"><?= number_format($announcementSummary['pending']) ?></div>
            </div>
        </div>
        <div class="adm-card ann-adm-stat-card">
            <div class="ann-adm-stat-icon" style="background:#E4F7EA; color:#1E8E3E;"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="ann-adm-stat-label">Approved</div>
                <div class="ann-adm-stat-value"><?= number_format($announcementSummary['approved']) ?></div>
            </div>
        </div>
        <div class="adm-card ann-adm-stat-card">
            <div class="ann-adm-stat-icon" style="background:#FCE4E4; color:#D93025;"><i class="bi bi-x-circle-fill"></i></div>
            <div>
                <div class="ann-adm-stat-label">Rejected</div>
                <div class="ann-adm-stat-value"><?= number_format($announcementSummary['rejected']) ?></div>
            </div>
        </div>
    </div>

    <!-- ============ PENDING APPROVAL ============ -->
    <h2 class="ann-adm-section-title">Pending Approval</h2>
    <div class="adm-card">
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Posted By</th>
                        <th>Date</th>
                        <th>Important</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingAnnouncements)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty">Nothing awaiting approval right now.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($pendingAnnouncements as $a): $cat = $categoryMeta[$a['category']] ?? $categoryMeta['general']; ?>
                    <tr data-announcement-row="<?= htmlspecialchars($a['id']) ?>">
                        <td><span style="font-weight:600;"><?= htmlspecialchars($a['title']) ?></span></td>
                        <td><span class="ann-adm-badge" style="background:<?= htmlspecialchars($cat['bg']) ?>; color:<?= htmlspecialchars($cat['color']) ?>;"><?= htmlspecialchars($cat['label']) ?></span></td>
                        <td><?= htmlspecialchars($a['postedBy']) ?></td>
                        <td><?= htmlspecialchars($a['date']) ?></td>
                        <td><?= $a['important'] ? '<span class="ann-adm-badge" style="background:#FCE4E4; color:#D93025;">Important</span>' : '—' ?></td>
                        <td>
                            <div class="ann-adm-action-btns">
                                <button type="button" class="ann-adm-btn-approve" data-id="<?= htmlspecialchars($a['id']) ?>"><i class="bi bi-check-lg"></i> Approve</button>
                                <button type="button" class="ann-adm-btn-reject" data-id="<?= htmlspecialchars($a['id']) ?>"><i class="bi bi-x-lg"></i> Reject</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============ SEND A NOTE TO SUB-ADMINS ============ -->
    <h2 class="ann-adm-section-title">Send a Note to Sub-Admins</h2>
    <div class="adm-card">
        <form class="ann-adm-note-form" id="adminNoteForm">
            <div class="ann-adm-field">
                <label for="noteTitle">Title</label>
                <input type="text" id="noteTitle" name="title" placeholder="e.g. Reminder about monthly reports" maxlength="120" required>
            </div>
            <div class="ann-adm-field">
                <label for="noteFacility">Send to</label>
                <select id="noteFacility" name="facility">
                    <?php foreach ($noteFacilityOptions as $fac): ?>
                    <option><?= htmlspecialchars($fac) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ann-adm-field full">
                <label for="noteMessage">Message</label>
                <textarea id="noteMessage" name="message" placeholder="Write the note here..." maxlength="1000" required></textarea>
            </div>
            <button type="submit" class="ann-adm-note-submit"><i class="bi bi-send-fill"></i> Send Note</button>
            <p class="ann-adm-note-feedback" id="adminNoteFeedback"></p>
        </form>
    </div>

    <!-- ============ NOTES SENT ============ -->
    <h2 class="ann-adm-section-title">Notes Sent</h2>
    <div class="adm-card">
        <div class="ann-adm-note-list" id="adminNoteList">
            <?php if (empty($allAdminNotes)): ?>
            <div class="adm-table-empty">No notes sent yet.</div>
            <?php endif; ?>
            <?php foreach ($allAdminNotes as $n): ?>
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

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-announcements.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
</body>
</html>
