<?php
/**
 * =====================================================================
 * Update Profile Settings (api/update-profile-settings.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/profile-settings.js (admin/profile_settings.php)
 * and assets/js/subadmin-profile-settings.js
 * (subadmin/profile_settings.php, medical-staff/profile_settings.php,
 * AND pharmacist/profile_settings.php — the latter two both reuse that
 * same script as-is) from three places: the "Save Changes" button
 * (Full Name / Phone / Health Center), and auto-save on every
 * notification toggle / theme / language change. All POST here with
 * only the fields that changed; this merges them into the signed-in
 * user's existing users/{uid} document rather than overwriting the
 * whole thing (see api/update-subadmin.php's header comment for why
 * that matters with FirestoreRestClient::setDocument()). Generic by
 * design — nothing below branches on role, so all four pages share it
 * rather than each having their own copy.
 *
 * Email and role are intentionally NOT accepted here — see
 * includes/admin/profile_settings_firestore.php's header comment for why.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['admin', 'subadmin', 'healthcare_staff', 'pharmacist', 'medical_technician', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$uid = $_SESSION['uid'] ?? '';
if ($uid === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Your session has expired. Please sign in again.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$existing = $firestore->getDocument('users', $uid) ?? [];
$changes = [];
$logDetails = [];

if (isset($body['fullName'])) {
    $fullName = trim((string) $body['fullName']);
    if ($fullName === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Full name cannot be empty.']);
        exit;
    }
    $changes['name'] = $fullName;
    $logDetails[] = 'name';
}

if (isset($body['phone'])) {
    $changes['phone'] = trim((string) $body['phone']);
    $logDetails[] = 'phone number';
}

if (isset($body['healthCenter'])) {
    $changes['healthCenter'] = trim((string) $body['healthCenter']);
    $logDetails[] = 'health center';
}

if (isset($body['notifications']) && is_array($body['notifications'])) {
    $allowedKeys = ['email', 'system', 'consent', 'inventory', 'consultation'];
    $notifications = [];
    foreach ($allowedKeys as $key) {
        if (isset($body['notifications'][$key])) {
            $notifications[$key] = (bool) $body['notifications'][$key];
        }
    }
    $changes['notifications'] = array_merge($existing['notifications'] ?? [], $notifications);
    $logDetails[] = 'notification preferences';
}

if (isset($body['theme'])) {
    $theme = trim((string) $body['theme']);
    if (!in_array($theme, ['Light', 'Dark', 'System'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid theme.']);
        exit;
    }
    $changes['theme'] = $theme;
    $logDetails[] = 'theme';
}

if (isset($body['language'])) {
    $language = trim((string) $body['language']);
    if (!in_array($language, ['English', 'Filipino'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid language.']);
        exit;
    }
    $changes['language'] = $language;
    $logDetails[] = 'language';
}

if ($changes === []) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Nothing to save.']);
    exit;
}

$updated = array_merge($existing, $changes);
$saved = $firestore->setDocument('users', $uid, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save your changes. Please try again.']);
    exit;
}

// Keep the session + Auth record in sync if the display name changed,
// so the sidebar/topbar name updates without needing to sign in again.
if (isset($changes['name'])) {
    $_SESSION['name'] = $changes['name'];
    try {
        $firebaseAuth->updateUser($uid, ['displayName' => $changes['name']]);
    } catch (\Throwable $e) {
        error_log('[update-profile-settings] Auth displayName sync failed for ' . $uid . ': ' . $e->getMessage());
    }
}

// Same idea for theme/language — every admin page reads these straight
// from the session (see includes/theme_helper.php, includes/i18n.php)
// rather than re-querying Firestore per page load.
if (isset($changes['theme'])) {
    $_SESSION['theme'] = $changes['theme'];
}
if (isset($changes['language'])) {
    $_SESSION['language'] = $changes['language'];
}

write_audit_log('updated', 'Profile / Settings', 'Updated ' . implode(', ', $logDetails) . '.');

echo json_encode(['success' => true]);
