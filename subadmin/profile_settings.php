<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Profile / Settings (subadmin/profile_settings.php)
 * ---------------------------------------------------------------------
 * Top-level Sub-Admin section (its own sidebar nav item) — same shape
 * as subadmin/user_management.php and its siblings. Reuses the exact
 * same sidebar/topbar shell as the rest of subadmin/*.php (280px
 * dark-red gradient sidebar, "adm-" prefixed atoms,
 * assets/css/admin-dashboard.css). Reuses assets/css/profile_settings.css
 * directly (rather than a new page-specific stylesheet) since this
 * page's profile form / password fields / toggles are visually
 * identical to admin/profile_settings.php's. Profile / Settings stays
 * active in the sidebar, since this IS that page.
 *
 * UNLIKE most subadmin/*.php pages built this session, this one is
 * NOT front-end only: profile info, notification prefs, and
 * theme/language read live from the signed-in sub-admin's own
 * Firestore users/{uid} doc, and Save Changes / Update Password / the
 * notification toggles / theme / language all persist for real — see
 * includes/subadmin/subadmin_profile_settings_firestore.php's header comment for
 * the full picture, including why this has its own data file instead
 * of requiring includes/profile_settings_firestore.php directly.
 * "Enable 2FA" is real too now (includes/shared/two_fa_setup_modal.php,
 * assets/js/two-fa.js, api/generate-2fa-secret.php +
 * api/confirm-2fa-setup.php / api/disable-2fa.php) — a genuine TOTP
 * secret an authenticator app can scan, required on every future
 * sign-in once enabled (api/session-login.php, api/verify-2fa.php).
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

require_once __DIR__ . '/../includes/shared/account_password_helpers.php'; // hc_account_has_password()
$hasPassword = hc_account_has_password($_SESSION['uid'] ?? '');

require_once __DIR__ . '/../includes/subadmin/subadmin_profile_settings_firestore.php';
// $adminProfile, $profileInfo, $roleOptions, $healthCenterOptions,
// $passwordLastChanged, $securitySettings, $notificationPreferences,
// $themeOptions, $languageOptions, $currentTheme, $currentLanguage,
// renderSelectOptions()

// This page's topbar needs its own $dashboardDateLabel and
// $facilityOptions (every other subadmin page gets these from its own
// data file too) — not part of the settings data file above since
// they're just topbar chrome, unrelated to what this page saves.
$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Profile / Settings stays active (this IS that page).
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
    ['icon' => 'bi-megaphone-fill',         'label' => t('nav.announcements'),                'href' => 'announcements.php'],
    [
        'icon' => 'bi-gear-fill',
        'label' => t('nav.profile_settings'),
        'id' => 'admNavProfileSettings',
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => t('nav.profile_settings'), 'href' => 'profile_settings.php', 'active' => true],
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
<title>Profile / Settings — HEALTrack</title>

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
<!-- Profile / Settings page styles (profile form, password fields, toggles) — shared verbatim with admin/profile_settings.php -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/profile_settings.css') ?>">
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
            <h1><?= htmlspecialchars(t('nav.profile_settings')) ?></h1>
            <p class="adm-page-subtitle"><?= htmlspecialchars(t('page.profile_settings.subtitle')) ?></p>
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

    <!-- ============ TWO-COLUMN SETTINGS LAYOUT ============ -->
    <div class="prf-grid">

        <!-- ============ LEFT COLUMN ============ -->
        <div class="prf-col">

            <!-- Profile Information -->
            <div class="adm-card prf-section">
                <div class="prf-section-header">
                    <span class="prf-section-icon red"><i class="bi bi-person-fill"></i></span>
                    <div>
                        <h2>Profile Information</h2>
                        <p>Update your personal information and contact details.</p>
                    </div>
                </div>

                <form id="profileInfoForm">
                    <div class="prf-photo-row">
                        <div class="prf-photo-col">
                            <div class="prf-photo-wrap">
                                <div class="prf-photo" id="prfPhotoPreview"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
                                <button type="button" class="prf-photo-upload" id="prfPhotoUploadBtn" aria-label="Change photo">
                                    <i class="bi bi-camera-fill"></i>
                                </button>
                                <input type="file" id="prfPhotoInput" accept="image/*" class="d-none">
                            </div>
                            <?php if (!empty($_SESSION['profilePhotoFileName'])): ?>
                            <button type="button" class="prf-photo-remove" id="prfPhotoRemoveBtn">Remove Photo</button>
                            <?php endif; ?>
                        </div>
                        <div class="prf-name-email-col">
                            <div class="prf-field">
                                <label for="fullName">Full Name</label>
                                <input type="text" class="form-control" id="fullName" value="<?= htmlspecialchars($profileInfo['fullName']) ?>">
                            </div>
                            <div class="prf-field">
                                <label for="email">Email Address</label>
                                <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($profileInfo['email']) ?>" disabled>
                                <div class="form-text">Email can't be changed here yet — contact support to update it.</div>
                            </div>
                        </div>
                    </div>

                    <div class="prf-field-grid-2">
                        <div class="prf-field">
                            <label for="phone">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" value="<?= htmlspecialchars($profileInfo['phone']) ?>">
                        </div>
                        <div class="prf-field">
                            <label for="role">Role</label>
                            <select class="form-select" id="role" disabled>
                                <?= renderSelectOptions($roleOptions, $profileInfo['role']) ?>
                            </select>
                        </div>
                    </div>

                    <div class="prf-field">
                        <label for="healthCenter">Health Center</label>
                        <select class="form-select" id="healthCenter" disabled>
                            <?= renderSelectOptions($healthCenterOptions, $profileInfo['healthCenter']) ?>
                        </select>
                        <div class="form-text">Only a sub-admin or admin can change your health facility.</div>
                    </div>

                    <div class="prf-actions-right">
                        <button type="submit" class="prf-btn-primary">
                            <i class="bi bi-floppy-fill"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password Change -->
            <div class="adm-card prf-section">
                <div class="prf-section-header">
                    <span class="prf-section-icon purple"><i class="bi bi-lock-fill"></i></span>
                    <div>
                        <h2>Password Change</h2>
                        <p>Update your password regularly to keep your account secure.</p>
                    </div>
                </div>

                <form id="passwordChangeForm">
                    <?php if ($hasPassword): ?>
                    <div class="prf-field">
                        <label for="currentPassword">Current Password</label>
                        <div class="prf-password-input-wrap">
                            <input type="password" class="form-control" id="currentPassword" placeholder="Enter current password">
                            <button type="button" class="prf-toggle-visibility" aria-label="Show password" data-target="currentPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="prf-field">
                        <p style="font-size:12.5px; color:var(--text-muted,#6b7280); margin:0;">
                            <i class="bi bi-info-circle"></i> You signed up with Google, so your account doesn't have a password yet — just set one below.
                        </p>
                        <input type="hidden" id="currentPassword" value="">
                    </div>
                    <?php endif; ?>
                    <div class="prf-field">
                        <label for="newPassword">New Password</label>
                        <div class="prf-password-input-wrap">
                            <input type="password" class="form-control" id="newPassword" placeholder="Enter new password">
                            <button type="button" class="prf-toggle-visibility" aria-label="Show password" data-target="newPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="prf-field">
                        <label for="confirmPassword">Confirm New Password</label>
                        <div class="prf-password-input-wrap">
                            <input type="password" class="form-control" id="confirmPassword" placeholder="Confirm new password">
                            <button type="button" class="prf-toggle-visibility" aria-label="Show password" data-target="confirmPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="prf-btn-primary prf-btn-block">
                        <i class="bi bi-shield-lock-fill"></i> Update Password
                    </button>
                </form>
            </div>

        </div>

        <!-- ============ RIGHT COLUMN ============ -->
        <div class="prf-col">

            <!-- Security Settings -->
            <div class="adm-card prf-section">
                <div class="prf-section-header">
                    <span class="prf-section-icon red"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <h2>Security Settings</h2>
                        <p>Manage your account security.</p>
                    </div>
                </div>

                <?php foreach ($securitySettings as $s): ?>
                <div class="prf-security-row">
                    <span class="prf-security-icon"><i class="bi <?= htmlspecialchars($s['icon']) ?>"></i></span>
                    <div class="prf-security-body">
                        <div class="prf-security-title"><?= htmlspecialchars($s['title']) ?></div>
                        <div class="prf-security-desc"><?= htmlspecialchars($s['desc']) ?></div>
                    </div>
                    <button type="button" class="prf-btn-outline" id="<?= htmlspecialchars($s['btnId']) ?>">
                        <?= htmlspecialchars($s['btnLabel']) ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Notification Preferences -->
            <div class="adm-card prf-section">
                <div class="prf-section-header">
                    <span class="prf-section-icon red"><i class="bi bi-bell-fill"></i></span>
                    <div>
                        <h2>Notification Preferences</h2>
                        <p>Choose how you want to receive notifications.</p>
                    </div>
                </div>

                <?php foreach ($notificationPreferences as $n): ?>
                <div class="prf-notif-row">
                    <div class="prf-notif-body">
                        <div class="prf-notif-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="prf-notif-desc"><?= htmlspecialchars($n['desc']) ?></div>
                    </div>
                    <div class="form-check form-switch prf-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="notif-<?= htmlspecialchars($n['key']) ?>" <?= !empty($n['checked']) ? 'checked' : '' ?>>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>

    </div>

</main>

<?php require __DIR__ . '/../includes/shared/two_fa_setup_modal.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Renders the "Enable 2FA" QR code client-side (includes/shared/two_fa_setup_modal.php, assets/js/two-fa.js) -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<!-- Firebase — only for "Resend Email" (Security Settings' Email
     Verification row): firebase.auth().currentUser.sendEmailVerification()
     is the only way to actually SEND that email without our own SMTP
     infra (Firebase hosts it, same as forgot-password.php) — see
     assets/js/email-verification.js. Relies on the client-side Firebase
     session still being alive from login (its own persistence, separate
     from this page's PHP session) — that script falls back to a plain
     "please log out and back in" message if it isn't. -->
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore-compat.js"></script>
<script src="<?= asset_url('/assets/js/firebase-config.js') ?>"></script>
<script src="<?= asset_url('/assets/js/email-verification.js') ?>"></script><script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script>window.HC_HAS_PASSWORD = <?= $hasPassword ? 'true' : 'false' ?>;</script>
<script src="<?= asset_url('/assets/js/subadmin-profile-settings.js') ?>"></script>
<script src="<?= asset_url('/assets/js/two-fa.js') ?>"></script>
<script src="<?= asset_url('/assets/js/profile-photo-preview.js') ?>"></script>
</body>
</html>
