<?php
/**
 * =====================================================================
 * Radiologic Technologist Profile / Settings — Data
 * ---------------------------------------------------------------------
 * Data for radiologic_technologist/profile_settings.php. Modeled directly on
 * includes/medical_technician/profile_settings_medtech_firestore.php —
 * $roleOptions is just ['Radiologic Technologist'], this role has no other
 * options to switch between here (Role renders read-only either way).
 *
 * $profileInfo, $notificationPreferences' checked state, and
 * $currentTheme/$currentLanguage are read from the signed-in
 * technologist's own Firestore users/{uid} document, falling back to
 * sensible defaults for anything never saved yet. Saved for real via
 * api/update-profile-settings.php and api/update-admin-password.php —
 * both gates were already broadened to accept role=radio_technologist
 * (neither endpoint's logic is role-specific). Reuses
 * assets/js/subadmin-profile-settings.js as-is, same as every other
 * role's profile_settings.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Radiologic Technologist',
    'role'     => 'Radiologic Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

// ---------------------------------------------------------------------
// Profile Information card
// ---------------------------------------------------------------------
$profileInfo = [
    'fullName' => $staffProfile['name'],
    'email' => $_SESSION['email'] ?? ($myDoc['email'] ?? 'radiotech@healtrack.com'),
    'phone' => $myDoc['phone'] ?? '',
    'role' => $staffProfile['role'],
    'healthCenter' => $myDoc['healthCenter'] ?? $staffProfile['facility'],
];

$roleOptions = ['Radiologic Technologist'];
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
if (!($_SESSION['emailVerified'] ?? false)) {
    $securitySettings[] = ['icon' => 'bi-envelope-exclamation-fill', 'title' => 'Email Verification', 'desc' => 'Your email address is not verified yet', 'btnLabel' => 'Resend Email', 'btnId' => 'btnResendVerification'];
}

// ---------------------------------------------------------------------
// Notification Preferences card — defaults to "on" for a first-time
// visit (nothing saved yet).
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
