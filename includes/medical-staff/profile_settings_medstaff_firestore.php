<?php
/**
 * =====================================================================
 * Medical Staff Profile / Settings — Data
 * ---------------------------------------------------------------------
 * Data for medical-staff/profile_settings.php. A separate file from
 * includes/subadmin_profile_settings_firestore.php (the Sub-Admin version)
 * rather than requiring it directly — that file's $adminProfile isn't
 * the shape medical-staff/*.php pages use ($staffProfile, role label
 * "Medical Staff", matching includes/medical_staff_dashboard_firestore.php
 * and every other medstaff data file this session). $roleOptions is
 * just ['Medical Staff'] — this role has no other options to switch
 * between here (Role renders read-only either way, same as the
 * Sub-Admin version).
 *
 * $profileInfo, $notificationPreferences' checked state, and
 * $currentTheme/$currentLanguage are read from the signed-in medical
 * staff member's own Firestore users/{uid} document, falling back to
 * sensible defaults for anything never saved yet (first visit). Saved
 * for real via api/update-profile-settings.php (Full Name / Phone /
 * Health Center / notification toggles / theme / language) and
 * api/update-admin-password.php (Password Change) — both gates were
 * broadened to also accept role=healthcare_staff, alongside the
 * existing admin/subadmin, since neither endpoint's logic is
 * role-specific. Reuses assets/js/subadmin-profile-settings.js as-is
 * (see medical-staff/profile_settings.php's header comment for why)
 * for the request handling.
 *
 * Email and Role are NOT self-service editable here (rendered
 * read-only), same reasoning as the admin/subadmin versions.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Staff',
    'role'     => 'Medical Staff',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

// ---------------------------------------------------------------------
// Profile Information card
// ---------------------------------------------------------------------
$profileInfo = [
    'fullName' => $staffProfile['name'],
    'email' => $_SESSION['email'] ?? ($myDoc['email'] ?? 'staff@healtrack.com'),
    'phone' => $myDoc['phone'] ?? '',
    'role' => $staffProfile['role'],
    'healthCenter' => $myDoc['healthCenter'] ?? $staffProfile['facility'],
];

$roleOptions = ['Medical Staff'];
$healthCenterOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$passwordLastChanged = isset($myDoc['passwordChangedAt'])
    ? (new DateTime($myDoc['passwordChangedAt']))->format('F j, Y')
    : 'Not changed since account creation';

// ---------------------------------------------------------------------
// Security Settings card
// ---------------------------------------------------------------------
$twoFactorEnabled = $myDoc['twoFactorEnabled'] ?? false;
$securitySettings = [
    ['icon' => 'bi-lock-fill', 'title' => 'Password',                          'desc' => 'Last changed: ' . $passwordLastChanged, 'btnLabel' => 'Change Password', 'btnId' => 'btnChangePassword'],
    $twoFactorEnabled
        ? ['icon' => 'bi-key-fill', 'title' => 'Two-Factor Authentication (2FA)', 'desc' => 'Enabled — your account is protected with an authenticator app', 'btnLabel' => 'Disable 2FA', 'btnId' => 'btnDisable2fa']
        : ['icon' => 'bi-key-fill', 'title' => 'Two-Factor Authentication (2FA)', 'desc' => 'Add an extra layer of security', 'btnLabel' => 'Enable 2FA', 'btnId' => 'btnEnable2fa'],
];
// Real Firebase Auth state (api/session-login.php / api/verify-2fa.php
// -> $_SESSION['emailVerified'], sourced from the ID token's own
// "email_verified" claim / a live Admin SDK lookup — see those files'
// comments). Only shown when NOT verified — nothing useful to say or do
// once it is, so the row is simply omitted rather than showing an inert
// "Verified" state with no action. Soft/non-blocking by design (this
// app has no email-sending infra of its own; "Resend Email" below
// triggers Firebase's own hosted sendEmailVerification() client-side,
// same as api/generate-2fa-secret.php's QR flow needs a client SDK —
// see assets/js/email-verification.js) — verifying is never required
// to sign in or use the app.
if (!($_SESSION['emailVerified'] ?? false)) {
    $securitySettings[] = ['icon' => 'bi-envelope-exclamation-fill', 'title' => 'Email Verification', 'desc' => 'Your email address is not verified yet', 'btnLabel' => 'Resend Email', 'btnId' => 'btnResendVerification'];
}
// ---------------------------------------------------------------------
// Notification Preferences card — defaults to "on" for a first-time
// visit (nothing saved yet), matching the checked state every toggle
// started with in the reference design. Same 5 keys as the Sub-Admin
// version — api/update-profile-settings.php's allowed-key list is
// shared, not role-specific.
// ---------------------------------------------------------------------
$savedNotifications = $myDoc['notifications'] ?? [];
$notificationPreferences = [
    ['key' => 'email',        'title' => 'Email Notifications',   'desc' => 'Receive updates and alerts via email',              'checked' => $savedNotifications['email'] ?? true],
    ['key' => 'system',       'title' => 'System Notifications',  'desc' => 'Receive in-app notifications',                       'checked' => $savedNotifications['system'] ?? true],
    ['key' => 'consent',      'title' => 'Consent Alerts',        'desc' => 'Get notified for new consent requests',              'checked' => $savedNotifications['consent'] ?? true],
    ['key' => 'inventory',    'title' => 'Inventory Alerts',      'desc' => 'Get notified for low stock and expiry alerts',       'checked' => $savedNotifications['inventory'] ?? true],
    ['key' => 'consultation', 'title' => 'Consultation Alerts',   'desc' => 'Get notified for important consultation updates',    'checked' => $savedNotifications['consultation'] ?? true],
];

// ---------------------------------------------------------------------
// Appearance Settings card
// ---------------------------------------------------------------------
$themeOptions = ['Light', 'Dark', 'System'];
$languageOptions = ['English', 'Filipino'];
$currentTheme = $myDoc['theme'] ?? 'Light';
$currentLanguage = $myDoc['language'] ?? 'English';

if (!function_exists('renderSelectOptions')) {
    /**
     * Reusable helper — renders <option> tags for a plain string-options
     * dropdown (Role, Health Center, Theme, Language all share this shape),
     * marking whichever one matches $selected.
     */
    function renderSelectOptions(array $options, string $selected): string
    {
        $html = '';
        foreach ($options as $option) {
            $isSelected = $option === $selected ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($option) . '"' . $isSelected . '>' . htmlspecialchars($option) . '</option>';
        }
        return $html;
    }
}
