<?php
/**
 * =====================================================================
 * Profile / Settings — Data
 * ---------------------------------------------------------------------
 * $profileInfo, $notificationPreferences' checked state, and
 * $currentTheme/$currentLanguage are read from the signed-in admin's
 * own Firestore users/{uid} document, falling back to sensible
 * defaults for anything never saved yet (first visit). Saved via
 * api/update-profile-settings.php (Full Name / Phone / Health Center /
 * notification toggles / theme / language) and
 * api/update-admin-password.php (Password Change) — see
 * assets/js/profile-settings.js for the request handling.
 *
 * Email and Role are NOT self-service editable here (rendered
 * read-only in admin/profile_settings.php): changing Auth email needs
 * a re-verification flow this app doesn't have yet, and self-service
 * role changes are a privilege-escalation risk — both stay display-only
 * on purpose.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

// Read from the PHP session api/session-login.php populated after Firebase sign-in
// (profile_settings.php calls session_start() before requiring this file).
$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'admin' => 'System Administrator',
        'subadmin' => 'Sub-Administrator',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Administrator',
    },
];

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

// ---------------------------------------------------------------------
// Profile Information card
// ---------------------------------------------------------------------
$profileInfo = [
    'fullName' => $adminProfile['name'],
    'email' => $_SESSION['email'] ?? ($myDoc['email'] ?? 'admin@healtrack.com'),
    'phone' => $myDoc['phone'] ?? '',
    'role' => $adminProfile['role'],
    'healthCenter' => $myDoc['healthCenter'] ?? 'All Facilities',
];

$roleOptions = ['System Administrator', 'Sub-Administrator', 'Healthcare Staff'];

// A System Administrator isn't scoped to one facility — "All Facilities" is
// the sensible default here, with the individual facilities still offered
// in case a future feature (e.g. a default landing facility) needs one picked.
$healthCenterOptions = ['All Facilities', 'North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

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
// started with in the original design.
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
