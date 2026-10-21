<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Homepage Settings (subadmin/homepage_settings.php)
 * ---------------------------------------------------------------------
 * New sibling under the "Profile / Settings" dropdown (now a real
 * 2-item submenu: Profile Settings / Homepage Settings — see every
 * subadmin/*.php page's own $adminNavItems). Real, subadmin-editable
 * content for the PUBLIC homepage (index.php)'s About section
 * (Mission/Vision/Community First) and footer (Contact Us/Operating
 * Hours) — all used to be hardcoded directly in index.php with no way
 * to change them without editing code.
 *
 * includes/shared/homepage_settings.php's hc_get_homepage_settings()
 * is the single real source both this page AND index.php itself read
 * through, so a save here is immediately live on the public homepage —
 * see api/update-homepage-settings.php for the actual write.
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

require_once __DIR__ . '/../includes/shared/homepage_settings.php'; // hc_get_homepage_settings()
require_once __DIR__ . '/../config/firebase.php'; // $firestore

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Sub-Administrator',
    },
    'facility' => $myDoc['facility'] ?? 'Unassigned Facility',
];

$dashboardDateLabel = date('F j, Y');
$homepageSettings = hc_get_homepage_settings();

// Sidebar nav — same real list every subadmin/*.php page carries, with
// the Profile / Settings dropdown open and its Homepage Settings child
// active, since this IS that page.
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
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => t('nav.profile_settings'), 'href' => 'profile_settings.php'],
            ['label' => t('nav.homepage_settings'), 'href' => 'homepage_settings.php', 'active' => true],
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
<title>Homepage Settings — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Reused verbatim from profile_settings.php — same card/field styles -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/profile_settings.css') ?>">
<!-- This page's own repeatable-row styles (Operating Hours) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/homepage_settings.css') ?>">
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
            <h1><?= htmlspecialchars(t('nav.homepage_settings')) ?></h1>
            <p class="adm-page-subtitle"><?= htmlspecialchars(t('page.homepage_settings.subtitle')) ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <a href="<?= BASE_URL ?>/index.php" target="_blank" rel="noopener" class="sad-topbar-select-btn">
                    <i class="bi bi-box-arrow-up-right"></i> View Public Homepage
                </a>

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

    <div id="hpsError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>
    <div id="hpsSuccess" class="alert alert-success py-2 px-3 d-none" style="font-size:13px;"></div>

    <form id="homepageSettingsForm">

        <!-- ============ ABOUT SECTION ============ -->
        <div class="adm-card prf-section">
            <div class="prf-section-header">
                <span class="prf-section-icon red"><i class="bi bi-info-circle-fill"></i></span>
                <div>
                    <h2>About Section</h2>
                    <p>The "About North Super Health Center" section on the public homepage.</p>
                </div>
            </div>

            <div class="prf-field">
                <label for="hpsMission">Mission</label>
                <textarea class="form-control" id="hpsMission" rows="2"><?= htmlspecialchars($homepageSettings['missionText']) ?></textarea>
            </div>
            <div class="prf-field">
                <label for="hpsVision">Vision</label>
                <textarea class="form-control" id="hpsVision" rows="2"><?= htmlspecialchars($homepageSettings['visionText']) ?></textarea>
            </div>
            <div class="prf-field">
                <label for="hpsCommunityFirst">Community First</label>
                <textarea class="form-control" id="hpsCommunityFirst" rows="2"><?= htmlspecialchars($homepageSettings['communityFirstText']) ?></textarea>
            </div>
        </div>

        <!-- ============ CONTACT US ============ -->
        <div class="adm-card prf-section">
            <div class="prf-section-header">
                <span class="prf-section-icon red"><i class="bi bi-telephone-fill"></i></span>
                <div>
                    <h2>Contact Us</h2>
                    <p>Shown in the homepage footer.</p>
                </div>
            </div>

            <div class="prf-field">
                <label for="hpsAddress">Address</label>
                <input type="text" class="form-control" id="hpsAddress" value="<?= htmlspecialchars($homepageSettings['contactAddress']) ?>">
            </div>
            <div class="prf-name-email-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="prf-field">
                    <label for="hpsPhone">Phone</label>
                    <input type="text" class="form-control" id="hpsPhone" value="<?= htmlspecialchars($homepageSettings['contactPhone']) ?>">
                </div>
                <div class="prf-field">
                    <label for="hpsEmail">Email</label>
                    <input type="email" class="form-control" id="hpsEmail" value="<?= htmlspecialchars($homepageSettings['contactEmail']) ?>">
                </div>
            </div>
        </div>

        <!-- ============ OPERATING HOURS ============ -->
        <div class="adm-card prf-section">
            <div class="prf-section-header">
                <span class="prf-section-icon red"><i class="bi bi-clock-fill"></i></span>
                <div>
                    <h2>Operating Hours</h2>
                    <p>Shown in the homepage footer.</p>
                </div>
            </div>

            <div id="hpsOperatingHoursRows" class="hps-repeat-rows">
                <?php foreach ($homepageSettings['operatingHours'] as $row): ?>
                <div class="hps-repeat-row">
                    <input type="text" class="form-control" placeholder="Day(s) (e.g. Monday - Friday)" data-field="label" value="<?= htmlspecialchars($row['label']) ?>">
                    <input type="text" class="form-control" placeholder="Hours (e.g. 8:00 AM - 5:00 PM)" data-field="hours" value="<?= htmlspecialchars($row['hours']) ?>">
                    <button type="button" class="hps-row-remove" aria-label="Remove"><i class="bi bi-trash"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="hps-row-add" id="hpsAddOperatingHour"><i class="bi bi-plus-lg"></i> Add Row</button>
        </div>

        <button type="submit" class="prf-btn-primary" id="hpsSubmitBtn">
            <i class="bi bi-floppy-fill"></i> Save Changes
        </button>
    </form>

</main>

<!-- ============ SAVE CONFIRMATION MODAL ============ -->
<!-- In-app modal rather than a native window.confirm() popup (which
     browsers prefix with "localhost says", looks out of place) — same
     Bootstrap modal pattern subadmin/user_management.php's own
     Delete/Approve/Unlock confirmation modals already use. -->
<div class="modal fade" id="hpsConfirmModal" tabindex="-1" aria-labelledby="hpsConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hps-confirm-modal">
            <div class="modal-body">
                <div class="hps-confirm-icon"><i class="bi bi-question-circle-fill"></i></div>
                <h5 id="hpsConfirmModalLabel">Save Changes?</h5>
                <p>The public homepage will be updated immediately with these changes.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="hps-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="hps-modal-btn-save" id="hpsConfirmModalSaveBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/homepage-settings.js') ?>"></script>
</body>
</html>
