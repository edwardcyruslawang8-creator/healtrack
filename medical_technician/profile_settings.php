<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Profile / Settings (medical_technician/profile_settings.php)
 * ---------------------------------------------------------------------
 * Top-level Medical Technologist section (its own sidebar nav item).
 * Reuses the exact same sidebar/topbar shell as the rest of
 * medical_technician/*.php (280px dark-red gradient sidebar, "adm-"/
 * "mt-" prefixed atoms, assets/css/admin-dashboard.css). Reuses
 * assets/css/profile_settings.css directly (rather than a new
 * page-specific stylesheet) since this page's profile form / password
 * fields / toggles are visually identical to every other role's
 * profile_settings.php. Profile / Settings stays active in the
 * sidebar, since this IS that page.
 *
 * UNLIKE medical_technician/dashboard.php and laboratory_xray.php,
 * this one is NOT front-end only: profile info, notification prefs,
 * and theme/language read live from the signed-in technician's own
 * Firestore users/{uid} doc, and Save Changes / Update Password / the
 * notification toggles / theme / language all persist for real — see
 * includes/medical_technician/profile_settings_medtech_firestore.php's
 * header comment for the full picture. Reuses
 * assets/js/subadmin-profile-settings.js as-is rather than a
 * "medtech-" copy — that script only touches DOM ids and two
 * role-agnostic API endpoints, nothing subadmin-specific, and this
 * page's markup uses the exact same ids on purpose so it keeps working
 * unchanged. "Enable 2FA" is real too now (includes/shared/two_fa_setup_modal.php,
 * assets/js/two-fa.js, api/generate-2fa-secret.php +
 * api/confirm-2fa-setup.php / api/disable-2fa.php) — a genuine TOTP
 * secret an authenticator app can scan, required on every future
 * sign-in once enabled (api/session-login.php, api/verify-2fa.php).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in medical technologist may view this page.
if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/shared/account_password_helpers.php'; // hc_account_has_password()
$hasPassword = hc_account_has_password($_SESSION['uid'] ?? '');

require_once __DIR__ . '/../includes/medical_technician/profile_settings_medtech_firestore.php';
// $staffProfile, $profileInfo, $roleOptions, $healthCenterOptions,
// $passwordLastChanged, $securitySettings, $notificationPreferences,
// $themeOptions, $languageOptions, $currentTheme, $currentLanguage,
// renderSelectOptions()

require_once __DIR__ . '/../includes/firestore/lab_results.php'; // hc_get_pending_lab_xray_count() for the sidebar badge below

// This page's topbar needs its own $dashboardDateLabel and
// $facilityOptions (every other medical_technician page gets these
// from its own data file too) — not part of the settings data file
// above since they're just topbar chrome, unrelated to what this page
// saves.
$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// Sidebar nav — same Medical Technologist nav list as dashboard.php.
// Profile / Settings stays active (this IS that page).
$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',                       'href' => 'dashboard.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',      'label' => 'Laboratory & X-ray',              'href' => 'laboratory_xray.php'],
    ['icon' => 'bi-inboxes-fill',              'label' => 'Laboratory & X-ray Consultation', 'href' => 'lab_xray_consultation.php', 'badge' => hc_get_pending_lab_xray_count()],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => 'Analytics & Reports',
        'id' => 'mtNavAnalyticsReports',
        'children' => [
            ['label' => 'Analytics', 'href' => 'analytics_reports.php'],
            ['label' => 'My Reports', 'href' => 'patients_report.php'],
        ],
    ],
    ['icon' => 'bi-bell-fill',                 'label' => 'Notifications',                   'href' => 'notifications.php', 'badge' => hc_medtech_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',                 'label' => 'Profile / Settings',              'href' => 'profile_settings.php', 'active' => true],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
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
<!-- Medical Technologist Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
<!-- Profile / Settings page styles (profile form, password fields, toggles) — shared verbatim with every other role's profile_settings.php -->
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
            <p>Health Information System</p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Medical technologist profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($staffProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($staffProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($staffNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'mtNavAnalyticsReports') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'mtNavAnalyticsReports') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'mtNavAnalyticsReports') ?>">
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
                <span class="mt-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <span>Log Out</span>
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
            <h1>Profile / Settings</h1>
            <p class="adm-page-subtitle">Manage your profile information and application preferences.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="mt-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this technician is
                     actually assigned to (their real users/{uid} doc's "facility"
                     field); not a switcher, since a technician can't reassign
                     themselves. -->
                <div class="mt-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($staffProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_medtech_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_medtech_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars($staffProfile['name']) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($staffProfile['role']) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i>Profile / Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
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
<script>window.HC_HAS_PASSWORD = <?= $hasPassword ? 'true' : 'false' ?>;</script>
<script src="<?= asset_url('/assets/js/subadmin-profile-settings.js') ?>"></script>
<script src="<?= asset_url('/assets/js/two-fa.js') ?>"></script>
<script src="<?= asset_url('/assets/js/profile-photo-preview.js') ?>"></script>
</body>
</html>
